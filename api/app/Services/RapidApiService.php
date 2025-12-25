<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling RapidAPI-specific functionality.
 *
 * Responsibilities:
 * - Map RapidAPI subscription plans to internal plans
 * - Validate RapidAPI requests
 * - Extract RapidAPI user information
 */
class RapidApiService
{
    /**
     * Map RapidAPI subscription plan to internal plan name.
     *
     * @param  string|null  $rapidApiPlan  RapidAPI plan (basic, pro, ultra)
     * @return string|null Internal plan name (free, pro, enterprise) or null if not found
     */
    public function mapRapidApiPlan(?string $rapidApiPlan): ?string
    {
        if ($rapidApiPlan === null || $rapidApiPlan === '') {
            return null;
        }

        $planMapping = config('rapidapi.plan_mapping', []);
        $normalizedPlan = strtolower(trim($rapidApiPlan));

        return $planMapping[$normalizedPlan] ?? null;
    }

    /**
     * Validate RapidAPI proxy secret.
     *
     * @param  string|null  $providedSecret  Secret from X-RapidAPI-Proxy-Secret header
     * @return bool True if valid, false otherwise
     */
    public function validateProxySecret(?string $providedSecret): bool
    {
        $expectedSecret = config('rapidapi.proxy_secret');
        $verifyEnabled = config('rapidapi.verify_proxy_secret', true);

        // If verification is disabled, always return true
        if (! $verifyEnabled) {
            return true;
        }

        // If no secret is configured, verification fails
        if ($expectedSecret === null || $expectedSecret === '') {
            return false;
        }

        // If no secret is provided, verification fails
        if ($providedSecret === null || $providedSecret === '') {
            return false;
        }

        // Use hash_equals for timing-safe comparison
        return hash_equals($expectedSecret, trim($providedSecret));
    }

    /**
     * Extract RapidAPI user identifier from request.
     *
     * @return string|null RapidAPI user ID or null if not found
     */
    public function getRapidApiUser(Request $request): ?string
    {
        $userHeader = config('rapidapi.headers.user', 'X-RapidAPI-User');
        $userId = $request->header($userHeader);

        if ($userId === null || $userId === '') {
            return null;
        }

        return trim($userId);
    }

    /**
     * Extract RapidAPI subscription plan from request.
     *
     * @return string|null RapidAPI subscription plan or null if not found
     */
    public function getRapidApiSubscription(Request $request): ?string
    {
        $subscriptionHeader = config('rapidapi.headers.subscription', 'X-RapidAPI-Subscription');
        $subscription = $request->header($subscriptionHeader);

        if ($subscription === null || $subscription === '') {
            return null;
        }

        return trim($subscription);
    }

    /**
     * Check if request is coming from RapidAPI proxy.
     *
     * A request is considered from RapidAPI if it has:
     * - X-RapidAPI-User header, OR
     * - X-RapidAPI-Subscription header, OR
     * - X-RapidAPI-Proxy-Secret header
     *
     * @return bool True if request appears to be from RapidAPI
     */
    public function isRapidApiRequest(Request $request): bool
    {
        $userHeader = config('rapidapi.headers.user', 'X-RapidAPI-User');
        $subscriptionHeader = config('rapidapi.headers.subscription', 'X-RapidAPI-Subscription');
        $proxySecretHeader = config('rapidapi.headers.proxy_secret', 'X-RapidAPI-Proxy-Secret');

        return $request->hasHeader($userHeader)
            || $request->hasHeader($subscriptionHeader)
            || $request->hasHeader($proxySecretHeader);
    }

    /**
     * Log RapidAPI request information.
     *
     * @param  Request  $request  The incoming request
     * @param  string|null  $rapidApiUserId  RapidAPI user ID
     * @param  string|null  $rapidApiPlan  RapidAPI subscription plan
     * @param  string|null  $mappedPlan  Mapped internal plan
     */
    public function logRapidApiRequest(
        Request $request,
        ?string $rapidApiUserId = null,
        ?string $rapidApiPlan = null,
        ?string $mappedPlan = null
    ): void {
        if (! config('rapidapi.log_requests', false)) {
            return;
        }

        Log::info('RapidAPI request', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'method' => $request->method(),
            'rapidapi_user' => $rapidApiUserId,
            'rapidapi_plan' => $rapidApiPlan,
            'mapped_plan' => $mappedPlan,
            'user_agent' => $request->userAgent(),
        ]);
    }
}
