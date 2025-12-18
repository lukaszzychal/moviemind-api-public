<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReportStatus;
use App\Jobs\RegenerateMovieDescriptionJob;
use App\Models\MovieReport;
use App\Repositories\MovieReportRepository;
use Illuminate\Support\Facades\Log;

/**
 * Action for verifying a movie report and triggering regeneration.
 */
class VerifyMovieReportAction
{
    public function __construct(
        private readonly MovieReportRepository $reportRepository
    ) {}

    /**
     * Verify a movie report and queue regeneration job.
     *
     * @param  string  $reportId  Report ID (UUID)
     * @return MovieReport Verified report
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function handle(string $reportId): MovieReport
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

        Log::info('Movie report verified', [
            'report_id' => $report->id,
            'movie_id' => $report->movie_id,
            'description_id' => $report->description_id,
        ]);

        // Queue regeneration job if description_id is provided
        if ($report->description_id !== null) {
            RegenerateMovieDescriptionJob::dispatch(
                $report->movie_id,
                $report->description_id
            );

            Log::info('Regeneration job queued for verified report', [
                'report_id' => $report->id,
                'movie_id' => $report->movie_id,
                'description_id' => $report->description_id,
            ]);
        }

        return $report;
    }
}
