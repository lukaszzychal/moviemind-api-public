<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\QueuePersonGenerationAction;
use App\Enums\Locale;
use App\Helpers\SlugValidator;
use App\Models\Person;
use App\Models\PersonBio;
use App\Repositories\PersonRepository;
use App\Support\PersonRetrievalResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * Service for retrieving people from local database or TMDB.
 * Handles caching, local lookup, TMDB search, and person creation.
 */
class PersonRetrievalService
{
    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService,
        private readonly QueuePersonGenerationAction $queuePersonGenerationAction
    ) {}

    /**
     * Retrieve person by slug, optionally with specific bio.
     *
     * @param  string  $slug  Person slug
     * @param  string|null  $bioId  Bio ID (UUID) or null
     */
    public function retrievePerson(string $slug, ?string $bioId): PersonRetrievalResult
    {
        // Parse slug to check if it contains birth year
        $parsed = Person::parseSlug($slug);
        $nameSlug = Str::slug($parsed['name']);

        // Check cache first for exact slug match (before disambiguation logic)
        $cacheKey = $this->generateCacheKey($slug, $bioId);
        if ($cachedData = Cache::get($cacheKey)) {
            return PersonRetrievalResult::fromCache($cachedData);
        }

        // Check if slug is ambiguous (no birth year) - check for multiple matches
        // If multiple matches found, return the most recent one (200) with _meta, not disambiguation (300)
        if ($parsed['birth_year'] === null) {
            $allMatches = $this->personRepository->findAllByNameSlug($nameSlug);
            if ($allMatches->count() > 1) {
                // Multiple people found - return most recent one (already sorted by birth_date desc)
                // The _meta will be added by PersonDisambiguationService in the formatter
                $person = $allMatches->first();
            } elseif ($allMatches->count() === 1) {
                // Only one match found - use it
                $person = $allMatches->first();
            } else {
                // No matches found - try exact match
                $person = $this->personRepository->findBySlugWithRelations($slug);
            }
        } else {
            // Slug contains birth year - check for exact match
            $person = $this->personRepository->findBySlugWithRelations($slug);
        }

        if ($person === null) {
            return $this->handlePersonNotFound($slug, $bioId);
        }

        // Check if person has bios
        if (! $person->bios()->exists()) {
            // If bio_id was requested but person has no bios, return bioNotFound
            if ($bioId !== null) {
                return PersonRetrievalResult::bioNotFound();
            }

            // If person exists but has no bios and no bio_id requested, queue generation
            // (similar to MovieRetrievalService - handles concurrent requests)
            if (! Feature::active('ai_bio_generation')) {
                return PersonRetrievalResult::notFound();
            }

            // Queue generation for existing person without bio
            $validation = SlugValidator::validatePersonSlug($slug);
            $generationResult = $this->queuePersonGenerationAction->handle(
                $slug,
                confidence: $validation['confidence'],
                existingPerson: $person,
                locale: Locale::EN_US->value,
                tmdbData: null
            );

            return PersonRetrievalResult::generationQueued($generationResult);
        }

        // Person has bios - check selected bio
        $selectedBio = $this->findSelectedBio($person, $bioId);

        if ($selectedBio === false) {
            return PersonRetrievalResult::bioNotFound();
        }

        return PersonRetrievalResult::found($person, $selectedBio);
    }

    /**
     * Find selected bio for person if bio_id is provided.
     *
     * @param  Person  $person  Person instance
     * @param  string|null  $bioId  Bio ID (UUID) or null
     * @return PersonBio|null|false Returns bio if found, null if not provided, false if invalid
     */
    private function findSelectedBio(Person $person, ?string $bioId): PersonBio|null|false
    {
        if ($bioId === null) {
            return null;
        }

        $candidate = $person->bios->firstWhere('id', $bioId);

        if ($candidate instanceof PersonBio) {
            return $candidate;
        }

        return false;
    }

    /**
     * Handle case when person is not found locally.
     *
     * @param  string  $slug  Person slug
     * @param  string|null  $bioId  Bio ID (UUID) or null
     */
    private function handlePersonNotFound(string $slug, ?string $bioId): PersonRetrievalResult
    {
        if (! Feature::active('ai_bio_generation')) {
            return PersonRetrievalResult::notFound();
        }

        $validation = SlugValidator::validatePersonSlug($slug);
        if (! $validation['valid']) {
            return PersonRetrievalResult::invalidSlug($slug, $validation);
        }

        return $this->attemptToFindOrCreatePersonFromTmdb($slug, $bioId, $validation);
    }

    /**
     * Attempt to find or create person from TMDB.
     *
     * @param  string  $slug  Person slug
     * @param  string|null  $bioId  Bio ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function attemptToFindOrCreatePersonFromTmdb(string $slug, ?string $bioId, array $validation): PersonRetrievalResult
    {
        // If tmdb_verification is disabled, allow generation without TMDB check
        if (! Feature::active('tmdb_verification')) {
            return $this->queueGenerationWithoutTmdb($slug, $validation);
        }

        $tmdbData = $this->tmdbVerificationService->verifyPerson($slug);

        if ($tmdbData !== null) {
            return $this->handleTmdbExactMatch($tmdbData, $slug, $bioId, $validation);
        }

        return $this->handleTmdbSearch($slug, $bioId, $validation);
    }

    /**
     * Queue generation without TMDB verification (when feature flag is disabled).
     */
    private function queueGenerationWithoutTmdb(string $slug, array $validation): PersonRetrievalResult
    {
        // Check if person already exists locally
        $person = $this->personRepository->findBySlugWithRelations($slug);

        if ($person !== null) {
            // Person exists - check if it has bios
            if ($person->bios()->exists()) {
                $selectedBio = $this->findSelectedBio($person, null);

                return PersonRetrievalResult::found($person, $selectedBio);
            }

            // Person exists but no bios - queue generation
            $generationResult = $this->queuePersonGenerationAction->handle(
                $slug,
                confidence: $validation['confidence'],
                locale: Locale::EN_US->value,
                tmdbData: null
            );

            return PersonRetrievalResult::generationQueued($generationResult);
        }

        // Person doesn't exist - create it from slug and queue generation
        $parsedSlug = Person::parseSlug($slug);
        $person = Person::create([
            'name' => $parsedSlug['name'],
            'slug' => $slug,
            'birth_date' => $parsedSlug['birth_year'] ? sprintf('%d-01-01', $parsedSlug['birth_year']) : null,
            'birthplace' => $parsedSlug['birthplace'] ?? null,
        ]);

        // Invalidate person search cache when new person is created
        $this->invalidatePersonSearchCache();

        $generationResult = $this->queuePersonGenerationAction->handle(
            $slug,
            confidence: $validation['confidence'],
            locale: Locale::EN_US->value,
            tmdbData: null
        );

        return PersonRetrievalResult::generationQueued($generationResult);
    }

    /**
     * Handle exact match found in TMDB.
     */
    private function handleTmdbExactMatch(array $tmdbData, string $slug, ?string $bioId, array $validation): PersonRetrievalResult
    {
        $person = $this->createPersonFromTmdb($tmdbData, $slug);
        $generationResult = $this->queueGenerationForNewPerson($person, $slug, $validation, $tmdbData);

        return PersonRetrievalResult::generationQueued($generationResult);
    }

    /**
     * Handle TMDB search results (multiple or single match).
     */
    private function handleTmdbSearch(string $slug, ?string $bioId, array $validation): PersonRetrievalResult
    {
        $searchResults = $this->tmdbVerificationService->searchPeople($slug, 5);

        if (count($searchResults) > 1) {
            $options = $this->buildDisambiguationOptions($slug, $searchResults);

            return PersonRetrievalResult::disambiguation($slug, $options);
        }

        if (count($searchResults) === 1) {
            return $this->handleSingleTmdbResult($searchResults[0], $slug, $bioId, $validation);
        }

        return PersonRetrievalResult::notFound();
    }

    /**
     * Handle single TMDB search result.
     *
     * @param  array  $tmdbResult  Single TMDB person result
     * @param  string  $slug  Person slug
     * @param  string|null  $bioId  Bio ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleSingleTmdbResult(array $tmdbResult, string $slug, ?string $bioId, array $validation): PersonRetrievalResult
    {
        if ($this->doesBirthYearMatchRequest($tmdbResult, $slug) === false) {
            return new PersonRetrievalResult(
                isNotFound: true,
                errorMessage: $this->buildBirthYearMismatchMessage($tmdbResult, $slug),
                errorCode: 404
            );
        }

        $person = $this->createPersonFromTmdb($tmdbResult, $slug);
        $generationResult = $this->queueGenerationForNewPerson($person, $slug, $validation, $tmdbResult);

        return PersonRetrievalResult::generationQueued($generationResult);
    }

    /**
     * Check if TMDB result birth year matches the year in request slug.
     */
    private function doesBirthYearMatchRequest(array $tmdbResult, string $slug): bool
    {
        $parsedSlug = Person::parseSlug($slug);
        $requestedYear = $parsedSlug['birth_year'];

        if ($requestedYear === null) {
            return true; // No year in request, so any year matches
        }

        $resultYear = ! empty($tmdbResult['birthday'])
            ? (int) substr($tmdbResult['birthday'], 0, 4)
            : null;

        return $resultYear === $requestedYear;
    }

    /**
     * Build error message for birth year mismatch.
     */
    private function buildBirthYearMismatchMessage(array $tmdbResult, string $slug): string
    {
        $parsedSlug = Person::parseSlug($slug);
        $requestedYear = $parsedSlug['birth_year'];
        $resultYear = ! empty($tmdbResult['birthday'])
            ? (int) substr($tmdbResult['birthday'], 0, 4)
            : null;

        Log::info('PersonRetrievalService: search result birth year does not match requested year', [
            'slug' => $slug,
            'requested_year' => $requestedYear,
            'result_year' => $resultYear,
            'result_name' => $tmdbResult['name'] ?? null,
        ]);

        return "No person found matching '{$slug}'. Found '{$tmdbResult['name']}' ({$resultYear}) but requested year was {$requestedYear}.";
    }

    /**
     * Create person from TMDB data (metadata only, no bio).
     * Bio should be generated asynchronously via queue job.
     *
     * @param  array{name: string, birthday?: string|null, place_of_birth?: string|null, id: int}  $tmdbData
     * @param  string  $requestSlug  Original slug from request
     * @return Person Returns existing person if found, or newly created person
     */
    private function createPersonFromTmdb(array $tmdbData, string $requestSlug): Person
    {
        $name = $tmdbData['name'];
        $birthDate = ! empty($tmdbData['birthday']) ? $tmdbData['birthday'] : null;
        $birthplace = $tmdbData['place_of_birth'] ?? null;
        $tmdbId = $tmdbData['id'];

        // Generate slug from TMDb data
        $generatedSlug = Person::generateSlug($name, $birthDate, $birthplace);

        // Check if person already exists by generated slug
        $existing = $this->personRepository->findBySlugForJob($generatedSlug);
        if ($existing) {
            Log::info('Person already exists by generated slug, returning existing', [
                'request_slug' => $requestSlug,
                'generated_slug' => $generatedSlug,
                'person_id' => $existing->id,
                'tmdb_id' => $tmdbId,
            ]);

            return $existing;
        }

        // Check if person exists by name + birth date (even if slug differs)
        if ($birthDate !== null) {
            $existingByNameBirth = Person::where('name', $name)
                ->where('birth_date', $birthDate)
                ->first();

            if ($existingByNameBirth) {
                Log::info('Person already exists by name+birth_date, returning existing', [
                    'request_slug' => $requestSlug,
                    'generated_slug' => $generatedSlug,
                    'existing_person_id' => $existingByNameBirth->id,
                    'tmdb_id' => $tmdbId,
                ]);

                return $existingByNameBirth;
            }
        }

        // Create person from TMDb data (metadata only, no bio)
        // Bio will be generated asynchronously via queue job
        $person = Person::create([
            'name' => $name,
            'slug' => $generatedSlug,
            'birth_date' => $birthDate,
            'birthplace' => $birthplace,
            'tmdb_id' => $tmdbId,
        ]);

        Log::info('PersonRetrievalService: Created person from TMDb', [
            'person_id' => $person->id,
            'slug' => $generatedSlug,
            'request_slug' => $requestSlug,
            'tmdb_id' => $tmdbId,
        ]);

        // Invalidate person search cache when new person is created
        $this->invalidatePersonSearchCache();

        return $person;
    }

    /**
     * Queue generation job for newly created person.
     *
     * @return array<string, mixed> Generation result
     */
    private function queueGenerationForNewPerson(Person $person, string $slug, array $validation, array $tmdbData): array
    {
        return $this->queuePersonGenerationAction->handle(
            $person->slug,
            confidence: $validation['confidence'],
            existingPerson: $person,
            locale: Locale::EN_US->value,
            tmdbData: $tmdbData
        );
    }

    /**
     * Build disambiguation options from TMDB search results.
     *
     * @param  array<int, array<string, mixed>>  $searchResults
     * @return array<int, array<string, mixed>>
     */
    private function buildDisambiguationOptions(string $slug, array $searchResults): array
    {
        return array_map(function ($result) {
            $birthDate = $result['birthday'] ?? null;
            $birthYear = ! empty($birthDate) ? substr($birthDate, 0, 4) : null;
            $birthplace = $result['place_of_birth'] ?? null;
            $suggestedSlug = Person::generateSlug($result['name'], $birthDate, $birthplace);

            return [
                'slug' => $suggestedSlug,
                'name' => $result['name'],
                'birth_year' => $birthYear ? (int) $birthYear : null,
                'birthplace' => $birthplace,
                'biography' => substr($result['biography'] ?? '', 0, 200).(strlen($result['biography'] ?? '') > 200 ? '...' : ''),
                'select_url' => url("/api/v1/people/{$suggestedSlug}"),
            ];
        }, $searchResults);
    }

    /**
     * Generate cache key for person.
     *
     * @param  string  $slug  Person slug
     * @param  string|null  $bioId  Bio ID (UUID) or null
     * @return string Cache key
     */
    private function generateCacheKey(string $slug, ?string $bioId): string
    {
        $suffix = $bioId !== null ? 'bio:'.$bioId : 'bio:default';

        return 'person:'.$slug.':'.$suffix;
    }

    /**
     * Invalidate person search cache when a new person is created.
     * Uses tagged cache if supported, otherwise clears all search cache keys.
     */
    private function invalidatePersonSearchCache(): void
    {
        try {
            // Try tagged cache invalidation (works with Redis, Memcached, DynamoDB)
            Cache::tags(['person_search'])->flush();
            Log::debug('PersonRetrievalService: invalidated tagged cache after person creation');
        } catch (\BadMethodCallException $e) {
            // Fallback: For database/file cache, we can't easily invalidate by tag
            // Cache will expire naturally after TTL (1 hour)
            // In production, consider using Redis for better cache control
            Log::debug('PersonRetrievalService: tagged cache not supported, cache will expire naturally', [
                'driver' => config('cache.default'),
            ]);
        }
    }
}
