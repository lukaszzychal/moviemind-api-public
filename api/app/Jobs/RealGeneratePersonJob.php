<?php

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Person;
use App\Models\PersonBio;
use App\Repositories\PersonRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\JobErrorFormatter;
use App\Services\JobStatusService;
use App\Services\OpenAiClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * Real Generate Person Job - calls actual AI API for production.
 * Used when AI_SERVICE=real.
 */
class RealGeneratePersonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120; // Longer timeout for real API calls

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?int $existingPersonId = null,
        public ?int $baselineBioId = null,
        public ?string $locale = null,
        public ?string $contextTag = null,
        public ?array $tmdbData = null
    ) {}

    /**
     * Execute the job.
     * Note: OpenAiClientInterface is injected via method injection.
     * Constructor injection is not possible because Jobs are serialized to queue.
     */
    public function handle(OpenAiClientInterface $openAiClient): void
    {
        Log::info('RealGeneratePersonJob started', [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'attempt' => $this->attempts(),
            'existing_person_id' => $this->existingPersonId,
            'baseline_bio_id' => $this->baselineBioId,
            'locale' => $this->locale,
            'context_tag' => $this->contextTag,
            'pid' => getmypid(),
        ]);
        /** @var JobStatusService $jobStatusService */
        $jobStatusService = app(JobStatusService::class);
        /** @var PersonRepository $personRepository */
        $personRepository = app(PersonRepository::class);

        try {
            $existing = $personRepository->findBySlugForJob($this->slug, $this->existingPersonId);

            if ($existing) {
                $this->refreshExistingPerson($existing, $openAiClient);
                Log::info('RealGeneratePersonJob refreshed existing person', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'person_id' => $existing->id,
                ]);

                return;
            }

            try {
                $result = $this->createPersonRecord($openAiClient);

                // If result is null, suggested slugs were found and error was already handled
                if ($result === null) {
                    return;
                }

                [$person, $bio, $localeValue, $contextTag] = $result;
                $this->promoteDefaultIfEligible($person, $bio);
                $this->invalidatePersonCaches($person);
                $this->updateCache('DONE', $person->id, $bio->id, $person->slug, $localeValue, $contextTag);
                Log::info('RealGeneratePersonJob created new person', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'person_id' => $person->id,
                    'bio_id' => $bio->id,
                    'locale' => $localeValue,
                    'context_tag' => $contextTag,
                    'pid' => getmypid(),
                ]);
            } catch (QueryException $exception) {
                if ($this->isUniquePersonSlugViolation($exception)) {
                    $existingAfterViolation = $personRepository->findBySlugForJob($this->slug, $this->existingPersonId);
                    if ($existingAfterViolation) {
                        Log::info('RealGeneratePersonJob detected concurrent creation - using existing person', [
                            'slug' => $this->slug,
                            'job_id' => $this->jobId,
                            'person_id' => $existingAfterViolation->id,
                            'pid' => getmypid(),
                        ]);
                        $this->markDoneUsingExisting($existingAfterViolation);

                        return;
                    }

                    Log::warning('RealGeneratePersonJob unique slug violation without person record', [
                        'slug' => $this->slug,
                        'job_id' => $this->jobId,
                        'pid' => getmypid(),
                        'error' => $exception->getMessage(),
                    ]);
                }

                throw $exception;
            }
        } catch (\Throwable $e) {
            Log::error('RealGeneratePersonJob failed', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            /** @var JobErrorFormatter $errorFormatter */
            $errorFormatter = app(JobErrorFormatter::class);
            $errorData = $errorFormatter->formatError($e, $this->slug, 'PERSON');
            $this->updateCache('FAILED', error: $errorData);

            throw $e; // Re-throw for retry mechanism
        } finally {
            $jobStatusService->releaseGenerationSlot('PERSON', $this->slug, $this->locale, $this->contextTag);
        }
    }

    /**
     * Configure retry backoff tuned for OpenAI free-tier limits.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        if (! config('services.openai.backoff.enabled', true)) {
            return [];
        }

        $intervals = config('services.openai.backoff.intervals', []);

        return ! empty($intervals) ? $intervals : [20, 60, 180];
    }

    private function refreshExistingPerson(Person $person, OpenAiClientInterface $openAiClient): void
    {
        $person->loadMissing('bios');
        $aiResponse = $openAiClient->generatePerson($this->slug, $this->tmdbData);

        if ($aiResponse['success'] === false) {
            $error = $aiResponse['error'] ?? 'Unknown error';

            // Check if it's a "not found" error from AI
            if (stripos($error, 'not found') !== false) {
                // Try to find suggested slugs
                $suggestedSlugs = $this->findSuggestedSlugs();

                if (! empty($suggestedSlugs)) {
                    Log::info('Person not found by AI during refresh, but found suggested slugs in TMDb', [
                        'slug' => $this->slug,
                        'job_id' => $this->jobId,
                        'suggestions_count' => count($suggestedSlugs),
                    ]);

                    // Format error with suggested slugs
                    $errorFormatter = app(JobErrorFormatter::class);
                    $errorData = $errorFormatter->formatError(
                        new \RuntimeException("Person not found: {$this->slug}"),
                        $this->slug,
                        'PERSON',
                        $suggestedSlugs
                    );

                    $this->updateCache('FAILED', error: $errorData);

                    return; // Don't throw exception, just end the job with error
                }

                Log::warning('Person not found by AI during refresh', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                ]);

                throw new \RuntimeException("Person not found: {$this->slug}");
            }

            throw new \RuntimeException('AI API returned error: '.$error);
        }

        // Validate AI response data consistency with slug (during refresh)
        if (Feature::active('hallucination_guard')) {
            $validator = app(\App\Services\AiDataValidator::class);
            $validation = $validator->validatePersonData($aiResponse, $this->slug);

            if (! $validation['valid']) {
                Log::warning('AI data validation failed for person during refresh', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'person_id' => $person->id,
                    'errors' => $validation['errors'],
                    'similarity' => $validation['similarity'],
                ]);

                throw new \RuntimeException(
                    'AI data validation failed: '.implode(', ', $validation['errors'])
                );
            }
        }

        $biography = $aiResponse['biography']
            ?? sprintf('Regenerated biography for %s via RealGeneratePersonJob.', $person->name);

        $locale = $this->resolveLocale();
        $baselineLockingActive = $this->baselineLockingEnabled();
        $willUpdateBaseline = $this->shouldUpdateBaseline($person, $locale);

        if ($baselineLockingActive) {
            Log::info('Baseline locking active for person generation', [
                'job_id' => $this->jobId,
                'slug' => $this->slug,
                'person_id' => $person->id,
                'baseline_bio_id' => $this->baselineBioId,
                'will_update_baseline' => $willUpdateBaseline,
                'locale' => $locale->value,
                'context_tag' => $this->contextTag,
            ]);
        }

        $bio = $willUpdateBaseline
            ? $this->updateBaselineBio($person, $locale, [
                'text' => (string) $biography,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
            ])
            : $this->persistBio(
                $person,
                $locale,
                $this->determineContextTag($person, $locale),
                [
                    'text' => (string) $biography,
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
                ]
            );

        if ($baselineLockingActive) {
            Log::info('Baseline locking result for person generation', [
                'job_id' => $this->jobId,
                'slug' => $this->slug,
                'person_id' => $person->id,
                'bio_id' => $bio->id,
                'result' => $willUpdateBaseline ? 'baseline_updated' : 'alternative_appended',
                'locale' => $locale->value,
                'context_tag' => $bio->context_tag instanceof ContextTag ? $bio->context_tag->value : (string) $bio->context_tag,
            ]);
        }

        $this->promoteDefaultIfEligible($person, $bio);
        $this->invalidatePersonCaches($person);
        $contextForCache = $bio->context_tag instanceof ContextTag ? $bio->context_tag->value : (string) $bio->context_tag;
        $this->updateCache('DONE', $person->id, $bio->id, $person->slug, $locale->value, $contextForCache);
    }

    private function updateCache(
        string $status,
        ?int $id = null,
        ?int $bioId = null,
        ?string $slug = null,
        ?string $locale = null,
        ?string $contextTag = null,
        ?array $error = null
    ): void {
        $payload = [
            'job_id' => $this->jobId,
            'status' => $status,
            'entity' => 'PERSON',
            'slug' => $slug ?? $this->slug,
            'requested_slug' => $this->slug,
            'id' => $id,
            'bio_id' => $bioId,
            'locale' => $locale ?? $this->locale,
            'context_tag' => $contextTag ?? $this->contextTag,
        ];

        if ($error !== null) {
            $payload['error'] = $error;
        }

        Cache::put($this->cacheKey(), $payload, now()->addMinutes(15));
    }

    private function nextContextTag(Person $person): string
    {
        $existingTags = array_map(
            fn ($tag) => $tag instanceof ContextTag ? $tag->value : (string) $tag,
            $person->bios()->pluck('context_tag')->all()
        );
        $preferredOrder = [
            ContextTag::DEFAULT->value,
            ContextTag::MODERN->value,
            ContextTag::CRITICAL->value,
            ContextTag::HUMOROUS->value,
        ];

        foreach ($preferredOrder as $candidate) {
            if (! in_array($candidate, $existingTags, true)) {
                return $candidate;
            }
        }

        $suffix = 2;
        do {
            $candidate = ContextTag::DEFAULT->value.'_'.$suffix;
            $suffix++;
        } while (in_array($candidate, $existingTags, true));

        return $candidate;
    }

    private function cacheKey(): string
    {
        return 'ai_job:'.$this->jobId;
    }

    private function resolveLocale(): Locale
    {
        if ($this->locale) {
            $normalized = $this->normalizeLocale($this->locale);
            if ($normalized !== null && ($enum = Locale::tryFrom($normalized))) {
                return $enum;
            }
        }

        return Locale::EN_US;
    }

    private function normalizeLocale(string $locale): ?string
    {
        $candidate = str_replace('_', '-', $locale);
        $candidateLower = strtolower($candidate);

        foreach (Locale::cases() as $case) {
            if (strtolower($case->value) === $candidateLower) {
                return $case->value;
            }
        }

        return null;
    }

    private function determineContextTag(Person $person, Locale $locale): string
    {
        if ($this->contextTag !== null) {
            $normalized = $this->normalizeContextTag($this->contextTag);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $this->nextContextTag($person);
    }

    private function normalizeContextTag(string $contextTag): ?string
    {
        $candidateLower = strtolower($contextTag);

        foreach (ContextTag::cases() as $case) {
            if (strtolower($case->value) === $candidateLower) {
                return $case->value;
            }
        }

        return null;
    }

    private function persistBio(Person $person, Locale $locale, string $contextTag, array $attributes): PersonBio
    {
        $existing = PersonBio::where('person_id', $person->id)
            ->where('locale', $locale->value)
            ->where('context_tag', $contextTag)
            ->first();

        if ($existing) {
            $existing->fill($attributes);
            $existing->save();

            return $existing->fresh();
        }

        return PersonBio::create(array_merge([
            'person_id' => $person->id,
            'locale' => $locale->value,
            'context_tag' => $contextTag,
        ], $attributes));
    }

    private function shouldUpdateBaseline(Person $person, Locale $locale): bool
    {
        if (! $this->baselineLockingEnabled() || $this->baselineBioId === null || $this->contextTag !== null) {
            return false;
        }

        $baseline = $this->getBaselineBio($person);

        if (! $baseline instanceof PersonBio) {
            return false;
        }

        if ($this->locale !== null && strtolower($baseline->locale->value) !== strtolower($locale->value)) {
            return false;
        }

        return true;
    }

    private function getBaselineBio(Person $person): ?PersonBio
    {
        $bio = $person->bios->firstWhere('id', $this->baselineBioId);

        return $bio instanceof PersonBio ? $bio : PersonBio::find($this->baselineBioId);
    }

    private function updateBaselineBio(Person $person, Locale $locale, array $attributes): PersonBio
    {
        $baseline = $this->getBaselineBio($person);

        if (! $baseline instanceof PersonBio) {
            return $this->persistBio($person, $locale, $this->determineContextTag($person, $locale), $attributes);
        }

        $baseline->fill(array_merge($attributes, [
            'locale' => $locale->value,
        ]));
        $baseline->save();

        return $baseline->fresh();
    }

    public function failed(\Throwable $exception): void
    {
        /** @var JobStatusService $jobStatusService */
        $jobStatusService = app(JobStatusService::class);
        $jobStatusService->releaseGenerationSlot('PERSON', $this->slug, $this->locale, $this->contextTag);

        Log::error('RealGeneratePersonJob permanently failed', [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ]);

        /** @var JobErrorFormatter $errorFormatter */
        $errorFormatter = app(JobErrorFormatter::class);
        $errorData = $errorFormatter->formatError($exception, $this->slug, 'PERSON');
        $this->updateCache('FAILED', error: $errorData);
    }

    /**
     * @return array{0: Person, 1: PersonBio, 2: string, 3: string}
     */
    /**
     * @return array{0: Person, 1: PersonBio, 2: string, 3: string}|null Returns null if suggested slugs were found (error already handled)
     */
    private function createPersonRecord(OpenAiClientInterface $openAiClient): ?array
    {
        // Pre-generation validation (before calling AI)
        if (Feature::active('hallucination_guard')) {
            $preValidator = app(\App\Services\PreGenerationValidator::class);
            $preValidation = $preValidator->shouldGeneratePerson($this->slug);

            if (! $preValidation['should_generate']) {
                Log::warning('Pre-generation validation failed for person', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'reason' => $preValidation['reason'],
                    'confidence' => $preValidation['confidence'] ?? null,
                ]);

                throw new \RuntimeException(
                    "Pre-generation validation failed: {$preValidation['reason']}"
                );
            }
        }

        $aiResponse = $openAiClient->generatePerson($this->slug, $this->tmdbData);

        if ($aiResponse['success'] === false) {
            $error = $aiResponse['error'] ?? 'Unknown error';

            // Check if it's a "not found" error from AI
            if (stripos($error, 'not found') !== false) {
                // Try to find suggested slugs
                $suggestedSlugs = $this->findSuggestedSlugs();

                if (! empty($suggestedSlugs)) {
                    Log::info('Person not found by AI, but found suggested slugs in TMDb', [
                        'slug' => $this->slug,
                        'job_id' => $this->jobId,
                        'suggestions_count' => count($suggestedSlugs),
                    ]);

                    // Format error with suggested slugs
                    $errorFormatter = app(JobErrorFormatter::class);
                    $errorData = $errorFormatter->formatError(
                        new \RuntimeException("Person not found: {$this->slug}"),
                        $this->slug,
                        'PERSON',
                        $suggestedSlugs
                    );

                    $this->updateCache('FAILED', error: $errorData);

                    return null; // Don't throw exception, just end the job with error
                }

                Log::warning('Person not found by AI and no suggestions available', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                ]);

                throw new \RuntimeException("Person not found: {$this->slug}");
            }

            throw new \RuntimeException('AI API returned error: '.$error);
        }

        // Validate AI response data consistency with slug
        if (Feature::active('hallucination_guard')) {
            $validator = app(\App\Services\AiDataValidator::class);
            $validation = $validator->validatePersonData($aiResponse, $this->slug);

            if (! $validation['valid']) {
                Log::warning('AI data validation failed for person', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'errors' => $validation['errors'],
                    'similarity' => $validation['similarity'],
                    'ai_response' => $aiResponse,
                ]);

                throw new \RuntimeException(
                    'AI data validation failed: '.implode(', ', $validation['errors'])
                );
            }
        }

        $name = $aiResponse['name'] ?? Str::of($this->slug)->replace('-', ' ')->title();
        $birthDate = $aiResponse['birth_date'] ?? '1970-01-01';
        $birthplace = $aiResponse['birthplace'] ?? 'Unknown';
        $biography = $aiResponse['biography'] ?? "Biography for {$name}.";

        // Generate unique slug from AI data instead of using slug from request
        // This ensures uniqueness and prevents conflicts with ambiguous slugs
        // Following DIP: use Person model method, not direct slug from request
        $generatedSlug = Person::generateSlug((string) $name, $birthDate, $birthplace);

        // Check if person already exists with generated slug (prevent duplicates)
        // This handles race conditions where multiple jobs create the same person
        /** @var PersonRepository $personRepository */
        $personRepository = app(PersonRepository::class);
        $existingByGeneratedSlug = $personRepository->findBySlugForJob($generatedSlug);
        if ($existingByGeneratedSlug) {
            // Person already exists with generated slug - use it
            $person = $existingByGeneratedSlug;
        } else {
            // Check if person exists by name + birth date (even if slug differs)
            // This prevents duplicates when slug format differs
            $existingByNameBirth = Person::where('name', (string) $name)
                ->where('birth_date', $birthDate)
                ->first();

            if ($existingByNameBirth) {
                // Person already exists - use it
                $person = $existingByNameBirth;
            } else {
                // Create new person
                $person = Person::create([
                    'name' => (string) $name,
                    'slug' => $generatedSlug,
                    'birth_date' => $birthDate,
                    'birthplace' => $birthplace,
                ]);
            }
        }

        $locale = $this->resolveLocale();
        $bio = $this->shouldUpdateBaseline($person, $locale)
            ? $this->updateBaselineBio($person, $locale, [
                'text' => (string) $biography,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
            ])
            : $this->persistBio(
                $person,
                $locale,
                $this->determineContextTag($person, $locale),
                [
                    'text' => (string) $biography,
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
                ]
            );

        $contextForCache = $bio->context_tag instanceof ContextTag ? $bio->context_tag->value : (string) $bio->context_tag;

        return [$person->fresh(['bios']), $bio, $locale->value, $contextForCache];
    }

    private function promoteDefaultIfEligible(Person $person, PersonBio $bio): void
    {
        $lock = Cache::lock($this->defaultLockKey($person), 15);

        try {
            $lock->block(5, function () use ($person, $bio): void {
                $person->refresh();
                $currentDefault = $person->default_bio_id;

                if ($this->baselineBioId !== null) {
                    if ((int) $currentDefault !== $this->baselineBioId) {
                        return;
                    }
                } elseif ($currentDefault !== null) {
                    return;
                }

                $person->default_bio_id = $bio->id;
                $person->save();
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('RealGeneratePersonJob default promotion lock timeout', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'person_id' => $person->id,
            ]);
        }
    }

    private function defaultLockKey(Person $person): string
    {
        return 'lock:person:default:'.$person->id;
    }

    private function invalidatePersonCaches(Person $person): void
    {
        $slugs = array_unique(array_filter([
            $this->slug,
            $person->slug,
        ]));

        $bioIds = $person->bios()->pluck('id')->all();

        foreach ($slugs as $slug) {
            Cache::forget('person:'.$slug.':bio:default');

            foreach ($bioIds as $bioId) {
                Cache::forget('person:'.$slug.':bio:'.$bioId);
            }
        }
    }

    private function baselineLockingEnabled(): bool
    {
        return Feature::active('ai_generation_baseline_locking');
    }

    private function isUniquePersonSlugViolation(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? null;
        $sqlState = is_array($errorInfo) && isset($errorInfo[0])
            ? (string) $errorInfo[0]
            : (string) $exception->getCode();

        if (! in_array($sqlState, ['23000', '23505'], true)) {
            return false;
        }

        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'people.slug') || str_contains($message, 'people_slug_unique')) {
            return true;
        }

        if (is_array($errorInfo) && isset($errorInfo[2])) {
            $details = strtolower((string) $errorInfo[2]);
            if (str_contains($details, 'people.slug') || str_contains($details, 'people_slug_unique')) {
                return true;
            }
        }

        return false;
    }

    private function markDoneUsingExisting(Person $person): void
    {
        $person->loadMissing(['bios', 'defaultBio']);
        /** @var PersonBio|null $bio */
        $bio = $person->defaultBio ?? $person->bios->first();

        $resolvedLocale = is_string($this->locale) ? $this->locale : null;
        $resolvedContextTag = $this->contextTag;

        if ($bio instanceof PersonBio) {
            /** @var mixed $bioLocale */
            $bioLocale = $bio->locale;
            if (is_string($bioLocale)) {
                $resolvedLocale = $bioLocale;
            }

            /** @var mixed $bioContext */
            $bioContext = $bio->context_tag;
            if ($bioContext instanceof ContextTag) {
                $resolvedContextTag = $bioContext->value;
            } else {
                $resolvedContextTag = (string) $bioContext;
            }
        }

        $bioId = $bio instanceof PersonBio ? $bio->id : null;

        $this->updateCache(
            'DONE',
            $person->id,
            $bioId,
            $person->slug,
            $resolvedLocale,
            $resolvedContextTag
        );
    }

    /**
     * Find suggested slugs from TMDb when person is not found.
     * Searches TMDb for people matching the slug and generates suggested slugs.
     *
     * @return array<int, array{slug: string, name: string, tmdb_id: int}>|null
     */
    private function findSuggestedSlugs(): ?array
    {
        // Only search if TMDb verification is enabled
        if (! Feature::active('tmdb_verification')) {
            return null;
        }

        try {
            /** @var EntityVerificationServiceInterface $tmdbService */
            $tmdbService = app(EntityVerificationServiceInterface::class);
            $searchResults = $tmdbService->searchPeople($this->slug, 5);

            if (empty($searchResults)) {
                return null;
            }

            // Generate suggested slugs from TMDb results
            $suggestedSlugs = [];
            foreach ($searchResults as $result) {
                $name = $result['name'];
                if (empty($name)) {
                    continue;
                }

                // Generate slug using Person model method
                $suggestedSlug = Person::generateSlug($name);

                $suggestedSlugs[] = [
                    'slug' => $suggestedSlug,
                    'name' => $name,
                    'tmdb_id' => $result['id'],
                ];
            }

            return $suggestedSlugs;
        } catch (\Throwable $e) {
            Log::warning('Failed to find suggested slugs from TMDb', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
