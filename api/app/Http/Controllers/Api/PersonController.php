<?php

namespace App\Http\Controllers\Api;

use App\Actions\GetRelatedPeopleAction;
use App\Actions\QueuePersonGenerationAction;
use App\Enums\Locale;
use App\Helpers\SlugValidator;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkPeopleRequest;
use App\Http\Requests\ComparePeopleRequest;
use App\Http\Requests\ReportPersonRequest;
use App\Http\Requests\SearchPersonRequest;
use App\Http\Resources\PersonResource;
use App\Http\Responses\PersonResponseFormatter;
use App\Models\Person;
use App\Models\PersonReport;
use App\Repositories\PersonRepository;
use App\Services\BulkRetrievalService;
use App\Services\EntityVerificationServiceInterface;
use App\Services\HateoasService;
use App\Services\PersonComparisonService;
use App\Services\PersonReportService;
use App\Services\PersonRetrievalService;
use App\Services\PersonSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly HateoasService $hateoas,
        private readonly QueuePersonGenerationAction $queuePersonGenerationAction,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService,
        private readonly PersonSearchService $personSearchService,
        private readonly PersonRetrievalService $personRetrievalService,
        private readonly PersonResponseFormatter $responseFormatter,
        private readonly PersonReportService $personReportService,
        private readonly PersonComparisonService $personComparisonService,
        private readonly BulkRetrievalService $bulkRetrievalService,
        private readonly GetRelatedPeopleAction $getRelatedPeopleAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Check if slugs parameter is provided (bulk retrieve)
        // Use has() to check if parameter exists, even if empty
        if ($request->has('slugs')) {
            return $this->handleBulkRetrieve($request);
        }

        // Normal search
        $q = $request->query('q');
        $limit = (int) $request->query('per_page', 50);
        $people = $this->personRepository->searchPeople($q, $limit);

        $data = $people->getCollection()->map(fn ($person) => $this->transformPerson($person));

        return response()->json([
            'data' => $data,
            'pagination' => [
                'current_page' => $people->currentPage(),
                'per_page' => $people->perPage(),
                'total_pages' => $people->lastPage(),
                'total' => $people->total(),
                'has_next_page' => $people->hasMorePages(),
                'has_previous_page' => $people->currentPage() > 1,
            ],
        ]);
    }

    /**
     * Handle bulk retrieve via GET /people?slugs=...
     */
    /**
     * Handle bulk retrieve via GET /people?slugs=...
     */
    private function handleBulkRetrieve(Request $request): JsonResponse
    {
        $slugsParam = $request->query('slugs');
        if ($slugsParam === null) {
            return response()->json(['errors' => ['slugs' => [trans('api.general.bulk_slugs_required')]]], 422);
        }

        $slugs = is_array($slugsParam) ? $slugsParam : explode(',', (string) $slugsParam);
        $slugs = array_map('trim', $slugs);
        $slugs = array_filter($slugs, fn ($slug) => $slug !== '');

        if (empty($slugs)) {
            return response()->json(['errors' => ['slugs' => [trans('api.general.bulk_slugs_required')]]], 422);
        }

        if (count($slugs) > 50) {
            return response()->json(['errors' => ['slugs' => [trans('api.general.bulk_max_items')]]], 422);
        }

        foreach ($slugs as $slug) {
            if (! preg_match('/^[a-z0-9-]+$/i', $slug) || strlen($slug) > 255) {
                return response()->json(['errors' => ['slugs' => [trans('api.general.bulk_invalid_slug_pattern')]]], 422);
            }
        }

        // Parse include
        $includeParam = $request->query('include');
        $include = is_array($includeParam) ? $includeParam : ($includeParam !== null ? explode(',', (string) $includeParam) : []);
        $include = array_map('trim', $include);
        $allowedInclude = ['bios', 'movies'];
        foreach ($include as $item) {
            if (! in_array($item, $allowedInclude, true)) {
                return response()->json(['errors' => ['include' => [trans('api.general.bulk_invalid_include')]]], 422);
            }
        }

        $result = $this->bulkRetrievalService->retrieve(
            $this->personRepository,
            $slugs,
            $include,
            function (Person $person) {
                return PersonResource::make($person)->additional([
                    '_links' => $this->hateoas->personLinks($person),
                ])->resolve();
            }
        );

        return response()->json($result, 200);
    }

    /**
     * Bulk retrieve multiple people by slugs.
     */
    public function bulk(BulkPeopleRequest $request): JsonResponse
    {
        $slugs = $request->getSlugs();
        $include = $request->getInclude();

        $result = $this->bulkRetrievalService->retrieve(
            $this->personRepository,
            $slugs,
            $include,
            function (Person $person) {
                return PersonResource::make($person)->additional([
                    '_links' => $this->hateoas->personLinks($person),
                ])->resolve();
            }
        );

        return response()->json($result, 200);
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
            $responseData = json_decode($response->getContent(), true);
            $this->personRetrievalService->putCache($slug, $bioId, $responseData);
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
            'error' => trans('api.person.multiple_found'),
            'message' => trans('api.person.disambiguation_message'),
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
            return response()->json(['error' => trans('api.person.not_found')], 404);
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

        try {
            $result = $this->getRelatedPeopleAction->handle($person, $request);
        } catch (\InvalidArgumentException $e) {
            return $this->responseFormatter->formatError($e->getMessage(), 422);
        }

        return response()->json($result);
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
        $this->personRetrievalService->forgetCache($slug);

        return $this->responseFormatter->formatRefreshSuccess($slug, $person->id);
    }

    /**
     * Compare two people.
     */
    public function compare(ComparePeopleRequest $request): JsonResponse
    {
        $slug1 = $request->validated()['slug1'];
        $slug2 = $request->validated()['slug2'];

        try {
            $comparison = $this->personComparisonService->compare($slug1, $slug2);

            return response()->json($comparison);
        } catch (\InvalidArgumentException $e) {
            return $this->responseFormatter->formatNotFound();
        }
    }
}
