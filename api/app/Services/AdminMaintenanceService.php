<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Maintenance operations exposed through the admin API (used by the MCP server).
 */
class AdminMaintenanceService
{
    public const CACHE_SCOPE_ALL = 'all';

    /**
     * Retry all failed queue jobs (queue:retry all).
     *
     * @return array{status: string, command: string, scope: string, exit_code: int, output: string}
     */
    public function retryFailedJobs(): array
    {
        $exitCode = Artisan::call('queue:retry', ['id' => ['all']]);

        return [
            'status' => 'ok',
            'command' => 'queue:retry',
            'scope' => 'all',
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
        ];
    }

    /**
     * Forget a single cache key, or clear the whole application cache for "all".
     *
     * @return array<string, string|int|bool>
     */
    public function clearCache(string $cacheKey): array
    {
        if ($cacheKey === '' || $cacheKey === self::CACHE_SCOPE_ALL) {
            $exitCode = Artisan::call('cache:clear');

            return [
                'status' => 'ok',
                'command' => 'cache:clear',
                'scope' => 'all',
                'cache_key' => self::CACHE_SCOPE_ALL,
                'exit_code' => $exitCode,
                'output' => trim(Artisan::output()),
            ];
        }

        return [
            'status' => 'ok',
            'scope' => 'key',
            'cache_key' => $cacheKey,
            'cleared' => Cache::forget($cacheKey),
        ];
    }
}
