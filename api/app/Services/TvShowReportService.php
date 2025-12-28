<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\TvShowReport;

/**
 * Service for managing TV show reports and calculating priority scores.
 */
class TvShowReportService
{
    /**
     * Calculate priority score for a report.
     *
     * Formula: count(pending reports of same type for same tv show) * weight(type)
     *
     * @param  TvShowReport  $report  Report to calculate score for
     * @return float Priority score
     */
    public function calculatePriorityScore(TvShowReport $report): float
    {
        // Count pending reports of the same type for the same TV show
        $count = TvShowReport::where('tv_show_id', $report->tv_show_id)
            ->where('type', $report->type)
            ->where('status', ReportStatus::PENDING)
            ->count();

        // Multiply by type weight
        return (float) ($count * $report->type->weight());
    }
}
