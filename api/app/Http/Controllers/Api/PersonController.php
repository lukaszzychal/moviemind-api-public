<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueuePersonGenerationAction;
use App\Enums\Locale;
use App\Helpers\SlugValidator;
use App\Http\Controllers\Controller;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Models\PersonBio;
use App\Repositories\PersonRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\HateoasService;
use App\Services\PersonDisambiguationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;

class PersonController extends Controller
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly HateoasService $hateoas,
        private readonly QueuePersonGenerationAction $queuePersonGenerationAction,
        private readonly PersonDisambiguationService $disambiguationService,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $people = $this->personRepository->searchPeople($q, 50);
        $data = $people->map(fn ($person) => $this->transformPerson($person));

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $bioId = $this->normalizeBioId($request->query('bio_id'));
        if ($bioId === false) {
            return response()->json([
                'error' => 'Invalid bio_id parameter',
            ], 422);
        }

        $cacheKey = $this->cacheKey($slug, $bioId);

        if ($cached = Cache::get($cacheKey)) {
            return response()->json($cached);
        }

        $person = $this->personRepository->findBySlugWithRelations($slug);
        if ($person) {
            $selectedBio = null;
            if ($bioId !== null) {
                $candidate = $person->bios->firstWhere('id', $bioId);
                if ($candidate instanceof PersonBio) {
                    $selectedBio = $candidate;
                }

                if ($selectedBio === null) {
                    return response()->json(['error' => 'Bio not found for person'], 404);
                }
            }

            $resource = PersonResource::make($person)
                ->additional(['_links' => $this->hateoas->personLinks($person)]);

            $payload = $resource->resolve($request);

            if ($selectedBio) {
                $payload['selected_bio'] = $selectedBio->toArray();
            }

            // Add disambiguation metadata if ambiguous slug
            $meta = $this->disambiguationService->determineMeta($person, $slug);
            if ($meta !== null) {
                $payload['_meta'] = $meta;
            }

            Cache::put($cacheKey, $payload, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return response()->json($payload);
        }

        if (! Feature::active('ai_bio_generation')) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        // Validate slug format and check for prompt injection
        $validation = SlugValidator::validatePersonSlug($slug);
        if (! $validation['valid']) {
            return response()->json([
                'error' => 'Invalid slug format',
                'message' => $validation['reason'],
                'confidence' => $validation['confidence'],
                'slug' => $slug,
            ], 400);
        }

        // Check for disambiguation request
        $tmdbId = $request->query('tmdb_id');
        if ($tmdbId !== null) {
            return $this->handleDisambiguationSelection($slug, (int) $tmdbId);
        }

        // Verify person exists in TMDb before queueing job (if feature flag enabled)
        $tmdbData = $this->tmdbVerificationService->verifyPerson($slug);
        if (! $tmdbData) {
            // If TMDb verification is disabled (feature flag off), allow generation without TMDb data
            if (! Feature::active('tmdb_verification')) {
                $result = $this->queuePersonGenerationAction->handle(
                    $slug,
                    confidence: $validation['confidence'],
                    locale: Locale::EN_US->value,
                    tmdbData: null
                );

                return response()->json($result, 202);
            }

            // Check if there are multiple matches (disambiguation needed)
            $searchResults = $this->tmdbVerificationService->searchPeople($slug, 5);
            if (count($searchResults) > 1) {
                return $this->respondWithDisambiguation($slug, $searchResults);
            }

            // If search found results but verifyPerson didn't, return suggested slugs
            if (count($searchResults) === 1) {
                $suggestedSlugs = $this->generateSuggestedSlugsFromSearchResults($searchResults);
                $result = $this->queuePersonGenerationAction->handle(
                    $slug,
                    confidence: $validation['confidence'],
                    locale: Locale::EN_US->value,
                    tmdbData: null
                );
                $result['suggested_slugs'] = $suggestedSlugs;

                return response()->json($result, 202);
            }

            return response()->json(['error' => 'Person not found'], 404);
        }

        $result = $this->queuePersonGenerationAction->handle(
            $slug,
            confidence: $validation['confidence'],
            locale: Locale::EN_US->value,
            tmdbData: $tmdbData
        );

        return response()->json($result, 202);
    }

    private function transformPerson(Person $person): array
    {
        $resource = PersonResource::make($person)->additional([
            '_links' => $this->hateoas->personLinks($person),
        ]);

        return $resource->resolve();
    }

    private function cacheKey(string $slug, ?int $bioId = null): string
    {
        $suffix = $bioId !== null ? 'bio:'.$bioId : 'bio:default';

        return 'person:'.$slug.':'.$suffix;
    }

    private function normalizeBioId(mixed $bioId): null|int|false
    {
        if ($bioId === null || $bioId === '') {
            return null;
        }

        if (filter_var($bioId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return false;
        }

        return (int) $bioId;
    }

    /**
     * Handle disambiguation selection when user chooses specific person from TMDb results.
     */
    private function handleDisambiguationSelection(string $slug, int $tmdbId): JsonResponse
    {
        // Search for people and find the one matching tmdb_id
        $searchResults = $this->tmdbVerificationService->searchPeople($slug, 10);
        $selectedPerson = null;

        foreach ($searchResults as $result) {
            if ($result['id'] === $tmdbId) {
                $selectedPerson = $result;
                break;
            }
        }

        if (! $selectedPerson) {
            return response()->json(['error' => 'Selected person not found in search results'], 404);
        }

        // Re-validate slug for confidence score
        $validation = SlugValidator::validatePersonSlug($slug);
        $result = $this->queuePersonGenerationAction->handle(
            $slug,
            confidence: $validation['confidence'],
            locale: Locale::EN_US->value,
            tmdbData: $selectedPerson
        );

        return response()->json($result, 202);
    }

    /**
     * Respond with disambiguation options when multiple people match the slug.
     */
    private function respondWithDisambiguation(string $slug, array $searchResults): JsonResponse
    {
        $options = array_map(function ($result) use ($slug) {
            $birthYear = ! empty($result['birthday']) ? substr($result['birthday'], 0, 4) : null;
            $birthplace = $result['place_of_birth'] ?? null;

            return [
                'tmdb_id' => $result['id'],
                'name' => $result['name'],
                'birth_year' => $birthYear,
                'birthplace' => $birthplace,
                'biography' => substr($result['biography'] ?? '', 0, 200).(strlen($result['biography'] ?? '') > 200 ? '...' : ''),
                'select_url' => url("/api/v1/people/{$slug}?tmdb_id={$result['id']}"),
            ];
        }, $searchResults);

        return response()->json([
            'error' => 'Multiple people found',
            'message' => 'Multiple people match this slug. Please select one:',
            'slug' => $slug,
            'options' => $options,
            'count' => count($options),
        ], 300); // 300 Multiple Choices
    }

    /**
     * Generate suggested slugs from TMDb search results.
     *
     * @param  array<int, array{name: string, birthday?: string, place_of_birth?: string, id: int}>  $searchResults
     * @return array<int, array{slug: string, name: string, birth_year: int|null, birthplace: string|null, tmdb_id: int}>
     */
    private function generateSuggestedSlugsFromSearchResults(array $searchResults): array
    {
        $suggestedSlugs = [];
        foreach ($searchResults as $result) {
            $name = $result['name'];
            if (empty($name)) {
                continue;
            }
            $birthDate = $result['birthday'] ?? null;
            $birthYear = ! empty($birthDate) ? (int) substr($birthDate, 0, 4) : null;
            $birthplace = $result['place_of_birth'] ?? null;

            $suggestedSlugs[] = [
                'slug' => Person::generateSlug($name, $birthDate, $birthplace),
                'name' => $name,
                'birth_year' => $birthYear,
                'birthplace' => $birthplace,
                'tmdb_id' => $result['id'],
            ];
        }

        return $suggestedSlugs;
    }

    /**
     * Refresh person data from TMDb.
     */
    public function refresh(string $slug): JsonResponse
    {
        $person = $this->personRepository->findBySlugWithRelations($slug);
        if (! $person) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        // Find existing snapshot
        $snapshot = \App\Models\TmdbSnapshot::where('entity_type', 'PERSON')
            ->where('entity_id', $person->id)
            ->first();

        if (! $snapshot) {
            return response()->json(['error' => 'No TMDb snapshot found for this person'], 404);
        }

        // Refresh person details from TMDb
        /** @var \App\Services\TmdbVerificationService $tmdbService */
        $tmdbService = $this->tmdbVerificationService;
        $freshData = $tmdbService->refreshPersonDetails($snapshot->tmdb_id);
        if (! $freshData) {
            return response()->json(['error' => 'Failed to refresh person data from TMDb'], 500);
        }

        // Invalidate cache
        Cache::forget($this->cacheKey($slug, null));

        return response()->json([
            'message' => 'Person data refreshed from TMDb',
            'slug' => $slug,
            'person_id' => $person->id,
            'tmdb_id' => $snapshot->tmdb_id,
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }
}
