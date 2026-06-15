<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parses and validates bulk retrieve parameters (slugs, include).
 *
 * Used by entity controllers (Movie, Person, TvSeries, TvShow) to handle
 * GET /entity?slugs=slug1,slug2 requests with consistent validation.
 */
trait ParsesBulkParameters
{
    /**
     * Parse and validate the 'slugs' query parameter.
     *
     * @return array<int, string>|JsonResponse Array of slugs or error response
     */
    protected function parseSlugsParam(Request $request): array|JsonResponse
    {
        $slugsParam = $request->query('slugs');

        if ($slugsParam === null || $slugsParam === '') {
            return response()->json([
                'errors' => [
                    'slugs' => [trans('api.general.bulk_slugs_required')],
                ],
            ], 422);
        }

        $slugs = is_array($slugsParam) ? $slugsParam : explode(',', (string) $slugsParam);
        $slugs = array_map('trim', $slugs);
        $slugs = array_filter($slugs, fn ($slug) => $slug !== '');

        if (empty($slugs)) {
            return response()->json([
                'errors' => [
                    'slugs' => [trans('api.general.bulk_slugs_required')],
                ],
            ], 422);
        }

        if (count($slugs) > 50) {
            return response()->json([
                'errors' => [
                    'slugs' => [trans('api.general.bulk_max_items')],
                ],
            ], 422);
        }

        foreach ($slugs as $slug) {
            if (! preg_match('/^[a-z0-9-]+$/i', $slug) || strlen($slug) > 255) {
                return response()->json([
                    'errors' => [
                        'slugs' => [trans('api.general.bulk_invalid_slug_pattern')],
                    ],
                ], 422);
            }
        }

        return array_values($slugs);
    }

    /**
     * Parse and validate the 'include' query parameter.
     *
     * @param  array<int, string>  $allowedIncludes  Allowed include values
     * @return array<int, string>|JsonResponse Array of includes or error response
     */
    protected function parseIncludeParam(Request $request, array $allowedIncludes): array|JsonResponse
    {
        $includeParam = $request->query('include');
        $include = is_array($includeParam)
            ? $includeParam
            : ($includeParam !== null ? explode(',', (string) $includeParam) : []);
        $include = array_map('trim', $include);
        $include = array_filter($include, fn ($item) => $item !== '');

        foreach ($include as $item) {
            if (! in_array($item, $allowedIncludes, true)) {
                return response()->json([
                    'errors' => [
                        'include' => [trans('api.general.bulk_invalid_include')],
                    ],
                ], 422);
            }
        }

        return array_values($include);
    }
}
