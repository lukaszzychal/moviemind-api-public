<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiKey;
use App\Models\ApiUsage;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;

/**
 * Service for tracking API usage.
 */
class UsageTracker
{
    /**
     * Track an API request.
     *
     * @param  ApiKey  $apiKey  The API key used
     * @param  string  $endpoint  The endpoint path
     * @param  SubscriptionPlan|null  $plan  The subscription plan
     * @param  string  $method  HTTP method (default: GET)
     * @param  int  $responseStatus  HTTP response status (default: 200)
     * @param  int|null  $responseTimeMs  Response time in milliseconds
     */
    public function trackRequest(
        ApiKey $apiKey,
        string $endpoint,
        ?SubscriptionPlan $plan = null,
        string $method = 'GET',
        int $responseStatus = 200,
        ?int $responseTimeMs = null
    ): void {
        $month = Carbon::now()->format('Y-m');

        ApiUsage::create([
            'api_key_id' => $apiKey->id,
            'plan_id' => $plan?->id,
            'endpoint' => $endpoint,
            'method' => $method,
            'response_status' => $responseStatus,
            'response_time_ms' => $responseTimeMs,
            'month' => $month,
        ]);
    }

    /**
     * Get monthly usage for an API key.
     *
     * @param  ApiKey  $apiKey  The API key
     * @param  string|null  $month  Month in YYYY-MM format (default: current month)
     * @return int The number of requests in the month
     */
    public function getMonthlyUsage(ApiKey $apiKey, ?string $month = null): int
    {
        $month = $month ?? Carbon::now()->format('Y-m');

        return ApiUsage::where('api_key_id', $apiKey->id)
            ->where('month', $month)
            ->count();
    }

    /**
     * Get remaining quota for an API key and plan.
     *
     * @param  ApiKey  $apiKey  The API key
     * @param  SubscriptionPlan|null  $plan  The subscription plan
     * @param  string|null  $month  Month in YYYY-MM format (default: current month)
     * @return int|null The remaining quota (null if unlimited)
     */
    public function getRemainingQuota(ApiKey $apiKey, ?SubscriptionPlan $plan, ?string $month = null): ?int
    {
        if ($plan === null) {
            // Default plan: 100 requests/month
            $monthlyLimit = 100;
        } else {
            $monthlyLimit = $plan->monthly_limit;
        }

        // Unlimited plan
        if ($monthlyLimit === 0) {
            return null;
        }

        $month = $month ?? Carbon::now()->format('Y-m');
        $used = $this->getMonthlyUsage($apiKey, $month);

        return max(0, $monthlyLimit - $used);
    }

    /**
     * Check if API key has exceeded monthly limit.
     *
     * @param  ApiKey  $apiKey  The API key
     * @param  SubscriptionPlan|null  $plan  The subscription plan
     * @param  string|null  $month  Month in YYYY-MM format (default: current month)
     * @return bool True if limit exceeded
     */
    public function hasExceededMonthlyLimit(ApiKey $apiKey, ?SubscriptionPlan $plan, ?string $month = null): bool
    {
        $remaining = $this->getRemainingQuota($apiKey, $plan, $month);

        // Unlimited plan
        if ($remaining === null) {
            return false;
        }

        return $remaining === 0;
    }

    /**
     * Get usage count for a specific time range (for rate limiting per minute).
     *
     * @param  ApiKey  $apiKey  The API key
     * @param  int  $seconds  Time window in seconds (default: 60 for per-minute)
     * @return int The number of requests in the time window
     */
    public function getUsageInTimeWindow(ApiKey $apiKey, int $seconds = 60): int
    {
        $since = Carbon::now()->subSeconds($seconds);

        return ApiUsage::where('api_key_id', $apiKey->id)
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * Check if API key has exceeded rate limit per minute.
     *
     * @param  ApiKey  $apiKey  The API key
     * @param  SubscriptionPlan|null  $plan  The subscription plan
     * @return bool True if rate limit exceeded
     */
    public function hasExceededRateLimit(ApiKey $apiKey, ?SubscriptionPlan $plan): bool
    {
        if ($plan === null) {
            $rateLimit = 10; // Default rate limit
        } else {
            $rateLimit = $plan->rate_limit_per_minute;
        }

        $usageInLastMinute = $this->getUsageInTimeWindow($apiKey, 60);

        return $usageInLastMinute >= $rateLimit;
    }
}
