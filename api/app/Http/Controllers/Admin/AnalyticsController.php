<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for analytics and usage statistics.
 */
class AnalyticsController
{
    public function __construct(
        private readonly AnalyticsService $analyticsService
    ) {}

    /**
     * Get overview statistics.
     */
    public function overview(Request $request): JsonResponse
    {
        $filters = $this->extractFilters($request);
        $stats = $this->analyticsService->getUsageStats($filters);
        $revenue = $this->analyticsService->getRevenueStats($filters);

        return response()->json([
            'usage' => $stats,
            'revenue' => $revenue,
        ]);
    }

    /**
     * Get usage statistics by plan.
     */
    public function byPlan(Request $request): JsonResponse
    {
        $filters = $this->extractFilters($request);
        $stats = $this->analyticsService->getUsageByPlan($filters);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get usage statistics by endpoint.
     */
    public function byEndpoint(Request $request): JsonResponse
    {
        $filters = $this->extractFilters($request);
        $limit = (int) $request->query('limit', 10);
        $stats = $this->analyticsService->getUsageByEndpoint($filters, $limit);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get usage statistics by time range.
     */
    public function byTimeRange(Request $request): JsonResponse
    {
        $filters = $this->extractFilters($request);
        $period = $request->query('period', 'daily'); // daily, weekly, monthly

        if (! in_array($period, ['daily', 'weekly', 'monthly'], true)) {
            return response()->json([
                'error' => 'Invalid period. Must be: daily, weekly, or monthly',
            ], 422);
        }

        $stats = $this->analyticsService->getUsageByTimePeriod($period, $filters);

        return response()->json([
            'period' => $period,
            'data' => $stats,
        ]);
    }

    /**
     * Get top API keys by usage.
     */
    public function topApiKeys(Request $request): JsonResponse
    {
        $filters = $this->extractFilters($request);
        $limit = (int) $request->query('limit', 10);
        $stats = $this->analyticsService->getTopApiKeys($filters, $limit);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get error rate.
     */
    public function errorRate(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $errorRate = $this->analyticsService->getErrorRate($startDate, $endDate);

        return response()->json([
            'error_rate' => $errorRate,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Extract filters from request.
     *
     * @return array Filters array
     */
    private function extractFilters(Request $request): array
    {
        $filters = [];

        if ($request->has('plan_id')) {
            $filters['plan_id'] = $request->query('plan_id');
        }

        if ($request->has('start_date')) {
            $filters['start_date'] = $request->query('start_date');
        }

        if ($request->has('end_date')) {
            $filters['end_date'] = $request->query('end_date');
        }

        if ($request->has('endpoint')) {
            $filters['endpoint'] = $request->query('endpoint');
        }

        return $filters;
    }
}
