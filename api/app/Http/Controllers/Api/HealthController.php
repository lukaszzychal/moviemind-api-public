<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AiServiceSelector;
use App\Http\Controllers\Controller;
use App\Services\EntityVerificationServiceInterface;
use App\Services\OpenAiClientInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

class HealthController extends Controller
{
    public function __construct(
        private readonly OpenAiClientInterface $openAiClient,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService
    ) {}

    public function openAi(): JsonResponse
    {
        $result = $this->openAiClient->health();

        $success = (bool) $result['success'];
        $status = 200;

        if (! $success) {
            $status = array_key_exists('status', $result) ? (int) $result['status'] : 503;
        }

        return response()->json($result, $status);
    }

    /**
     * Check TMDb API health.
     *
     * @author MovieMind API Team
     */
    public function tmdb(): JsonResponse
    {
        $result = $this->tmdbVerificationService->health();

        $success = (bool) $result['success'];
        $status = 200;

        if (! $success) {
            $status = array_key_exists('status', $result) ? (int) $result['status'] : 503;
        }

        return response()->json($result, $status);
    }

    /**
     * Debug endpoint for service configuration inspection.
     * Protected by feature flag 'debug_endpoints' (default: disabled).
     *
     * Access: GET /api/v1/admin/debug/config
     * Requires: Feature flag 'debug_endpoints' must be enabled.
     */
    public function debugConfig(Request $request): JsonResponse
    {
        // Security: Check feature flag
        if (! Feature::active('debug_endpoints')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Debug endpoints are disabled. Enable feature flag "debug_endpoints" to access this endpoint.',
            ], 403);
        }

        // Collect configuration data
        // Note: Using env() directly for debug purposes to show raw .env values
        // @phpstan-ignore-next-line - Debug endpoint intentionally uses env() to show raw values
        $aiServiceEnv = env('AI_SERVICE');
        // @phpstan-ignore-next-line
        $openAiKey = env('OPENAI_API_KEY');
        $appEnv = config('app.env');

        $config = [
            'environment' => [
                'app_env' => $appEnv,
                'app_debug' => config('app.debug'),
                'ai_service_env' => $aiServiceEnv,
                'ai_service_config' => config('services.ai.service'),
                'ai_service_selector' => AiServiceSelector::getService(),
                'is_real' => AiServiceSelector::isReal(),
                'is_mock' => AiServiceSelector::isMock(),
            ],
            'openai' => [
                'api_key_set' => ! empty($openAiKey),
                'api_key_preview' => $openAiKey ? substr($openAiKey, 0, 10).'...' : null,
                'model' => config('services.openai.model'),
                'api_url' => config('services.openai.url'),
                'health_url' => config('services.openai.health_url'),
                'backoff_enabled' => config('services.openai.backoff.enabled'),
                'backoff_intervals' => config('services.openai.backoff.intervals'),
            ],
            'queue' => [
                'connection' => config('queue.default'),
                'redis_host' => config('database.redis.default.host'),
            ],
            'cache' => [
                'driver' => config('cache.default'),
            ],
            'database' => [
                'connection' => config('database.default'),
                'host' => config('database.connections.'.config('database.default').'.host'),
            ],
            'services' => [
                'openai_client_class' => get_class($this->openAiClient),
                'openai_client_interface' => \App\Services\OpenAiClientInterface::class,
            ],
            'endpoints' => [
                'movies' => [
                    'GET /api/v1/movies',
                    'GET /api/v1/movies/search',
                    'GET /api/v1/movies/{slug}',
                    'POST /api/v1/movies/bulk',
                    'GET /api/v1/movies/compare',
                    'GET /api/v1/movies/{slug}/related',
                    'GET /api/v1/movies/{slug}/collection',
                    'POST /api/v1/movies/{slug}/refresh',
                    'POST /api/v1/movies/{slug}/report',
                ],
                'people' => [
                    'GET /api/v1/people',
                    'GET /api/v1/people/search',
                    'GET /api/v1/people/{slug}',
                    'POST /api/v1/people/bulk',
                    'GET /api/v1/people/compare',
                    'GET /api/v1/people/{slug}/related',
                    'POST /api/v1/people/{slug}/refresh',
                    'POST /api/v1/people/{slug}/report',
                ],
                'tv_series' => [
                    'GET /api/v1/tv-series',
                    'GET /api/v1/tv-series/search',
                    'GET /api/v1/tv-series/{slug}',
                    'GET /api/v1/tv-series/compare',
                    'GET /api/v1/tv-series/{slug}/related',
                    'POST /api/v1/tv-series/{slug}/refresh',
                    'POST /api/v1/tv-series/{slug}/report',
                ],
                'tv_shows' => [
                    'GET /api/v1/tv-shows',
                    'GET /api/v1/tv-shows/search',
                    'GET /api/v1/tv-shows/{slug}',
                    'GET /api/v1/tv-shows/compare',
                    'GET /api/v1/tv-shows/{slug}/related',
                    'POST /api/v1/tv-shows/{slug}/refresh',
                    'POST /api/v1/tv-shows/{slug}/report',
                ],
                'generation' => [
                    'POST /api/v1/generate',
                    'GET /api/v1/jobs/{id}',
                ],
                'health' => [
                    'GET /api/v1/health/openai',
                    'GET /api/v1/health/tmdb',
                ],
            ],
            'timestamp' => now()->toIso8601String(),
            'note' => 'This endpoint is protected by the "debug_endpoints" feature flag. Disabled by default in production.',
        ];

        return response()->json($config, 200);
    }

    /**
     * Clear test data from database (STAGING ONLY - TEMPORARY).
     * Protected by admin basic auth and environment check.
     *
     * Access: POST /api/v1/admin/database/clear-test-data
     * Requires: Admin basic auth, staging environment
     */
    public function clearTestData(Request $request): JsonResponse
    {
        // Only allow in staging
        if (config('app.env') !== 'staging') {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'This endpoint is only available in staging environment.',
            ], 403);
        }

        try {
            \Illuminate\Support\Facades\Log::warning('Clearing test data', [
                'ip' => $request->ip(),
                'user' => $request->getUser(),
                'timestamp' => now()->toIso8601String(),
            ]);

            // Clear test data tables
            \Illuminate\Support\Facades\DB::table('movies')->delete();
            \Illuminate\Support\Facades\DB::table('movie_descriptions')->delete();
            \Illuminate\Support\Facades\DB::table('people')->delete();
            \Illuminate\Support\Facades\DB::table('person_bios')->delete();
            \Illuminate\Support\Facades\DB::table('tv_series')->delete();
            \Illuminate\Support\Facades\DB::table('tv_series_descriptions')->delete();
            \Illuminate\Support\Facades\DB::table('tv_shows')->delete();
            \Illuminate\Support\Facades\DB::table('tv_show_descriptions')->delete();

            // Clear cache
            \Illuminate\Support\Facades\Cache::flush();

            \Illuminate\Support\Facades\Log::info('Test data cleared', [
                'ip' => $request->ip(),
                'user' => $request->getUser(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test data cleared successfully.',
                'timestamp' => now()->toIso8601String(),
            ], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to clear test data', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user' => $request->getUser(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'error' => 'Failed to clear test data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
