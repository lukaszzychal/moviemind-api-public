<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to add request-id and correlation-id to all HTTP requests.
 *
 * - request-id: Unique identifier for each request (UUIDv4)
 * - correlation-id: Identifier to track related requests across services (UUIDv4)
 *   - If provided by client in X-Correlation-ID header, uses that value
 *   - Otherwise, generates a new UUIDv4
 *
 * Both IDs are:
 * - Added to response headers (X-Request-ID, X-Correlation-ID)
 * - Added to log context for traceability
 * - Available via request attributes for use in controllers/services
 *
 * @author MovieMind API Team
 */
class RequestIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate or retrieve request-id (always unique per request)
        $requestId = $this->generateRequestId($request);

        // Generate or retrieve correlation-id (can be passed from client)
        $correlationId = $this->getOrGenerateCorrelationId($request);

        // Store in request attributes for use in controllers/services
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('correlation_id', $correlationId);

        // Add to log context for all subsequent log entries
        Log::withContext([
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
        ]);

        // Process request
        $response = $next($request);

        // Add headers to response
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }

    /**
     * Generate a unique request-id for this request.
     * Uses UUIDv4 (random, universal identifier).
     */
    private function generateRequestId(Request $request): string
    {
        // If client provides X-Request-ID, use it (for testing/debugging)
        $clientRequestId = $request->header('X-Request-ID');
        if ($clientRequestId && Str::isUuid($clientRequestId)) {
            return $clientRequestId;
        }

        // Otherwise, generate new UUIDv4
        return (string) Str::uuid();
    }

    /**
     * Get correlation-id from request header or generate new one.
     * Uses UUIDv4 (random, universal identifier).
     *
     * Correlation-ID is used to track related requests across services.
     * If a client sends X-Correlation-ID, we use it to maintain the chain.
     */
    private function getOrGenerateCorrelationId(Request $request): string
    {
        $clientCorrelationId = $request->header('X-Correlation-ID');

        // If client provides valid UUID, use it
        if ($clientCorrelationId && Str::isUuid($clientCorrelationId)) {
            return $clientCorrelationId;
        }

        // Otherwise, generate new UUIDv4
        return (string) Str::uuid();
    }
}
