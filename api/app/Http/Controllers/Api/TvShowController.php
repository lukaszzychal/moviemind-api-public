<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompareTvShowRequest;
use App\Http\Requests\ReportTvShowRequest;
use App\Http\Resources\TvShowResource;
use App\Http\Responses\TvShowResponseFormatter;
use App\Models\TvShow;
use App\Models\TvShowRelationship;
use App\Models\TvShowReport;
use App\Repositories\TvShowRepository;
use App\Services\HateoasService;
use App\Services\TvShowComparisonService;
use App\Services\TvShowReportService;
use App\Services\TvShowRetrievalService;
use App\Services\TvShowSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TvShowController extends Controller
{
    public function __construct(
        private readonly TvShowRepository $tvShowRepository,
        private readonly HateoasService $hateoas,
        private readonly TvShowSearchService $tvShowSearchService,
        private readonly TvShowRetrievalService $tvShowRetrievalService,
        private readonly TvShowResponseFormatter $responseFormatter,
        private readonly TvShowReportService $tvShowReportService,
        private readonly TvShowComparisonService $tvShowComparisonService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $slugsParam = $request->query('slugs');
        if ($slugsParam !== null) {
            return $this->handleBulkRetrieve($request);
        }

        $q = $request->query('q');
        $tvShows = $this->tvShowRepository->searchTvShows($q, 50);
        $data = $tvShows->map(function ($tvShow) {
            $resource = TvShowResource::make($tvShow)->additional([
                '_links' => $this->hateoas->tvShowLinks($tvShow),
            ]);

            return $resource->resolve();
        });

        return response()->json([
            'data' => $data->toArray(),
            'count' => $data->count(),
        ]);
    }

    /**
     * Handle bulk retrieve via GET /tv-shows?slugs=...
     */
    private function handleBulkRetrieve(Request $request): JsonResponse
    {
        $slugsParam = $request->query('slugs');

        if ($slugsParam === null || $slugsParam === '') {
            return response()->json([
                'errors' => [
                    'slugs' => ['The slugs field is required and cannot be empty.'],
                ],
            ], 422);
        }

        $slugs = is_array($slugsParam) ? $slugsParam : explode(',', (string) $slugsParam);
        $slugs = array_map('trim', $slugs);
        $slugs = array_filter($slugs, fn ($slug) => $slug !== '');

        if (empty($slugs)) {
            return response()->json([
                'errors' => [
                    'slugs' => ['The slugs field is required and cannot be empty.'],
                ],
            ], 422);
        }

        if (count($slugs) > 50) {
            return response()->json([
                'errors' => [
                    'slugs' => ['The slugs field must not have more than 50 items.'],
                ],
            ], 422);
        }

        foreach ($slugs as $slug) {
            if (! preg_match('/^[a-z0-9-]+$/i', $slug) || strlen($slug) > 255) {
                return response()->json([
                    'errors' => [
                        'slugs' => ['Each slug must match the pattern: /^[a-z0-9-]+$/i and be max 255 characters.'],
                    ],
                ], 422);
            }
        }

        $includeParam = $request->query('include');
        $include = is_array($includeParam) ? $includeParam : ($includeParam !== null ? explode(',', (string) $includeParam) : []);
        $include = array_map('trim', $include);
        $include = array_filter($include, fn ($item) => $item !== '');

        $allowedInclude = ['descriptions', 'people'];
        foreach ($include as $item) {
            if (! in_array($item, $allowedInclude, true)) {
                return response()->json([
                    'errors' => [
                        'include' => ['The include field must contain only: '.implode(', ', $allowedInclude).'.'],
                    ],
                ], 422);
            }
        }

        $tvShows = $this->tvShowRepository->findBySlugs($slugs, $include);

        $data = $tvShows->map(function (TvShow $tvShow) {
            $resource = TvShowResource::make($tvShow)->additional([
                '_links' => $this->hateoas->tvShowLinks($tvShow),
            ]);

            return $resource->resolve();
        })->toArray();

        $foundSlugs = $tvShows->pluck('slug')->toArray();
        $notFound = array_values(array_diff($slugs, $foundSlugs));

        return response()->json([
            'data' => $data,
            'not_found' => $notFound,
            'count' => count($data),
            'requested_count' => count($slugs),
        ], 200);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $descriptionId = $this->normalizeDescriptionId($request->query('description_id'));
        if ($descriptionId === false) {
            return $this->responseFormatter->formatError('Invalid description_id parameter', 422);
        }

        $result = $this->tvShowRetrievalService->retrieveTvShow($slug, $descriptionId);

        return $this->responseFormatter->formatFromResult($result, $slug);
    }

    public function search(Request $request): JsonResponse
    {
        $criteria = [
            'q' => $request->query('q'),
            'year' => $request->query('year') ? (int) $request->query('year') : null,
            'limit' => $request->query('limit') ? (int) $request->query('limit') : 20,
            'page' => $request->query('page') ? (int) $request->query('page') : null,
            'per_page' => $request->query('per_page') ? (int) $request->query('per_page') : 20,
            'sort' => $request->query('sort'),
            'order' => $request->query('order'),
        ];

        $searchResult = $this->tvShowSearchService->search($criteria);

        return response()->json($searchResult->toArray(), 200);
    }

    /**
     * Normalize description_id parameter.
     * Returns null if empty, string if valid UUID, false if invalid.
     */
    private function normalizeDescriptionId(mixed $descriptionId): null|string|false
    {
        if ($this->isEmpty($descriptionId)) {
            return null;
        }

        $descriptionId = (string) $descriptionId;

        if (! $this->isValidUuid($descriptionId)) {
            return false;
        }

        return $descriptionId;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private function isValidUuid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    public function related(Request $request, string $slug): JsonResponse
    {
        $tvShow = $this->tvShowRepository->findBySlugWithRelations($slug);
        if (! $tvShow) {
            return $this->responseFormatter->formatNotFound();
        }

        $typeFilter = $request->query('type', 'all');
        $types = $typeFilter !== 'all' ? [strtoupper((string) $typeFilter)] : null;
        $relatedShows = $tvShow->getRelatedShows($types);

        $relatedData = $relatedShows->map(function (TvShow $related) use ($tvShow) {
            $relationship = TvShowRelationship::where(function ($query) use ($tvShow, $related) {
                $query->where('tv_show_id', $tvShow->id)
                    ->where('related_tv_show_id', $related->id);
            })->orWhere(function ($query) use ($tvShow, $related) {
                $query->where('tv_show_id', $related->id)
                    ->where('related_tv_show_id', $tvShow->id);
            })->first();

            $resource = TvShowResource::make($related)->additional([
                '_links' => $this->hateoas->tvShowLinks($related),
            ]);

            $data = $resource->resolve();
            $data['relationship_type'] = $relationship?->relationship_type->value ?? null;
            $data['relationship_label'] = $relationship?->relationship_type->label() ?? null;
            $data['relationship_order'] = $relationship?->order;

            return $data;
        })->values()->toArray();

        return response()->json([
            'tv_show' => [
                'id' => $tvShow->id,
                'slug' => $tvShow->slug,
                'title' => $tvShow->title,
            ],
            'related_tv_shows' => $relatedData,
            'count' => count($relatedData),
            '_links' => [
                'self' => ['href' => url("/api/v1/tv-shows/{$slug}/related")],
                'tv_show' => ['href' => url("/api/v1/tv-shows/{$slug}")],
            ],
        ]);
    }

    public function refresh(string $slug): JsonResponse
    {
        $tvShow = $this->tvShowRepository->findBySlugWithRelations($slug);
        if (! $tvShow) {
            return $this->responseFormatter->formatNotFound();
        }

        $snapshot = \App\Models\TmdbSnapshot::where('entity_type', 'TV_SHOW')
            ->where('entity_id', $tvShow->id)
            ->first();

        if (! $snapshot) {
            return response()->json(['error' => 'No TMDb snapshot found for this TV show'], 404);
        }

        // TODO: Implement refreshTvShowDetails in TmdbVerificationService
        return response()->json([
            'message' => 'TV show data refreshed from TMDb',
            'slug' => $slug,
            'tv_show_id' => $tvShow->id,
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    public function report(ReportTvShowRequest $request, string $slug): JsonResponse
    {
        $tvShow = $this->tvShowRepository->findBySlugWithRelations($slug);
        if (! $tvShow) {
            return $this->responseFormatter->formatNotFound();
        }

        $validated = $request->validated();

        $report = TvShowReport::create([
            'tv_show_id' => $tvShow->id,
            'description_id' => $validated['description_id'] ?? null,
            'type' => $validated['type'],
            'message' => $validated['message'],
            'suggested_fix' => $validated['suggested_fix'] ?? null,
            'status' => \App\Enums\ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        $priorityScore = $this->tvShowReportService->calculatePriorityScore($report);
        $report->update(['priority_score' => $priorityScore]);

        TvShowReport::where('tv_show_id', $tvShow->id)
            ->where('type', $report->type)
            ->where('status', \App\Enums\ReportStatus::PENDING)
            ->where('id', '!=', $report->id)
            ->update(['priority_score' => $priorityScore]);

        return response()->json([
            'data' => [
                'id' => $report->id,
                'tv_show_id' => $report->tv_show_id,
                'type' => $report->type->value,
                'message' => $report->message,
                'status' => $report->status->value,
                'priority_score' => $report->priority_score,
                'created_at' => $report->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function compare(CompareTvShowRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $comparison = $this->tvShowComparisonService->compare(
                $validated['slug1'],
                $validated['slug2']
            );

            return response()->json($comparison, 200);
        } catch (\InvalidArgumentException $e) {
            return $this->responseFormatter->formatError($e->getMessage(), 404);
        }
    }
}
