<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiMetricsController extends Controller
{
    public function __construct(
        private readonly AiMetricsService $metricsService
    ) {}

    /**
     * Get token usage statistics.
     */
    public function tokenUsage(Request $request): JsonResponse
    {
        $entityType = $request->query('entity_type');
        $stats = $this->metricsService->getTokenUsageByFormat($entityType);

        return response()->json([
            'data' => $stats,
            'summary' => [
                'total_requests' => $stats->sum('total_requests'),
                'total_tokens' => $stats->sum('total_tokens'),
            ],
        ]);
    }

    /**
     * Get parsing accuracy statistics.
     */
    public function parsingAccuracy(Request $request): JsonResponse
    {
        $entityType = $request->query('entity_type');
        $stats = $this->metricsService->getParsingAccuracy($entityType);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get error statistics.
     */
    public function errorStatistics(Request $request): JsonResponse
    {
        $entityType = $request->query('entity_type');
        $stats = $this->metricsService->getErrorStatistics($entityType);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get format comparison (TOON vs JSON).
     */
    public function formatComparison(Request $request): JsonResponse
    {
        $entityType = $request->query('entity_type');
        $comparison = $this->metricsService->getFormatComparison($entityType);

        return response()->json([
            'data' => $comparison,
        ]);
    }
}
