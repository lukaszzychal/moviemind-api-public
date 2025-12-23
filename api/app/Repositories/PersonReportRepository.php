<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ReportStatus;
use App\Models\PersonReport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PersonReportRepository
{
    /**
     * Get all reports with optional filtering and sorting.
     *
     * @param  array{status?: string, priority?: string}  $filters
     * @return LengthAwarePaginator<int, PersonReport>|Collection<int, PersonReport>
     */
    public function getAll(array $filters = [], int $perPage = 50): LengthAwarePaginator|Collection
    {
        $query = PersonReport::with(['person', 'bio'])
            ->orderBy('priority_score', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by status
        if (isset($filters['status']) && ReportStatus::isValid($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by priority (high = >= 3.0, medium = >= 1.0, low = < 1.0)
        if (isset($filters['priority'])) {
            match ($filters['priority']) {
                'high' => $query->where('priority_score', '>=', 3.0),
                'medium' => $query->where('priority_score', '>=', 1.0)->where('priority_score', '<', 3.0),
                'low' => $query->where('priority_score', '<', 1.0),
                default => null,
            };
        }

        if ($perPage > 0) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Find report by ID.
     */
    public function findById(string $id): ?PersonReport
    {
        return PersonReport::with(['person', 'bio'])->find($id);
    }
}
