<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Service for adaptive rate limiting based on system load.
 *
 * Calculates dynamic rate limits based on:
 * - CPU load (40% weight)
 * - Queue size (40% weight)
 * - Active jobs (20% weight)
 */
class AdaptiveRateLimiter
{
    /**
     * Get the maximum number of requests per minute for an endpoint.
     *
     * @param  string  $endpoint  Endpoint identifier (search, generate, report)
     * @return int Maximum requests per minute
     */
    public function getMaxAttempts(string $endpoint): int
    {
        $loadFactor = $this->calculateLoadFactor();
        $loadLevel = $this->getLoadLevel($loadFactor);

        $defaultRate = (int) config("rate-limiting.defaults.{$endpoint}", 100);
        $minRate = (int) config("rate-limiting.min.{$endpoint}", 20);
        $reductionFactor = (float) config("rate-limiting.reduction_factors.{$loadLevel}", 1.0);

        // Calculate adjusted rate
        if ($reductionFactor === 0.0) {
            // Use minimum rate for critical load
            $adjustedRate = $minRate;
        } else {
            // Apply reduction factor
            $adjustedRate = (int) ($defaultRate * $reductionFactor);
            // Ensure it's not below minimum
            $adjustedRate = max($adjustedRate, $minRate);
        }

        // Log rate limit change if enabled
        if (config('rate-limiting.logging.enabled', true)) {
            $this->logRateLimitChange($endpoint, $loadFactor, $loadLevel, $defaultRate, $adjustedRate);
        }

        return $adjustedRate;
    }

    /**
     * Calculate load factor based on system metrics.
     *
     * @return float Load factor: 0.0 (no load) - 1.0+ (high load)
     */
    public function calculateLoadFactor(): float
    {
        $cpuComponent = 0.0;
        $queueComponent = 0.0;
        $activeJobsComponent = 0.0;

        // CPU Load (40% weight)
        if (config('rate-limiting.cpu.enabled', true)) {
            $cpuLoad = $this->getCpuLoad();
            $cpuWeight = (float) config('rate-limiting.weights.cpu', 0.4);
            $cpuComponent = $cpuLoad * $cpuWeight;
        }

        // Queue Size (40% weight)
        if (config('rate-limiting.queue.enabled', true)) {
            $queueRatio = $this->getQueueRatio();
            $queueWeight = (float) config('rate-limiting.weights.queue', 0.4);
            $queueComponent = $queueRatio * $queueWeight;
        }

        // Active Jobs (20% weight)
        if (config('rate-limiting.active_jobs.enabled', true)) {
            $activeJobsRatio = $this->getActiveJobsRatio();
            $activeJobsWeight = (float) config('rate-limiting.weights.active_jobs', 0.2);
            $activeJobsComponent = $activeJobsRatio * $activeJobsWeight;
        }

        // Load factor: weighted sum
        $loadFactor = $cpuComponent + $queueComponent + $activeJobsComponent;

        // Normalize to 0.0 - 1.5 (can exceed 1.0 under extreme load)
        return min(1.5, max(0.0, $loadFactor));
    }

    /**
     * Get CPU load normalized to 0.0 - 1.0.
     *
     * @return float Normalized CPU load (0.0 = no load, 1.0 = full load)
     */
    private function getCpuLoad(): float
    {
        if (! function_exists('sys_getloadavg')) {
            // Fallback: return 0 if CPU monitoring is not available
            return 0.0;
        }

        $load = sys_getloadavg();
        if ($load === false) {
            return 0.0;
        }

        $load1min = (float) $load[0]; // 1-minute load average
        $cpuCores = (int) config('rate-limiting.cpu.cores', 4);
        $maxLoad = (float) config('rate-limiting.cpu.max_load', 4.0);

        // Normalize: load / cores, capped at maxLoad
        $normalizedLoad = min(1.0, $load1min / max(1, $cpuCores));

        // Further normalize by maxLoad if needed
        if ($maxLoad > 0) {
            $normalizedLoad = min(1.0, $normalizedLoad * ($cpuCores / $maxLoad));
        }

        return max(0.0, min(1.0, $normalizedLoad));
    }

