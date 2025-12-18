<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\MovieReport;

/**
 * Service for managing movie reports and calculating priority scores.
 */
class MovieReportService
{
    /**
     * Calculate priority score for a report.
     *
     * Formula: count(pending reports of same type for same movie) * weight(type)
     *
     * @param  MovieReport  $report  Report to calculate score for
     * @return float Priority score
     */
    public function calculatePriorityScore(MovieReport $report): float
    {
        // Count pending reports of the same type for the same movie
        $count = MovieReport::where('movie_id', $report->movie_id)
            ->where('type', $report->type)
            ->where('status', ReportStatus::PENDING)
            ->count();

        // Multiply by type weight
        return (float) ($count * $report->type->weight());
    }
}
