<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TvShowResource;
use App\Http\Responses\TvShowResponseFormatter;
use App\Models\TvShow;
use App\Repositories\TvShowRepository;
use App\Services\HateoasService;
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
        private readonly TvShowResponseFormatter $responseFormatter
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
}
