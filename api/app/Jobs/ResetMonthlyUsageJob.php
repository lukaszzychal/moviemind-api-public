<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ApiUsage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to reset monthly usage counters.
 *
 * This job should be scheduled to run on the 1st day of each month
 * to reset usage tracking for the new month.
 */
class ResetMonthlyUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * Note: This job doesn't actually delete usage records, as they are
     * needed for analytics. Instead, it ensures that the monthly tracking
     * works correctly by relying on the 'month' field in api_usage table.
     * The month field is automatically set to the current month when
     * tracking requests, so no manual reset is needed.
     *
     * However, this job can be used for cleanup of old records if needed.
     */
    public function handle(): void
    {
        Log::info('ResetMonthlyUsageJob: Monthly usage tracking resets automatically via month field');

        // Optional: Clean up old usage records (older than 12 months)
        $cutoffDate = now()->subMonths(12);
        $deleted = ApiUsage::where('created_at', '<', $cutoffDate)->delete();

        if ($deleted > 0) {
            Log::info("ResetMonthlyUsageJob: Cleaned up {$deleted} old usage records");
        }
    }
}
