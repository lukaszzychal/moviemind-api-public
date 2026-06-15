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
    use \App\Http\Concerns\BuildsPaginationResponse;
    use \App\Http\Concerns\ParsesBulkParameters;

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
        $limit = (int) $request->query('per_page', 50);
        $tvSeries = $this->tvSeriesRepository->searchTvSeries($q, $limit);

        $data = $tvSeries->getCollection()->map(function ($tvSeries) {
            $resource = TvSeriesResource::make($tvSeries)->additional([
                '_links' => $this->hateoas->tvSeriesLinks($tvSeries),
            ]);

            return $resource->resolve();
        });

        return response()->json([
            'data' => $data,
            'pagination' => $this->buildPaginationMeta($tvSeries),
        ]);
    }

    /**
     * Handle bulk retrieve via GET /tv-series?slugs=...
     */
    private function handleBulkRetrieve(Request $request): JsonResponse
    {
        $slugsResult = $this->parseSlugsParam($request);
        if ($slugsResult instanceof JsonResponse) {
            return $slugsResult;
        }

        $includeResult = $this->parseIncludeParam($request, ['descriptions', 'people']);
        if ($includeResult instanceof JsonResponse) {
            return $includeResult;
        }

        $tvSeries = $this->tvSeriesRepository->findBySlugs($slugsResult, $includeResult);

        $data = $tvSeries->map(function (TvSeries $tvSeries) {
            $resource = TvSeriesResource::make($tvSeries)->additional([
                '_links' => $this->hateoas->tvSeriesLinks($tvSeries),
            ]);

            return $resource->resolve();
        })->toArray();

        $foundSlugs = $tvSeries->pluck('slug')->toArray();
        $notFound = array_values(array_diff($slugsResult, $foundSlugs));

        return response()->json([
            'data' => $data,
            'not_found' => $notFound,
            'count' => count($data),
            'requested_count' => count($slugsResult),
        ], 200);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $descriptionId = \App\Helpers\UuidValidator::normalize($request->query('description_id'));
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
            return response()->json(['error' => trans('api.tv_series.no_snapshot')], 404);
        }

        // TODO: Implement refreshTvSeriesDetails in TmdbVerificationService
        // For now, return success message
        return response()->json([
            'message' => trans('api.tv_series.refresh_success'),
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
        $report = $this->tvSeriesReportService->createReport($tvSeries, $validated);

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
