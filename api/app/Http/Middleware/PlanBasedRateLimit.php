<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\SubscriptionPlan;
use App\Services\PlanService;
use App\Services\UsageTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for plan-based rate limiting.
 *
 * Checks both:
 * - Monthly limit (from subscription plan)
 * - Per-minute rate limit (from subscription plan)
 *
 * This middleware should be used AFTER RapidApiAuth middleware
 * to ensure API key is available in request attributes.
 */
class PlanBasedRateLimit
{
    public function __construct(
        private readonly PlanService $planService,
        private readonly UsageTracker $usageTracker
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from request attributes (set by RapidApiAuth middleware)
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey === null || ! ($apiKey instanceof ApiKey)) {
            // If no API key, allow request (RapidApiAuth should have handled this)
            return $next($request);
        }

        // Get subscription plan
        /** @var SubscriptionPlan|null $plan */
        $plan = $apiKey->plan;

        // Check monthly limit
        if ($this->usageTracker->hasExceededMonthlyLimit($apiKey, $plan)) {
            $monthlyLimit = $this->planService->getMonthlyLimit($plan);
            $used = $this->usageTracker->getMonthlyUsage($apiKey);

            Log::warning('Plan-based rate limit exceeded - monthly limit', [
                'api_key_id' => $apiKey->id,
                'plan_id' => $plan instanceof SubscriptionPlan ? $plan->id : null,
                'monthly_limit' => $monthlyLimit,
                'used' => $used,
            ]);

            return $this->rateLimitExceeded(
                'Monthly request limit exceeded',
                $monthlyLimit,
                $used,
                null
            );
        }

        // Check per-minute rate limit
        if ($this->usageTracker->hasExceededRateLimit($apiKey, $plan)) {
            $rateLimit = $this->planService->getRateLimit($plan);
            $usedInLastMinute = $this->usageTracker->getUsageInTimeWindow($apiKey, 60);

            Log::warning('Plan-based rate limit exceeded - per-minute limit', [
                'api_key_id' => $apiKey->id,
                'plan_id' => $plan instanceof SubscriptionPlan ? $plan->id : null,
                'rate_limit_per_minute' => $rateLimit,
                'used_in_last_minute' => $usedInLastMinute,
            ]);

            return $this->rateLimitExceeded(
                'Rate limit exceeded. Please try again in a minute.',
                null,
                null,
                $rateLimit
            );
        }

        // Get response
        $response = $next($request);

        // Track usage after successful request
        $this->trackUsage($apiKey, $plan, $request, $response);

        // Add rate limit headers
        $this->addRateLimitHeaders($response, $apiKey, $plan);

        return $response;
    }

    /**
     * Track API usage for this request.
     */
    private function trackUsage(ApiKey $apiKey, ?SubscriptionPlan $plan, Request $request, Response $response): void
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        $this->usageTracker->trackRequest(
            apiKey: $apiKey,
            endpoint: $request->path(),
            plan: $plan,
            method: $request->method(),
            responseStatus: $response->getStatusCode(),
            responseTimeMs: $responseTimeMs
        );
    }

    /**
     * Add rate limit headers to response.
     */
    private function addRateLimitHeaders(Response $response, ApiKey $apiKey, ?SubscriptionPlan $plan): void
    {
        $monthlyLimit = $this->planService->getMonthlyLimit($plan);
        $rateLimitPerMinute = $this->planService->getRateLimit($plan);
        $monthlyUsed = $this->usageTracker->getMonthlyUsage($apiKey);
        $monthlyRemaining = $this->usageTracker->getRemainingQuota($apiKey, $plan);
        $usedInLastMinute = $this->usageTracker->getUsageInTimeWindow($apiKey, 60);

        // Monthly limit headers
        if ($monthlyLimit > 0) {
            $response->headers->set('X-RateLimit-Monthly-Limit', (string) $monthlyLimit);
            $response->headers->set('X-RateLimit-Monthly-Used', (string) $monthlyUsed);
            $response->headers->set('X-RateLimit-Monthly-Remaining', $monthlyRemaining !== null ? (string) $monthlyRemaining : 'unlimited');
        } else {
            $response->headers->set('X-RateLimit-Monthly-Limit', 'unlimited');
            $response->headers->set('X-RateLimit-Monthly-Used', (string) $monthlyUsed);
            $response->headers->set('X-RateLimit-Monthly-Remaining', 'unlimited');
        }

        // Per-minute rate limit headers
        $response->headers->set('X-RateLimit-Per-Minute-Limit', (string) $rateLimitPerMinute);
        $response->headers->set('X-RateLimit-Per-Minute-Used', (string) $usedInLastMinute);
        $response->headers->set('X-RateLimit-Per-Minute-Remaining', (string) max(0, $rateLimitPerMinute - $usedInLastMinute));
    }

    /**
     * Return 429 Too Many Requests response.
     */
    private function rateLimitExceeded(
        string $message,
        ?int $monthlyLimit,
        ?int $monthlyUsed,
        ?int $rateLimitPerMinute
    ): Response {
        $response = response()->json([
            'error' => 'Too many requests',
            'message' => $message,
        ], 429);

        if ($monthlyLimit !== null && $monthlyUsed !== null) {
            $response->headers->set('X-RateLimit-Monthly-Limit', (string) $monthlyLimit);
            $response->headers->set('X-RateLimit-Monthly-Used', (string) $monthlyUsed);
            $response->headers->set('X-RateLimit-Monthly-Remaining', '0');
        }

        if ($rateLimitPerMinute !== null) {
            $response->headers->set('X-RateLimit-Per-Minute-Limit', (string) $rateLimitPerMinute);
            $response->headers->set('Retry-After', '60'); // Retry after 60 seconds
        }

        return $response;
    }
}
