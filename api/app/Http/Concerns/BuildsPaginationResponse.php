<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Builds standardized pagination metadata for API responses.
 */
trait BuildsPaginationResponse
{
    protected function buildPaginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'has_next_page' => $paginator->hasMorePages(),
            'has_previous_page' => $paginator->currentPage() > 1,
        ];
    }
}
