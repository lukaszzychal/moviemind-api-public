<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReportStatus;
use App\Jobs\RegenerateTvSeriesDescriptionJob;
use App\Models\TvSeriesReport;
use App\Repositories\TvSeriesReportRepository;
use Illuminate\Support\Facades\Log;

/**
 * Action for verifying a TV series report and triggering regeneration.
 */
class VerifyTvSeriesReportAction
{
    public function __construct(
        private readonly TvSeriesReportRepository $reportRepository
    ) {}

    /**
     * Verify a TV series report and queue regeneration job.
     *
     * @param  string  $reportId  Report ID (UUID)
     * @return TvSeriesReport Verified report
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function handle(string $reportId): TvSeriesReport
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

        Log::info('TV series report verified', [
            'report_id' => $report->id,
            'tv_series_id' => $report->tv_series_id,
            'description_id' => $report->description_id,
        ]);

        // Queue regeneration job if description_id is provided
        if ($report->description_id !== null) {
            RegenerateTvSeriesDescriptionJob::dispatch(
                $report->tv_series_id,
                $report->description_id
            );

            Log::info('Regeneration job queued for verified report', [
                'report_id' => $report->id,
                'tv_series_id' => $report->tv_series_id,
                'description_id' => $report->description_id,
            ]);
        }

        return $report;
    }
}
