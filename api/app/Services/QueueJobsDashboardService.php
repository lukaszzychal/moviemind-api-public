<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Laravel\Horizon\Horizon;

/**
 * Service for queue jobs dashboard statistics.
 *
 * Provides statistics about queue jobs from both Horizon API and database.
 */
class QueueJobsDashboardService
{
    /**
     * Get overview statistics for all queues.
     *
     * @return array{
     *     total_pending: int,
     *     total_processing: int,
     *     total_completed: int,
     *     total_failed: int,
     *     queues: array
     * }
     */
    public function getOverview(): array
    {
        // Get statistics from database
        $pending = DB::table('jobs')
            ->whereNull('reserved_at')
            ->count();

        $processing = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->count();

        $failed = DB::table('failed_jobs')->count();

        // Get Horizon status if available
        $horizonStatus = $this->getHorizonStatus();

        // Get AI jobs statistics
        $aiJobsStats = $this->getAiJobsStatistics();

        // Aggregate completed from AI jobs (DONE status)
        $completed = $aiJobsStats['by_status']['DONE'] ?? 0;

        // Get queue breakdown
        $queues = $this->getByQueue();

        return [
            'total_pending' => $pending,
            'total_processing' => $processing,
            'total_completed' => $completed,
            'total_failed' => $failed,
            'horizon_status' => $horizonStatus,
            'queues' => $queues,
            'ai_jobs' => $aiJobsStats,
        ];
    }

    /**
     * Get statistics grouped by queue name.
     *
     * @return array<int, array{queue: string, pending: int, processing: int, failed: int}>
     */
    public function getByQueue(): array
    {
        $pendingByQueue = DB::table('jobs')
            ->whereNull('reserved_at')
            ->select('queue', DB::raw('COUNT(*) as count'))
            ->groupBy('queue')
            ->get()
            ->keyBy('queue');

        $processingByQueue = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->select('queue', DB::raw('COUNT(*) as count'))
            ->groupBy('queue')
            ->get()
            ->keyBy('queue');

        $failedByQueue = DB::table('failed_jobs')
            ->select('queue', DB::raw('COUNT(*) as count'))
            ->groupBy('queue')
            ->get()
            ->keyBy('queue');

        // Merge all queues
        $allQueues = collect([$pendingByQueue, $processingByQueue, $failedByQueue])
            ->flatMap(fn ($collection) => $collection->keys())
            ->unique()
            ->values();

        return $allQueues->map(function (string $queue) use ($pendingByQueue, $processingByQueue, $failedByQueue) {
            return [
                'queue' => $queue,
                'pending' => (int) ($pendingByQueue->get($queue)?->count ?? 0),
                'processing' => (int) ($processingByQueue->get($queue)?->count ?? 0),
                'failed' => (int) ($failedByQueue->get($queue)?->count ?? 0),
            ];
        })->values()->toArray();
    }

    /**
     * Get recent jobs with pagination.
     *
     * @param  int  $perPage  Items per page
     * @param  int  $page  Page number
     * @return array{data: array, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function getRecentJobs(int $perPage = 10, int $page = 1): array
    {
        $offset = ($page - 1) * $perPage;

        $total = DB::table('jobs')->count();

        $jobs = DB::table('jobs')
            ->orderByDesc('created_at')
            ->limit($perPage)
            ->offset($offset)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload ?? '{}', true);
                $jobClass = $payload['displayName'] ?? 'Unknown';

                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'job' => $jobClass,
                    'attempts' => $job->attempts,
                    'status' => $job->reserved_at ? 'processing' : 'pending',
                    'created_at' => date('Y-m-d H:i:s', $job->created_at),
                    'available_at' => date('Y-m-d H:i:s', $job->available_at),
                ];
            })
            ->toArray();

        $lastPage = (int) ceil($total / $perPage);

        return [
            'data' => $jobs,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
        ];
    }

    /**
     * Get average processing times by queue.
     *
     * @return array{by_queue: array, overall_avg: float|null}
     */
    public function getProcessingTimes(): array
    {
        // For now, return structure - processing times would need to be tracked
        // This could be enhanced with job completion tracking
        $queues = DB::table('jobs')
            ->select('queue')
            ->distinct()
            ->pluck('queue');

        $byQueue = $queues->mapWithKeys(function (string $queue) {
            return [$queue => [
                'avg_seconds' => null, // Would need completion tracking
                'min_seconds' => null,
                'max_seconds' => null,
                'sample_size' => 0,
            ]];
        })->toArray();

        return [
            'by_queue' => $byQueue,
            'overall_avg' => null,
        ];
    }

    /**
     * Get AI jobs statistics aggregated by status and entity type.
     *
     * @return array{
     *     by_status: array<string, int>,
     *     by_entity_type: array<string, int>,
     *     total: int
     * }
     */
    public function getAiJobsStatistics(): array
    {
        $byStatus = DB::table('ai_jobs')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byEntityType = DB::table('ai_jobs')
            ->select('entity_type', DB::raw('COUNT(*) as count'))
            ->groupBy('entity_type')
            ->pluck('count', 'entity_type')
            ->toArray();

        $total = DB::table('ai_jobs')->count();

        return [
            'by_status' => [
                'PENDING' => (int) ($byStatus['PENDING'] ?? 0),
                'DONE' => (int) ($byStatus['DONE'] ?? 0),
                'FAILED' => (int) ($byStatus['FAILED'] ?? 0),
            ],
            'by_entity_type' => $byEntityType,
            'total' => $total,
        ];
    }

    /**
     * Get Horizon status if available.
     *
     * @return array<string, mixed>|null
     */
    private function getHorizonStatus(): ?array
    {
        try {
            // Check if Horizon is running by checking Redis for Horizon keys
            $redis = app('redis')->connection();
            $horizonKeys = $redis->keys('horizon:*');

            if (empty($horizonKeys)) {
                return null;
            }

            // Get basic Horizon info from Redis
            $masters = $redis->get('horizon:masters') ?: '[]';
            $mastersData = json_decode($masters, true) ?? [];

            return [
                'active' => ! empty($mastersData),
                'paused' => false, // Would need to check pause status
                'masters_count' => count($mastersData),
            ];
        } catch (\Exception $e) {
            // Horizon not available or not configured
            return null;
        }
    }
}
