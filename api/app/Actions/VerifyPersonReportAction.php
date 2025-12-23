<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReportStatus;
use App\Jobs\RegeneratePersonBioJob;
use App\Models\PersonReport;
use App\Repositories\PersonReportRepository;
use Illuminate\Support\Facades\Log;

/**
 * Action for verifying a person report and triggering regeneration.
 */
class VerifyPersonReportAction
{
    public function __construct(
        private readonly PersonReportRepository $reportRepository
    ) {}

    /**
     * Verify a person report and queue regeneration job.
     *
     * @param  string  $reportId  Report ID (UUID)
     * @return PersonReport Verified report
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function handle(string $reportId): PersonReport
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

        Log::info('Person report verified', [
            'report_id' => $report->id,
            'person_id' => $report->person_id,
            'bio_id' => $report->bio_id,
        ]);

        // Queue regeneration job if bio_id is provided
        if ($report->bio_id !== null) {
            RegeneratePersonBioJob::dispatch(
                $report->person_id,
                $report->bio_id
            );

            Log::info('Regeneration job queued for verified report', [
                'report_id' => $report->id,
                'person_id' => $report->person_id,
                'bio_id' => $report->bio_id,
            ]);
        }

        return $report;
    }
}
