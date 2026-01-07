<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Laravel\Pennant\Feature;

class InstanceController extends Controller
{
    /**
     * Get status of all instances for Modular Monolith scaling.
     *
     * Access: GET /api/v1/admin/instances
     * Returns: List of all instances with their status and active feature flags
     */
    public function index(): JsonResponse
    {
        // Get list of instances from environment or config
        // Format: INSTANCE_URLS=api-1:8000,api-2:8000,api-3:8000
        $instanceUrls = $this->getInstanceUrls();

        $instances = [];

        foreach ($instanceUrls as $instanceId => $url) {
            $instances[$instanceId] = $this->getInstanceStatus($instanceId, $url);
        }

        // Add current instance
        // @phpstan-ignore-next-line - Instance ID is instance-specific and cannot be cached
        $currentInstanceId = env('INSTANCE_ID', 'current');
        $instances[$currentInstanceId] = $this->getCurrentInstanceStatus();

        return response()->json([
            'instances' => $instances,
            'total_instances' => count($instances),
            'healthy_instances' => collect($instances)->where('status', 'healthy')->count(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get status of a specific instance.
     *
     * Access: GET /api/v1/admin/instances/{instanceId}
     */
    public function show(string $instanceId): JsonResponse
    {
        // Check if it's current instance
        // @phpstan-ignore-next-line - Instance ID is instance-specific and cannot be cached
        $currentInstanceId = env('INSTANCE_ID', 'current');
        if ($instanceId === $currentInstanceId || $instanceId === 'current') {
            return response()->json($this->getCurrentInstanceStatus());
        }

        // Get instance URL
        $instanceUrls = $this->getInstanceUrls();
        if (! isset($instanceUrls[$instanceId])) {
            return response()->json([
                'error' => 'Instance not found',
                'instance_id' => $instanceId,
            ], 404);
        }

        return response()->json($this->getInstanceStatus($instanceId, $instanceUrls[$instanceId]));
    }

    /**
     * Get list of instance URLs from environment or config.
     *
     * @return array<string, string> Format: ['instance-id' => 'http://host:port']
     */
    private function getInstanceUrls(): array
    {
        // Option 1: From environment variable
        // Format: INSTANCE_URLS=api-1:http://api-1:8000,api-2:http://api-2:8000
        // @phpstan-ignore-next-line - Instance URLs are instance-specific and cannot be cached
        $instanceUrlsEnv = env('INSTANCE_URLS', '');
        if (! empty($instanceUrlsEnv)) {
            $urls = [];
            $pairs = explode(',', $instanceUrlsEnv);
            foreach ($pairs as $pair) {
                $parts = explode(':', $pair, 2);
                if (count($parts) === 2) {
                    $urls[trim($parts[0])] = trim($parts[1]);
                }
            }

            return $urls;
        }

        // Option 2: From config file
        $configUrls = config('instances.urls', []);
        if (! empty($configUrls)) {
            return $configUrls;
        }

        // Default: empty (only current instance will be shown)
        return [];
    }

    /**
     * Get status of a remote instance.
     */
    private function getInstanceStatus(string $instanceId, string $url): array
    {
        try {
            $response = Http::timeout(2)->get("{$url}/api/v1/health/instance");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'instance_id' => $instanceId,
                    'status' => $data['status'] ?? 'healthy',
                    'features' => $data['features'] ?? [],
                    'last_check' => now()->toIso8601String(),
                    'url' => $url,
                ];
            }

            return [
                'instance_id' => $instanceId,
                'status' => 'unhealthy',
                'error' => 'Health check failed',
                'last_check' => now()->toIso8601String(),
                'url' => $url,
            ];
        } catch (\Exception $e) {
            return [
                'instance_id' => $instanceId,
                'status' => 'unreachable',
                'error' => $e->getMessage(),
                'last_check' => now()->toIso8601String(),
                'url' => $url,
            ];
        }
    }

    /**
     * Get status of current instance.
     */
    private function getCurrentInstanceStatus(): array
    {
        // @phpstan-ignore-next-line - Instance ID is instance-specific and cannot be cached
        $instanceId = env('INSTANCE_ID', 'current');
        $activeFeatures = [];

        // Get all feature flags from config
        $flags = config('pennant.flags', []);

        foreach ($flags as $name => $config) {
            $activeFeatures[$name] = Feature::active($name);
        }

        return [
            'instance_id' => $instanceId,
            'status' => 'healthy',
            'features' => $activeFeatures,
            'last_check' => now()->toIso8601String(),
            'url' => 'current',
        ];
    }
}
