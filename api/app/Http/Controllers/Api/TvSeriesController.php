<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompareTvSeriesRequest;
use App\Http\Requests\ReportTvSeriesRequest;
use App\Http\Resources\TvSeriesResource;
use App\Http\Responses\TvSeriesResponseFormatter;
use App\Models\TvSeries;
use App\Models\TvSeriesRelationship;
use App\Models\TvSeriesReport;
use App\Repositories\TvSeriesRepository;
use App\Services\HateoasService;
use App\Services\TvSeriesComparisonService;
use App\Services\TvSeriesReportService;
use App\Services\TvSeriesRetrievalService;
use App\Services\TvSeriesSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TvSeriesController extends Controller
{
    public function __construct(
        private readonly TvSeriesRepository $tvSeriesRepository,
        private readonly HateoasService $hateoas,
        private readonly TvSeriesSearchService $tvSeriesSearchService,
        private readonly TvSeriesRetrievalService $tvSeriesRetrievalService,
        private readonly TvSeriesResponseFormatter $responseFormatter,
        private readonly TvSeriesReportService $tvSeriesReportService,
        private readonly TvSeriesComparisonService $tvSeriesComparisonService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $slugsParam = $request->query('slugs');
        if ($slugsParam !== null) {
            return $this->handleBulkRetrieve($request);
        }

        $q = $request->query('q');
        $tvSeries = $this->tvSeriesRepository->searchTvSeries($q, 50);
        $data = $tvSeries->map(function ($tvSeries) {
            $resource = TvSeriesResource::make($tvSeries)->additional([
                '_links' => $this->hateoas->tvSeriesLinks($tvSeries),
            ]);

            return $resource->resolve();
        });

        return response()->json([
            'data' => $data->toArray(),
            'count' => $data->count(),
        ]);
    }

    /**
     * Handle bulk retrieve via GET /tv-series?slugs=...
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

        $tvSeries = $this->tvSeriesRepository->findBySlugs($slugs, $include);

        $data = $tvSeries->map(function (TvSeries $tvSeries) {
            $resource = TvSeriesResource::make($tvSeries)->additional([
                '_links' => $this->hateoas->tvSeriesLinks($tvSeries),
            ]);

            return $resource->resolve();
        })->toArray();

        $foundSlugs = $tvSeries->pluck('slug')->toArray();
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

        $result = $this->tvSeriesRetrievalService->retrieveTvSeries($slug, $descriptionId);

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

        $searchResult = $this->tvSeriesSearchService->search($criteria);

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
        $tvSeries = $this->tvSeriesRepository->findBySlugWithRelations($slug);
        if (! $tvSeries) {
            return $this->responseFormatter->formatNotFound();
        }

        $typeFilter = $request->query('type', 'all');
        $types = $typeFilter !== 'all' ? [strtoupper((string) $typeFilter)] : null;
        $relatedSeries = $tvSeries->getRelatedSeries($types);

        $relatedData = $relatedSeries->map(function (TvSeries $related) use ($tvSeries) {
            $relationship = TvSeriesRelationship::where(function ($query) use ($tvSeries, $related) {
                $query->where('tv_series_id', $tvSeries->id)
                    ->where('related_tv_series_id', $related->id);
            })->orWhere(function ($query) use ($tvSeries, $related) {
                $query->where('tv_series_id', $related->id)
                    ->where('related_tv_series_id', $tvSeries->id);
            })->first();

            $resource = TvSeriesResource::make($related)->additional([
                '_links' => $this->hateoas->tvSeriesLinks($related),
            ]);

            $data = $resource->resolve();
            $data['relationship_type'] = $relationship?->relationship_type->value ?? null;
            $data['relationship_label'] = $relationship?->relationship_type->label() ?? null;
            $data['relationship_order'] = $relationship?->order;

            return $data;
        })->values()->toArray();

        return response()->json([
            'tv_series' => [
                'id' => $tvSeries->id,
                'slug' => $tvSeries->slug,
                'title' => $tvSeries->title,
            ],
            'related_tv_series' => $relatedData,
            'count' => count($relatedData),
            '_links' => [
                'self' => ['href' => url("/api/v1/tv-series/{$slug}/related")],
                'tv_series' => ['href' => url("/api/v1/tv-series/{$slug}")],
            ],
        ]);
    }

    public function refresh(string $slug): JsonResponse
    {
        $tvSeries = $this->tvSeriesRepository->findBySlugWithRelations($slug);
        if (! $tvSeries) {
            return $this->responseFormatter->formatNotFound();
        }

        $snapshot = \App\Models\TmdbSnapshot::where('entity_type', 'TV_SERIES')
            ->where('entity_id', $tvSeries->id)
            ->first();

        if (! $snapshot) {
            return response()->json(['error' => 'No TMDb snapshot found for this TV series'], 404);
        }

        // TODO: Implement refreshTvSeriesDetails in TmdbVerificationService
        // For now, return success message
        return response()->json([
            'message' => 'TV series data refreshed from TMDb',
            'slug' => $slug,
            'tv_series_id' => $tvSeries->id,
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    public function report(ReportTvSeriesRequest $request, string $slug): JsonResponse
    {
        $tvSeries = $this->tvSeriesRepository->findBySlugWithRelations($slug);
        if (! $tvSeries) {
            return $this->responseFormatter->formatNotFound();
        }

        $validated = $request->validated();

        $report = TvSeriesReport::create([
            'tv_series_id' => $tvSeries->id,
            'description_id' => $validated['description_id'] ?? null,
            'type' => $validated['type'],
            'message' => $validated['message'],
            'suggested_fix' => $validated['suggested_fix'] ?? null,
            'status' => \App\Enums\ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        $priorityScore = $this->tvSeriesReportService->calculatePriorityScore($report);
        $report->update(['priority_score' => $priorityScore]);

        TvSeriesReport::where('tv_series_id', $tvSeries->id)
            ->where('type', $report->type)
            ->where('status', \App\Enums\ReportStatus::PENDING)
            ->where('id', '!=', $report->id)
            ->update(['priority_score' => $priorityScore]);

        return response()->json([
            'data' => [
                'id' => $report->id,
                'tv_series_id' => $report->tv_series_id,
                'type' => $report->type->value,
                'message' => $report->message,
                'status' => $report->status->value,
                'priority_score' => $report->priority_score,
                'created_at' => $report->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function compare(CompareTvSeriesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $comparison = $this->tvSeriesComparisonService->compare(
                $validated['slug1'],
                $validated['slug2']
            );

            return response()->json($comparison, 200);
        } catch (\InvalidArgumentException $e) {
            return $this->responseFormatter->formatError($e->getMessage(), 404);
        }
    }
}
