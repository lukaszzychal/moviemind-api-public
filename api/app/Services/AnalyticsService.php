<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiKey;
use App\Models\ApiUsage;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service for generating analytics and usage statistics.
 *
 * Responsibilities:
 * - Aggregate usage data by plan, endpoint, time range
 * - Calculate revenue statistics
 * - Identify top endpoints and API keys
 * - Calculate error rates
 */
class AnalyticsService
{
    /**
     * Get usage statistics with optional filters.
     *
     * @param  array  $filters  Filters: plan_id, start_date, end_date, endpoint
     * @return array Usage statistics
     */
    public function getUsageStats(array $filters = []): array
    {
        $query = ApiUsage::query();

        // Apply filters
        if (isset($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        if (isset($filters['endpoint'])) {
            $query->where('endpoint', $filters['endpoint']);
        }

        $totalRequests = $query->count();
        $successfulRequests = (clone $query)->where('response_status', '>=', 200)
            ->where('response_status', '<', 300)
            ->count();
        $errorRequests = (clone $query)->where('response_status', '>=', 400)
            ->count();

        $avgResponseTime = (clone $query)->whereNotNull('response_time_ms')
            ->avg('response_time_ms');

        return [
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'error_requests' => $errorRequests,
            'success_rate' => $totalRequests > 0 ? round(($successfulRequests / $totalRequests) * 100, 2) : 0,
            'error_rate' => $totalRequests > 0 ? round(($errorRequests / $totalRequests) * 100, 2) : 0,
            'avg_response_time_ms' => $avgResponseTime ? round((float) $avgResponseTime, 2) : null,
        ];
    }

    /**
     * Get usage statistics grouped by plan.
     *
     * @param  array  $filters  Optional filters
     * @return array Usage statistics per plan
     */
    public function getUsageByPlan(array $filters = []): array
    {
        $query = ApiUsage::query();

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        $results = $query->select('plan_id', DB::raw('COUNT(*) as total_requests'))
            ->groupBy('plan_id')
            ->get();

        $plans = SubscriptionPlan::whereIn('id', $results->pluck('plan_id')->filter())->get()->keyBy('id');

        return $results->map(function ($result) use ($plans) {
            /** @phpstan-var object{plan_id: string, total_requests: int|string} $result */
            $plan = $plans->get($result->plan_id);
            $totalRequests = (int) ($result->total_requests ?? 0);

            return [
                'plan_id' => $result->plan_id,
                'plan_name' => $plan?->name ?? 'unknown',
                'plan_display_name' => $plan?->display_name ?? 'Unknown',
                'total_requests' => $totalRequests,
            ];
        })->values()->toArray();
    }

    /**
     * Get usage statistics grouped by endpoint.
     *
     * @param  array  $filters  Optional filters
     * @param  int  $limit  Limit number of results (default: 10)
     * @return array Usage statistics per endpoint
     */
    public function getUsageByEndpoint(array $filters = [], int $limit = 10): array
    {
        $query = ApiUsage::query();

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        if (isset($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }

        $results = $query->select('endpoint', 'method', DB::raw('COUNT(*) as total_requests'))
            ->groupBy('endpoint', 'method')
            ->orderByDesc('total_requests')
            ->limit($limit)
            ->get();

        return $results->map(function ($result) {
            /** @phpstan-var object{endpoint: string, method: string, total_requests: int|string} $result */
            return [
                'endpoint' => $result->endpoint ?? '',
                'method' => $result->method ?? '',
                'total_requests' => (int) ($result->total_requests ?? 0),
            ];
        })
            ->toArray();
    }

    /**
     * Get top API keys by usage.
     *
     * @param  array  $filters  Optional filters
     * @param  int  $limit  Limit number of results (default: 10)
     * @return array Top API keys
     */
    public function getTopApiKeys(array $filters = [], int $limit = 10): array
    {
        $query = ApiUsage::query();

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        $results = $query->select('api_key_id', DB::raw('COUNT(*) as total_requests'))
            ->groupBy('api_key_id')
            ->orderByDesc('total_requests')
            ->limit($limit)
            ->get();

        $apiKeys = ApiKey::whereIn('id', $results->pluck('api_key_id'))->get()->keyBy('id');

        return $results->map(function ($result) use ($apiKeys) {
            /** @phpstan-var object{api_key_id: string, total_requests: int|string} $result */
            $apiKey = $apiKeys->get($result->api_key_id);
            $totalRequests = (int) ($result->total_requests ?? 0);

            return [
                'api_key_id' => $result->api_key_id ?? '',
                'api_key_name' => $apiKey !== null ? $apiKey->name : 'Unknown',
                'api_key_prefix' => $apiKey !== null ? $apiKey->key_prefix : 'unknown',
                'total_requests' => $totalRequests,
            ];
        })->values()->toArray();
    }

    /**
     * Get usage statistics aggregated by time period.
     *
     * @param  string  $period  Period: daily, weekly, monthly
     * @param  array  $filters  Optional filters
     * @return array Usage statistics by period
     */
    public function getUsageByTimePeriod(string $period, array $filters = []): array
    {
        $query = ApiUsage::query();

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        if (isset($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }

        // Use database-specific date formatting
        $driver = config('database.default');

        $isSqlite = $driver === 'sqlite' || str_contains($driver, 'sqlite');

        if ($isSqlite) {
            $dateExpr = match ($period) {
                'daily' => "strftime('%Y-%m-%d', created_at)",
                'weekly' => "strftime('%Y-%W', created_at)", // Week number
                'monthly' => "strftime('%Y-%m', created_at)",
                default => "strftime('%Y-%m-%d', created_at)",
            };
        } else {
            $dateFormat = match ($period) {
                'daily' => '%Y-%m-%d',
                'weekly' => '%Y-%u', // ISO week
                'monthly' => '%Y-%m',
                default => '%Y-%m-%d',
            };
            $dateExpr = "DATE_FORMAT(created_at, '{$dateFormat}')";
        }

        $results = $query->select(
            DB::raw("{$dateExpr} as period"),
            DB::raw('COUNT(*) as total_requests'),
            DB::raw('SUM(CASE WHEN response_status >= 200 AND response_status < 300 THEN 1 ELSE 0 END) as successful_requests'),
            DB::raw('SUM(CASE WHEN response_status >= 400 THEN 1 ELSE 0 END) as error_requests')
        )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $results->map(function ($result) {
            /** @phpstan-var object{period: string, total_requests: int|string, successful_requests: int|string, error_requests: int|string} $result */
            return [
                'period' => $result->period ?? '',
                'total_requests' => (int) ($result->total_requests ?? 0),
                'successful_requests' => (int) ($result->successful_requests ?? 0),
                'error_requests' => (int) ($result->error_requests ?? 0),
            ];
        })
            ->toArray();
    }

    /**
     * Get error rate for a time range.
     *
     * @param  string|null  $startDate  Start date (YYYY-MM-DD)
     * @param  string|null  $endDate  End date (YYYY-MM-DD)
     * @return float Error rate percentage
     */
    public function getErrorRate(?string $startDate = null, ?string $endDate = null): float
    {
        $query = ApiUsage::query();

        if ($startDate !== null) {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate !== null) {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        $total = $query->count();
        $errors = (clone $query)->where('response_status', '>=', 400)->count();

        if ($total === 0) {
            return 0.0;
        }

        return round(($errors / $total) * 100, 2);
    }

    /**
     * Get revenue statistics (estimated based on plan prices).
     *
     * @param  array  $filters  Optional filters
     * @return array Revenue statistics
     */
    public function getRevenueStats(array $filters = []): array
    {
        // Get active subscriptions with their plans
        $subscriptions = \App\Models\Subscription::where('status', 'active')
            ->with('plan')
            ->get();

        $monthlyRevenue = 0;
        $yearlyRevenue = 0;

        foreach ($subscriptions as $subscription) {
            $plan = $subscription->plan;
            if ($plan instanceof SubscriptionPlan) {
                $priceMonthly = $plan->price_monthly;
                $priceYearly = $plan->price_yearly;

                if ($priceMonthly !== null) {
                    $monthlyRevenue += (float) $priceMonthly;
                }
                if ($priceYearly !== null) {
                    $yearlyRevenue += (float) $priceYearly;
                }
            }
        }

        return [
            'monthly_revenue' => round($monthlyRevenue, 2),
            'yearly_revenue' => round($yearlyRevenue, 2),
            'active_subscriptions' => $subscriptions->count(),
        ];
    }
}
