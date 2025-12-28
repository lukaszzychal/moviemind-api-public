<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReportStatus;
use App\Jobs\RegenerateTvShowDescriptionJob;
use App\Models\TvShowReport;
use App\Repositories\TvShowReportRepository;
use Illuminate\Support\Facades\Log;

/**
 * Action for verifying a TV show report and triggering regeneration.
 */
class VerifyTvShowReportAction
{
    public function __construct(
        private readonly TvShowReportRepository $reportRepository
    ) {}

    /**
     * Verify a TV show report and queue regeneration job.
     *
     * @param  string  $reportId  Report ID (UUID)
     * @return TvShowReport Verified report
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function handle(string $reportId): TvShowReport
    {
        $report = $this->reportRepository->findById($reportId);

        if ($report === null) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Report not found: {$reportId}");
        }

        // Update report status
        $report->update([
            'status' => ReportStatus::VERIFIED,
            'verified_at' => now(),
        ]);

        Log::info('TV show report verified', [
            'report_id' => $report->id,
            'tv_show_id' => $report->tv_show_id,
            'description_id' => $report->description_id,
        ]);

        // Queue regeneration job if description_id is provided
        if ($report->description_id !== null) {
            RegenerateTvShowDescriptionJob::dispatch(
                $report->tv_show_id,
                $report->description_id
            );

            Log::info('Regeneration job queued for verified report', [
                'report_id' => $report->id,
                'tv_show_id' => $report->tv_show_id,
                'description_id' => $report->description_id,
            ]);
        }

        return $report;
    }
}
