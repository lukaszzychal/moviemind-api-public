<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\TvSeriesReport;

/**
 * Service for managing TV series reports and calculating priority scores.
 */
class TvSeriesReportService
{
    /**
     * Calculate priority score for a report.
     *
     * Formula: count(pending reports of same type for same tv series) * weight(type)
     *
     * @param  TvSeriesReport  $report  Report to calculate score for
     * @return float Priority score
     */
    public function calculatePriorityScore(TvSeriesReport $report): float
    {
        // Count pending reports of the same type for the same TV series
        $count = TvSeriesReport::where('tv_series_id', $report->tv_series_id)
            ->where('type', $report->type)
            ->where('status', ReportStatus::PENDING)
            ->count();

        // Multiply by type weight
        return (float) ($count * $report->type->weight());
    }
}
