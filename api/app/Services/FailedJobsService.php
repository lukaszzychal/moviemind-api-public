<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service for failed jobs monitoring and statistics.
 *
 * Provides methods to retrieve and analyze failed jobs from the database.
 */
class FailedJobsService
{
    /**
     * Get failed jobs with pagination.
     *
     * @param  int  $perPage  Items per page
     * @param  int  $page  Page number
     * @return array{data: array, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function getFailedJobs(int $perPage = 10, int $page = 1): array
    {
        $offset = ($page - 1) * $perPage;

        $total = DB::table('failed_jobs')->count();

        $jobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit($perPage)
            ->offset($offset)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload ?? '{}', true);
                $jobClass = $payload['displayName'] ?? 'Unknown';
                $exception = $this->parseException($job->exception ?? '');

                $failedAt = $job->failed_at;
                if ($failedAt instanceof \Carbon\Carbon || $failedAt instanceof \DateTime) {
                    $failedAtFormatted = $failedAt->format('Y-m-d H:i:s');
                } elseif (is_string($failedAt)) {
                    $failedAtFormatted = $failedAt;
                } else {
                    $failedAtFormatted = null;
                }

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'connection' => $job->connection,
                    'job' => $jobClass,
                    'exception' => $exception['message'],
                    'exception_class' => $exception['class'],
                    'failed_at' => $failedAtFormatted,
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
     * Get failed jobs filtered by queue.
     *
     * @param  string  $queue  Queue name
     * @param  int  $perPage  Items per page
     * @param  int  $page  Page number
     * @return array{data: array, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function getFailedJobsByQueue(string $queue, int $perPage = 10, int $page = 1): array
    {
        $offset = ($page - 1) * $perPage;

        $total = DB::table('failed_jobs')
            ->where('queue', $queue)
            ->count();

        $jobs = DB::table('failed_jobs')
            ->where('queue', $queue)
            ->orderByDesc('failed_at')
            ->limit($perPage)
            ->offset($offset)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload ?? '{}', true);
                $jobClass = $payload['displayName'] ?? 'Unknown';
                $exception = $this->parseException($job->exception ?? '');

                $failedAt = $job->failed_at;
                if ($failedAt instanceof \Carbon\Carbon || $failedAt instanceof \DateTime) {
                    $failedAtFormatted = $failedAt->format('Y-m-d H:i:s');
                } elseif (is_string($failedAt)) {
                    $failedAtFormatted = $failedAt;
                } else {
                    $failedAtFormatted = null;
                }

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'connection' => $job->connection,
                    'job' => $jobClass,
                    'exception' => $exception['message'],
                    'exception_class' => $exception['class'],
                    'failed_at' => $failedAtFormatted,
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
     * Get failed jobs filtered by date range.
     *
     * @param  string  $startDate  Start date (Y-m-d)
     * @param  string  $endDate  End date (Y-m-d)
     * @param  int  $perPage  Items per page
     * @param  int  $page  Page number
     * @return array{data: array, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function getFailedJobsByDateRange(string $startDate, string $endDate, int $perPage = 10, int $page = 1): array
    {
        $offset = ($page - 1) * $perPage;

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $total = DB::table('failed_jobs')
            ->whereBetween('failed_at', [$start, $end])
            ->count();

        $jobs = DB::table('failed_jobs')
            ->whereBetween('failed_at', [$start, $end])
            ->orderByDesc('failed_at')
            ->limit($perPage)
            ->offset($offset)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload ?? '{}', true);
                $jobClass = $payload['displayName'] ?? 'Unknown';
                $exception = $this->parseException($job->exception ?? '');

                $failedAt = $job->failed_at;
                if ($failedAt instanceof \Carbon\Carbon || $failedAt instanceof \DateTime) {
                    $failedAtFormatted = $failedAt->format('Y-m-d H:i:s');
                } elseif (is_string($failedAt)) {
                    $failedAtFormatted = $failedAt;
                } else {
                    $failedAtFormatted = null;
                }

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'connection' => $job->connection,
                    'job' => $jobClass,
                    'exception' => $exception['message'],
                    'exception_class' => $exception['class'],
                    'failed_at' => $failedAtFormatted,
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
     * Get failure statistics aggregated by queue and time.
     *
     * @return array{
     *     total_failed: int,
     *     by_queue: array<string, int>,
     *     by_hour: array<string, int>
     * }
     */
    public function getFailureStatistics(): array
    {
        $totalFailed = DB::table('failed_jobs')->count();

        $byQueue = DB::table('failed_jobs')
            ->select('queue', DB::raw('COUNT(*) as count'))
            ->groupBy('queue')
            ->pluck('count', 'queue')
            ->toArray();

        // Group by hour (last 24 hours) – PostgreSQL
        $byHour = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->selectRaw("TO_CHAR(failed_at, 'YYYY-MM-DD HH24:00:00') as hour, COUNT(*) as count")
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        return [
            'total_failed' => $totalFailed,
            'by_queue' => $byQueue,
            'by_hour' => $byHour,
        ];
    }

    /**
     * Get failure rate percentage.
     *
     * @return array{
     *     failure_rate_percent: float,
     *     total_jobs: int,
     *     failed_jobs: int
     * }
     */
    public function getFailureRate(): array
    {
        $failedJobs = DB::table('failed_jobs')->count();

        // Get total jobs from ai_jobs (completed + failed)
        $totalAiJobs = DB::table('ai_jobs')->count();
        $completedAiJobs = DB::table('ai_jobs')->where('status', 'DONE')->count();
        $failedAiJobs = DB::table('ai_jobs')->where('status', 'FAILED')->count();

        // Total jobs = completed + failed (from ai_jobs) + failed_jobs
        $totalJobs = $completedAiJobs + $failedAiJobs + $failedJobs;
        $totalFailed = $failedAiJobs + $failedJobs;

        $failureRate = $totalJobs > 0 ? round(($totalFailed / $totalJobs) * 100, 2) : 0.0;

        return [
            'failure_rate_percent' => $failureRate,
            'total_jobs' => $totalJobs,
            'failed_jobs' => $totalFailed,
        ];
    }

    /**
     * Parse exception string to extract message and class.
     *
     * @param  string  $exception  Exception string
     * @return array{message: string, class: string|null}
     */
    private function parseException(string $exception): array
    {
        if (empty($exception)) {
            return ['message' => 'Unknown error', 'class' => null];
        }

        // Try to extract exception class and message
        $lines = explode("\n", $exception);
        $firstLine = $lines[0];

        // Pattern: "ExceptionClass: Exception message"
        if (preg_match('/^([^:]+):\s*(.+)$/', $firstLine, $matches)) {
            return [
                'class' => trim($matches[1]),
                'message' => trim($matches[2]),
            ];
        }

        // Fallback: use first line as message
        return [
            'class' => null,
            'message' => trim($firstLine) ?: 'Unknown error',
        ];
    }
}
