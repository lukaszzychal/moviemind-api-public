<?php

declare(strict_types=1);

namespace App\Services;

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
    public function calculatePriorityScore(\App\Models\TvShowReport $report): float
    {
        // Count pending reports of the same type for the same tv show
        $count = \App\Models\TvShowReport::where('tv_show_id', $report->tv_show_id)
            ->where('type', $report->type)
            ->where('status', \App\Enums\ReportStatus::PENDING)
            ->count();

        // Multiply by type weight
        return (float) ($count * $report->type->weight());
    }

    public function createReport(\App\Models\TvShow $tvShow, array $validated): \App\Models\TvShowReport
    {
        $report = \App\Models\TvShowReport::create([
            'tv_show_id' => $tvShow->id,
            'description_id' => $validated['description_id'] ?? null,
            'type' => $validated['type'],
            'message' => $validated['message'],
            'suggested_fix' => $validated['suggested_fix'] ?? null,
            'status' => \App\Enums\ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        $priorityScore = $this->calculatePriorityScore($report);
        $report->update(['priority_score' => $priorityScore]);

        \App\Models\TvShowReport::where('tv_show_id', $tvShow->id)
            ->where('type', $report->type)
            ->where('status', \App\Enums\ReportStatus::PENDING)
            ->where('id', '!=', $report->id)
            ->update(['priority_score' => $priorityScore]);

        return $report;
    }
}
