<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AdaptiveRateLimiter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for adaptive rate limiting based on system load.
 *
 * Dynamically adjusts rate limits based on:
 * - CPU load
 * - Queue size
 * - Active jobs
 */
class AdaptiveRateLimit
{
    public function __construct(
        private readonly AdaptiveRateLimiter $rateLimiter
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $endpoint  Endpoint identifier (search, generate, report)
     */
    public function handle(Request $request, Closure $next, string $endpoint): Response
    {
        // Get dynamic rate limit based on system load
        $maxAttempts = $this->rateLimiter->getMaxAttempts($endpoint);

        // Create unique key for this user/IP and endpoint
        $key = $this->resolveRequestSignature($request, $endpoint);

        // Check if rate limit exceeded
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'error' => 'Too many requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $seconds,
            ], 429)->withHeaders([
                'Retry-After' => $seconds,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        // Increment rate limit counter
        RateLimiter::hit($key, 60); // 60 seconds = 1 minute

        // Get response
        $response = $next($request);

        // Add rate limit headers
        $remaining = max(0, $maxAttempts - RateLimiter::attempts($key));

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }

    /**
     * Resolve request signature for rate limiting.
     *
     * @param  Request  $request  HTTP request
     * @param  string  $endpoint  Endpoint identifier
     * @return string Unique key for rate limiting
     */
    private function resolveRequestSignature(Request $request, string $endpoint): string
    {
        // Use IP address as identifier (can be extended with user ID if authenticated)
        $identifier = $request->ip() ?? 'unknown';

        return "adaptive-rate-limit:{$endpoint}:{$identifier}";
    }
}
