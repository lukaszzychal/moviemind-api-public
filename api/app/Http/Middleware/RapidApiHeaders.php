<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\RapidApiService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for handling RapidAPI-specific headers.
 *
 * Responsibilities:
 * - Verify X-RapidAPI-Proxy-Secret (if enabled)
 * - Extract and map RapidAPI subscription plan
 * - Extract RapidAPI user identifier
 * - Log RapidAPI requests (if enabled)
 *
 * This middleware should run AFTER RapidApiAuth middleware
 * to ensure API key is already validated.
 */
class RapidApiHeaders
{
    public function __construct(
        private readonly RapidApiService $rapidApiService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only process requests that appear to be from RapidAPI
        if (! $this->rapidApiService->isRapidApiRequest($request)) {
            return $next($request);
        }

        // Verify proxy secret if enabled
        $proxySecretHeader = config('rapidapi.headers.proxy_secret', 'X-RapidAPI-Proxy-Secret');
        $providedSecret = $request->header($proxySecretHeader);

        if (! $this->rapidApiService->validateProxySecret($providedSecret)) {
            Log::warning('RapidAPI proxy secret verification failed', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Invalid proxy secret',
            ], 403);
        }

        // Extract RapidAPI information
        $rapidApiUserId = $this->rapidApiService->getRapidApiUser($request);
        $rapidApiPlan = $this->rapidApiService->getRapidApiSubscription($request);
        $mappedPlan = $this->rapidApiService->mapRapidApiPlan($rapidApiPlan);

        // Add RapidAPI information to request attributes
        if ($rapidApiUserId !== null) {
            $request->attributes->set('rapidapi_user_id', $rapidApiUserId);
        }

        if ($rapidApiPlan !== null) {
            $request->attributes->set('rapidapi_plan', $rapidApiPlan);
        }

        if ($mappedPlan !== null) {
            $request->attributes->set('rapidapi_mapped_plan', $mappedPlan);
        }

        // Log request if enabled
        $this->rapidApiService->logRapidApiRequest(
            $request,
            $rapidApiUserId,
            $rapidApiPlan,
            $mappedPlan
        );

        return $next($request);
    }
}
