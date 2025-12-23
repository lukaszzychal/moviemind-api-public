<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueuePersonGenerationAction;
use App\Enums\Locale;
use App\Helpers\SlugValidator;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportPersonRequest;
use App\Http\Requests\SearchPersonRequest;
use App\Http\Resources\PersonResource;
use App\Http\Responses\PersonResponseFormatter;
use App\Models\Person;
use App\Models\PersonReport;
use App\Repositories\PersonRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\HateoasService;
use App\Services\PersonReportService;
use App\Services\PersonRetrievalService;
use App\Services\PersonSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PersonController extends Controller
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly HateoasService $hateoas,
        private readonly QueuePersonGenerationAction $queuePersonGenerationAction,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService,
        private readonly PersonSearchService $personSearchService,
        private readonly PersonRetrievalService $personRetrievalService,
        private readonly PersonResponseFormatter $responseFormatter,
        private readonly PersonReportService $personReportService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $people = $this->personRepository->searchPeople($q, 50);
        $data = $people->map(fn ($person) => $this->transformPerson($person));

        return response()->json(['data' => $data]);
    }

    public function search(SearchPersonRequest $request): JsonResponse
    {
        $criteria = $request->getSearchCriteria();
        $searchResult = $this->personSearchService->search($criteria);

        // For search endpoint, always return 200 with normal structure (even if ambiguous)
        return response()->json($searchResult->toArray(), 200);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $bioId = $this->normalizeBioId($request->query('bio_id'));
        if ($bioId === false) {
            return $this->responseFormatter->formatError('Invalid bio_id parameter', 422);
        }

        // Handle disambiguation selection (special case - user selects specific slug from disambiguation)
        // Note: Disambiguation now uses slugs instead of tmdb_id
        $selectedSlug = $request->query('slug');
        if ($selectedSlug !== null) {
            return $this->handleDisambiguationSelection($slug, (string) $selectedSlug);
        }

        $result = $this->personRetrievalService->retrievePerson($slug, $bioId);

        $response = $this->responseFormatter->formatFromResult($result, $slug);

        // Cache successful responses (but not disambiguation - they should be fresh)
        if ($result->isFound() && ! $result->isCached() && ! $result->isDisambiguation()) {
            $cacheKey = $this->cacheKey($slug, $bioId);
            $responseData = json_decode($response->getContent(), true);
            Cache::put($cacheKey, $responseData, now()->addSeconds(self::CACHE_TTL_SECONDS));
        }

        return $response;
    }

    private function transformPerson(Person $person): array
    {
        $resource = PersonResource::make($person)->additional([
            '_links' => $this->hateoas->personLinks($person),
        ]);

        return $resource->resolve();
    }

    /**
     * Generate cache key for person response.
     *
     * @param  string  $slug  Person slug
     * @param  string|null  $bioId  Bio ID (UUID) or null
     * @return string Cache key
     */
    private function cacheKey(string $slug, ?string $bioId = null): string
    {
        $suffix = $bioId !== null ? 'bio:'.$bioId : 'bio:default';

        return 'person:'.$slug.':'.$suffix;
    }

    /**
     * Normalize bio ID from request (UUID string or null).
     *
     * @param  mixed  $bioId  Bio ID from query parameter (UUID string or null)
     * @return null|string|false Returns UUID string, null if not provided, or false if invalid
     */
    private function normalizeBioId(mixed $bioId): null|string|false
    {
        if ($bioId === null || $bioId === '') {
            return null;
        }

        $bioId = (string) $bioId;

        // Validate UUID format (UUIDv7 format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $bioId)) {
            return false;
        }

        return $bioId;
    }

    /**
     * Handle disambiguation selection when user chooses specific person by slug.
     * This method is called when user selects a slug from disambiguation options.
     */
    private function handleDisambiguationSelection(string $originalSlug, string $selectedSlug): JsonResponse
    {
        // Find person by selected slug
        $person = $this->personRepository->findBySlugWithRelations($selectedSlug);

        if (! $person) {
            // Person doesn't exist yet - need to find it in TMDb and create it
            // Search for people matching the original slug
            $searchResults = $this->tmdbVerificationService->searchPeople($originalSlug, 10);

            // Find the one that matches the selected slug
            $selectedPerson = null;
            foreach ($searchResults as $result) {
                $birthDate = $result['birthday'] ?? null;
                $birthplace = $result['place_of_birth'] ?? null;
                $generatedSlug = Person::generateSlug($result['name'], $birthDate, $birthplace);

                if ($generatedSlug === $selectedSlug) {
                    $selectedPerson = $result;
                    break;
                }
            }

            if (! $selectedPerson) {
                return $this->responseFormatter->formatDisambiguationSelectionNotFound();
            }

            // Re-validate slug for confidence score
            $validation = SlugValidator::validatePersonSlug($selectedSlug);
            $result = $this->queuePersonGenerationAction->handle(
                $selectedSlug,
                confidence: $validation['confidence'],
                locale: Locale::EN_US->value,
                tmdbData: $selectedPerson
            );

            return $this->responseFormatter->formatGenerationQueued($result);
        }

        // Person exists - return it directly by calling show method with a new request
        $request = Request::create("/api/v1/people/{$selectedSlug}", 'GET');

        return $this->show($request, $selectedSlug);
    }

    /**
     * Respond with disambiguation options when multiple people match the slug.
     *
     * @phpstan-ignore-next-line
     */
    private function respondWithDisambiguation(string $slug, array $searchResults): JsonResponse
    {
        $options = array_map(function ($result) {
            $birthDate = $result['birthday'] ?? null;
            $birthplace = $result['place_of_birth'] ?? null;
            $birthYear = ! empty($birthDate) ? substr($birthDate, 0, 4) : null;
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

        return response()->json([
            'error' => 'Multiple people found',
            'message' => 'Multiple people match this slug. Please select one:',
            'slug' => $slug,
            'options' => $options,
            'count' => count($options),
            'hint' => 'Use the slug from options to access specific person (e.g., GET /api/v1/people/{slug})',
        ], 300); // 300 Multiple Choices
    }

    /**
     * Generate suggested slugs from TMDb search results.
     *
     * @param  array<int, array{name: string, birthday?: string, place_of_birth?: string, id: int}>  $searchResults
     * @return array<int, array{slug: string, name: string, birth_year: int|null, birthplace: string|null}>
     *
     * @phpstan-ignore-next-line
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
            ];
        }

        return $suggestedSlugs;
    }

    /**
     * Report an issue with a person or their bio.
     */
    public function report(ReportPersonRequest $request, string $slug): JsonResponse
    {
        $person = $this->personRepository->findBySlugWithRelations($slug);

        if ($person === null) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        $validated = $request->validated();

        // Create report
        $report = PersonReport::create([
            'person_id' => $person->id,
            'bio_id' => $validated['bio_id'] ?? null,
            'type' => $validated['type'],
            'message' => $validated['message'],
            'suggested_fix' => $validated['suggested_fix'] ?? null,
            'status' => \App\Enums\ReportStatus::PENDING,
            'priority_score' => 0.0, // Will be calculated below
        ]);

        // Calculate and update priority score
        $priorityScore = $this->personReportService->calculatePriorityScore($report);
        $report->update(['priority_score' => $priorityScore]);

        // Also update priority scores for other pending reports of same type
        PersonReport::where('person_id', $person->id)
            ->where('type', $report->type)
            ->where('status', \App\Enums\ReportStatus::PENDING)
            ->where('id', '!=', $report->id)
            ->update(['priority_score' => $priorityScore]);

        return response()->json([
            'data' => [
                'id' => $report->id,
                'person_id' => $report->person_id,
                'bio_id' => $report->bio_id,
                'type' => $report->type->value,
                'message' => $report->message,
                'suggested_fix' => $report->suggested_fix,
                'status' => $report->status->value,
                'priority_score' => (float) $report->priority_score,
                'created_at' => $report->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get related people for a given person.
     *
     * Supports filtering by relationship type:
     * - ?type=collaborators - Only collaborators (people who worked together in same movies, different roles)
     * - ?type=same_name - Only people with same name (disambiguation)
     * - ?type=all or no filter - Both collaborators and same name
     *
     * Additional filters:
     * - ?collaborator_role=ACTOR|DIRECTOR|WRITER|PRODUCER - Filter collaborators by role
     * - ?limit=10 - Limit results (default: 20)
     */
    public function related(Request $request, string $slug): JsonResponse
    {
        $person = $this->personRepository->findBySlugWithRelations($slug);
        if (! $person) {
            return $this->responseFormatter->formatNotFound();
        }

        // Parse type filter: collaborators, same_name, or all (default)
        $typeFilter = $request->query('type', 'all');
        $typeFilter = strtolower((string) $typeFilter);

        // Parse collaborator role filter
        $collaboratorRole = $request->query('collaborator_role');
        if ($collaboratorRole !== null) {
            $collaboratorRole = strtoupper((string) $collaboratorRole);
            // Validate role
            if (! in_array($collaboratorRole, ['ACTOR', 'DIRECTOR', 'WRITER', 'PRODUCER'], true)) {
                return $this->responseFormatter->formatError('Invalid collaborator_role. Must be one of: ACTOR, DIRECTOR, WRITER, PRODUCER', 422);
            }
        }

        // Parse limit
        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min(100, $limit)); // Clamp between 1 and 100

        // Build cache key
        $cacheKey = $this->buildRelatedCacheKey($slug, $typeFilter, $collaboratorRole, $limit);

        // Try to get from cache
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        $collaborators = [];
        $sameName = [];

        // Collaborators - people who worked together in same movies, different roles
        if ($typeFilter === 'all' || $typeFilter === 'collaborators') {
            $collaborators = $this->getCollaborators($person, $collaboratorRole, $limit);
        }

        // Same Name - people with same name (disambiguation)
        if ($typeFilter === 'all' || $typeFilter === 'same_name') {
            $sameName = $this->getSameNamePeople($person, $limit);
        }

        // Combine results
        $allRelatedPeople = array_merge($collaborators, $sameName);

        // Build response
        $response = [
            'person' => [
                'id' => $person->id,
                'slug' => $person->slug,
                'name' => $person->name,
            ],
            'related_people' => $allRelatedPeople,
            'count' => count($allRelatedPeople),
            'filters' => [
                'type' => $typeFilter,
                'collaborator_role' => $collaboratorRole,
                'collaborators_count' => count($collaborators),
                'same_name_count' => count($sameName),
            ],
            '_links' => [
                'self' => [
                    'href' => url("/api/v1/people/{$slug}/related"),
                ],
                'person' => [
                    'href' => url("/api/v1/people/{$slug}"),
                ],
                'collaborators' => [
                    'href' => url("/api/v1/people/{$slug}/related?type=collaborators"),
                ],
                'same_name' => [
                    'href' => url("/api/v1/people/{$slug}/related?type=same_name"),
                ],
            ],
        ];

        // Cache response (TTL: 1 hour)
        Cache::put($cacheKey, $response, now()->addHour());

        return response()->json($response);
    }

    /**
     * Get collaborators for a person (people who worked together in same movies, different roles).
     *
     * @param  Person  $person  The person to find collaborators for
     * @param  string|null  $collaboratorRole  Optional role filter (ACTOR, DIRECTOR, WRITER, PRODUCER)
     * @param  int  $limit  Maximum number of collaborators to return
     * @return array<int, array<string, mixed>>
     */
    private function getCollaborators(Person $person, ?string $collaboratorRole, int $limit): array
    {
        // Get all movies this person worked on
        $personMovies = $person->movies()->pluck('movies.id');

        if ($personMovies->isEmpty()) {
            return [];
        }

        // Find other people who worked on the same movies, but in different roles
        /** @var \Illuminate\Database\Eloquent\Builder<Person> $queryBuilder */
        $queryBuilder = Person::whereHas('movies', function ($q) use ($personMovies, $person, $collaboratorRole) {
            $q->whereIn('movies.id', $personMovies)
                ->where('movie_person.person_id', '!=', $person->id);

            // Filter by collaborator role if specified
            if ($collaboratorRole !== null) {
                $q->where('movie_person.role', $collaboratorRole);
            }
        })
            ->with(['movies' => function ($query) use ($personMovies) {
                $query->whereIn('movies.id', $personMovies)
                    ->withPivot(['role', 'character_name', 'job']);
            }]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Person> $query */
        $query = $queryBuilder->get();

        // Group by person and count collaborations
        /** @var array<string, array{person: Person, collaborations: array<int, array<string, mixed>>, collaborations_count: int, person_role: string|null}> $collaboratorsMap */
        $collaboratorsMap = [];
        foreach ($query as $collaborator) {
            $collaborations = [];
            $personRole = null;

            foreach ($collaborator->movies as $movie) {
                /** @var \App\Models\Movie $movie */
                /** @var \Illuminate\Database\Eloquent\Relations\Pivot|null $moviePivot */
                $moviePivot = $movie->pivot;

                // Get person's role in this movie
                $personMoviePivot = $person->movies()->where('movies.id', $movie->id)->first()?->pivot;
                /** @var \Illuminate\Database\Eloquent\Relations\Pivot|null $personMoviePivot */
                /** @phpstan-ignore-next-line */
                $personRoleInMovie = $personMoviePivot !== null ? $personMoviePivot->role : null;

                // Get collaborator's role in this movie
                /** @phpstan-ignore-next-line */
                $collaboratorRoleInMovie = $moviePivot !== null ? $moviePivot->role : null;

                // Only include if roles are different
                if ($personRoleInMovie !== $collaboratorRoleInMovie) {
                    $collaborations[] = [
                        'movie_id' => $movie->id,
                        'movie_slug' => $movie->slug,
                        'movie_title' => $movie->title ?? '',
                        'person_role' => $personRoleInMovie,
                        'collaborator_role' => $collaboratorRoleInMovie,
                    ];

                    if ($personRole === null) {
                        $personRole = $personRoleInMovie;
                    }
                }
            }

            if (! empty($collaborations)) {
                $collaboratorsMap[$collaborator->id] = [
                    'person' => $collaborator,
                    'collaborations' => $collaborations,
                    'collaborations_count' => count($collaborations),
                    'person_role' => $personRole,
                ];
            }
        }

        // Sort by collaborations count (desc) and limit
        /** @phpstan-ignore-next-line */
        uasort($collaboratorsMap, function (array $a, array $b): int {
            return $b['collaborations_count'] <=> $a['collaborations_count'];
        });

        $collaboratorsMap = array_slice($collaboratorsMap, 0, $limit, true);

        // Format response
        $result = [];
        foreach ($collaboratorsMap as $data) {
            /** @var Person $collaborator */
            $collaborator = $data['person'];
            $collaborations = $data['collaborations'];
            $personRole = $data['person_role'];
            $collaboratorRoleInFirst = $collaborations[0]['collaborator_role'] ?? null;

            $result[] = [
                'id' => $collaborator->id,
                'slug' => $collaborator->slug,
                'name' => $collaborator->name,
                'relationship_type' => 'COLLABORATOR',
                'relationship_label' => $collaboratorRoleInFirst !== null
                    ? "Collaborator ({$collaboratorRoleInFirst})"
                    : 'Collaborator',
                'collaborations' => $collaborations,
                'collaborations_count' => count($collaborations),
                '_links' => [
                    'self' => [
                        'href' => url("/api/v1/people/{$collaborator->slug}"),
                    ],
                ],
            ];
        }

        return $result;
    }

    /**
     * Get people with same name (disambiguation).
     *
     * @param  Person  $person  The person to find same name people for
     * @param  int  $limit  Maximum number of people to return
     * @return array<int, array<string, mixed>>
     */
    private function getSameNamePeople(Person $person, int $limit): array
    {
        // Extract base slug (name part without year/suffix)
        $parsed = Person::parseSlug($person->slug);
        $baseSlug = \Illuminate\Support\Str::slug($parsed['name']);

        // Find all people with same name slug
        $sameNamePeople = $this->personRepository->findAllByNameSlug($baseSlug)
            ->where('id', '!=', $person->id) // Exclude current person
            ->take($limit);

        $result = [];
        foreach ($sameNamePeople as $sameNamePerson) {
            $result[] = [
                'id' => $sameNamePerson->id,
                'slug' => $sameNamePerson->slug,
                'name' => $sameNamePerson->name,
                'birth_date' => $sameNamePerson->birth_date?->format('Y-m-d'),
                'birthplace' => $sameNamePerson->birthplace,
                'relationship_type' => 'SAME_NAME',
                'relationship_label' => 'Same Name',
                '_links' => [
                    'self' => [
                        'href' => url("/api/v1/people/{$sameNamePerson->slug}"),
                    ],
                ],
            ];
        }

        return $result;
    }

    /**
     * Build cache key for related people endpoint.
     *
     * @param  string  $slug  Person slug
     * @param  string  $typeFilter  Type filter (collaborators, same_name, all)
     * @param  string|null  $collaboratorRole  Optional collaborator role filter
     * @param  int  $limit  Result limit
     * @return string Cache key
     */
    private function buildRelatedCacheKey(string $slug, string $typeFilter, ?string $collaboratorRole, int $limit): string
    {
        $parts = ['person_related', $slug, $typeFilter];
        if ($collaboratorRole !== null) {
            $parts[] = $collaboratorRole;
        }
        $parts[] = "limit_{$limit}";

        return implode(':', $parts);
    }

    /**
     * Refresh person data from TMDb.
     */
    public function refresh(string $slug): JsonResponse
    {
        $person = $this->personRepository->findBySlugWithRelations($slug);
        if (! $person) {
            return $this->responseFormatter->formatNotFound();
        }

        // Find existing snapshot
        $snapshot = \App\Models\TmdbSnapshot::where('entity_type', 'PERSON')
            ->where('entity_id', $person->id)
            ->first();

        if (! $snapshot) {
            return $this->responseFormatter->formatRefreshNoSnapshot();
        }

        // Refresh person details from TMDb
        /** @var \App\Services\TmdbVerificationService $tmdbService */
        $tmdbService = $this->tmdbVerificationService;
        $freshData = $tmdbService->refreshPersonDetails($snapshot->tmdb_id);
        if (! $freshData) {
            return $this->responseFormatter->formatRefreshFailed();
        }

        // Update snapshot with fresh data
        $snapshot->update([
            'raw_data' => $freshData,
            'fetched_at' => now(),
        ]);

        // Invalidate cache
        Cache::forget($this->cacheKey($slug, null));

        return $this->responseFormatter->formatRefreshSuccess($slug, $person->id);
    }
}
