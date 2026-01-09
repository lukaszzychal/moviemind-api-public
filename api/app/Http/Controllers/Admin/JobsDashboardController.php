<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FailedJobsService;
use App\Services\QueueJobsDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for jobs dashboard endpoints.
 *
 * Provides statistics and monitoring for queue jobs and failed jobs.
 */
class JobsDashboardController extends Controller
{
    public function __construct(
        private readonly QueueJobsDashboardService $queueJobsService,
        private readonly FailedJobsService $failedJobsService
    ) {}

    /**
     * Get overview statistics for all queues.
     */
    public function overview(Request $request): JsonResponse
    {
        $stats = $this->queueJobsService->getOverview();

        return response()->json($stats);
    }

    /**
     * Get statistics grouped by queue name.
     */
    public function byQueue(Request $request): JsonResponse
    {
        $stats = $this->queueJobsService->getByQueue();

        return response()->json($stats);
    }

    /**
     * Get recent jobs with pagination.
     */
    public function recent(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $page = (int) $request->query('page', 1);

        $result = $this->queueJobsService->getRecentJobs($perPage, $page);

        return response()->json($result);
    }

    /**
     * Get failed jobs with pagination.
     */
    public function failed(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $page = (int) $request->query('page', 1);
        $queue = $request->query('queue');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if ($queue) {
            $result = $this->failedJobsService->getFailedJobsByQueue($queue, $perPage, $page);
        } elseif ($startDate && $endDate) {
            $result = $this->failedJobsService->getFailedJobsByDateRange($startDate, $endDate, $perPage, $page);
        } else {
            $result = $this->failedJobsService->getFailedJobs($perPage, $page);
        }

        return response()->json($result);
    }

    /**
     * Get failed jobs statistics.
     */
    public function failedStats(Request $request): JsonResponse
    {
        $stats = $this->failedJobsService->getFailureStatistics();

        return response()->json($stats);
    }

    /**
     * Get average processing times by queue.
     */
    public function processingTimes(Request $request): JsonResponse
    {
        $stats = $this->queueJobsService->getProcessingTimes();

        return response()->json($stats);
    }
}
