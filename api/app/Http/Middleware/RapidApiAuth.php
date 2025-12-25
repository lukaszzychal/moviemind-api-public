<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for RapidAPI authentication using API keys.
 *
 * Verifies API keys from:
 * - Header: X-RapidAPI-Key
 * - Fallback: Authorization: Bearer {key}
 */
class RapidApiAuth
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract API key from headers
        $plaintextKey = $this->extractApiKey($request);

        if ($plaintextKey === null) {
            Log::warning('RapidAPI authentication failed - no API key provided', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->unauthorized('API key is required');
        }

        // Validate API key
        $apiKey = $this->apiKeyService->validateAndGetKey($plaintextKey);

        if ($apiKey === null) {
            Log::warning('RapidAPI authentication failed - invalid API key', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'key_prefix' => $this->apiKeyService->extractPrefix($plaintextKey),
            ]);

            return $this->unauthorized('Invalid or expired API key');
        }

        // Track usage (update last_used_at)
        $endpoint = $request->path();
        $this->apiKeyService->trackUsage($plaintextKey, $endpoint);

        // Add API key to request attributes for use in controllers
        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('api_key_id', $apiKey->id);

        Log::debug('RapidAPI authentication successful', [
            'api_key_id' => $apiKey->id,
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        return $next($request);
    }

    /**
     * Extract API key from request headers.
     *
     * Priority:
     * 1. X-RapidAPI-Key header
     * 2. Authorization: Bearer {key}
     *
     * @return string|null The plaintext API key, or null if not found
     */
    private function extractApiKey(Request $request): ?string
    {
        // Check X-RapidAPI-Key header first (RapidAPI standard)
        $rapidApiKey = $request->header('X-RapidAPI-Key');
        if ($rapidApiKey !== null && $rapidApiKey !== '') {
            return trim($rapidApiKey);
        }

        // Fallback to Authorization: Bearer {key}
        $authorization = $request->header('Authorization');
        if ($authorization !== null && str_starts_with($authorization, 'Bearer ')) {
            return trim(substr($authorization, 7)); // Remove "Bearer " prefix
        }

        return null;
    }

    /**
     * Return 401 Unauthorized response.
     */
    private function unauthorized(string $message = 'Unauthorized'): Response
    {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => $message,
        ], 401)->withHeaders([
            'WWW-Authenticate' => 'Bearer',
        ]);
    }
}
