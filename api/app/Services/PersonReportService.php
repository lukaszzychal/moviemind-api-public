<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\PersonReport;

/**
 * Service for managing person reports and calculating priority scores.
 */
class PersonReportService
{
    /**
     * Calculate priority score for a report.
     *
     * Formula: count(pending reports of same type for same person) * weight(type)
     *
     * @param  PersonReport  $report  Report to calculate score for
     * @return float Priority score
     */
    public function calculatePriorityScore(PersonReport $report): float
    {
        // Count pending reports of the same type for the same person
        $count = PersonReport::where('person_id', $report->person_id)
            ->where('type', $report->type)
            ->where('status', ReportStatus::PENDING)
            ->count();

        // Multiply by type weight
        return (float) ($count * $report->type->weight());
    }
}