    /**
     * Get queue size ratio normalized to 0.0 - 1.0.
     *
     * @return float Normalized queue ratio (0.0 = empty, 1.0 = full)
     */
    private function getQueueRatio(): float
    {
        try {
            $connection = config('rate-limiting.queue.connection', 'redis');
            $queueName = config('rate-limiting.queue.queue_name', 'default');
            $maxSize = (int) config('rate-limiting.queue.max_size', 1000);

            // Get queue size from Redis
            $queueKey = "queues:{$queueName}";
            $queueSize = (int) Redis::connection($connection)->llen($queueKey);

            // Normalize to 0.0 - 1.0
            $ratio = min(1.0, (float) ($queueSize / max(1, $maxSize)));

            return max(0.0, $ratio);
        } catch (\Exception $e) {
            // Fallback: return 0 if Redis is unavailable
            Log::warning('Failed to get queue size for rate limiting', [
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Get active jobs ratio normalized to 0.0 - 1.0.
     *
     * @return float Normalized active jobs ratio (0.0 = no active jobs, 1.0 = max active jobs)
     */
    private function getActiveJobsRatio(): float
    {
        try {
            $maxJobs = (int) config('rate-limiting.active_jobs.max_jobs', 100);

            // Count reserved jobs from Redis (Horizon stores active jobs as reserved keys)
            $connection = config('rate-limiting.queue.connection', 'redis');
            $reservedKeys = Redis::connection($connection)->keys('horizon:*:reserved');
            $activeJobsCount = count($reservedKeys);

            // Normalize to 0.0 - 1.0
            $ratio = min(1.0, (float) ($activeJobsCount / max(1, $maxJobs)));

            return max(0.0, $ratio);
        } catch (\Exception $e) {
            // Fallback: return 0 if monitoring is unavailable
            Log::warning('Failed to get active jobs count for rate limiting', [
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Get load level based on load factor.
     *
     * @param  float  $loadFactor  Calculated load factor
     * @return string Load level: low, medium, high, critical
     */
    private function getLoadLevel(float $loadFactor): string
    {
        $thresholds = config('rate-limiting.thresholds', [
            'low' => 0.3,
            'medium' => 0.5,
            'high' => 0.7,
            'critical' => 0.9,
        ]);

        if ($loadFactor >= ($thresholds['critical'] ?? 0.9)) {
            return 'critical';
        }
        if ($loadFactor >= ($thresholds['high'] ?? 0.7)) {
            return 'high';
        }
        if ($loadFactor >= ($thresholds['medium'] ?? 0.5)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Log rate limit change.
     *
     * @param  string  $endpoint  Endpoint identifier
     * @param  float  $loadFactor  Calculated load factor
     * @param  string  $loadLevel  Load level (low, medium, high, critical)
     * @param  int  $defaultRate  Default rate limit
     * @param  int  $adjustedRate  Adjusted rate limit
     */
    private function logRateLimitChange(
        string $endpoint,
        float $loadFactor,
        string $loadLevel,
        int $defaultRate,
        int $adjustedRate
    ): void {
        $channel = config('rate-limiting.logging.channel', 'default');
        $level = config('rate-limiting.logging.level', 'info');

        Log::channel($channel)->log($level, 'Adaptive rate limit adjusted', [
            'endpoint' => $endpoint,
            'load_factor' => round($loadFactor, 3),
            'load_level' => $loadLevel,
            'default_rate' => $defaultRate,
            'adjusted_rate' => $adjustedRate,
            'reduction' => $defaultRate - $adjustedRate,
            'reduction_percent' => $defaultRate > 0 ? round((($defaultRate - $adjustedRate) / $defaultRate) * 100, 1) : 0,
        ]);
    }
}
