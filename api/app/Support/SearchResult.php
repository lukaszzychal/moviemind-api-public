<?php

declare(strict_types=1);

namespace App\Support;

/**
 * DTO for search results combining local and external (TMDB) results.
 */
class SearchResult
{
    /**
     * @param  array<int, array<string, mixed>>  $results
     * @param  string  $matchType  'exact', 'ambiguous', 'partial', 'none'
     * @param  float|null  $confidence  Confidence score (0.0 - 1.0)
     * @param  int|null  $currentPage  Current page number (for pagination)
     * @param  int|null  $perPage  Items per page (for pagination)
     * @param  int|null  $totalPages  Total number of pages (for pagination)
     */
    public function __construct(
        public readonly array $results,
        public readonly int $total,
        public readonly int $localCount,
        public readonly int $externalCount,
        public readonly string $matchType,
        public readonly ?float $confidence = null,
        public readonly ?int $currentPage = null,
        public readonly ?int $perPage = null,
        public readonly ?int $totalPages = null
    ) {}

    /**
     * Check if search found exact match (single result with high confidence).
     */
    public function isExactMatch(): bool
    {
        return $this->matchType === 'exact' && $this->total === 1;
    }

    /**
     * Check if search found multiple results (ambiguous).
     */
    public function isAmbiguous(): bool
    {
        return $this->matchType === 'ambiguous' && $this->total > 1;
    }

    /**
     * Check if search found no results.
     */
    public function isEmpty(): bool
    {
        return $this->matchType === 'none' && $this->total === 0;
    }

    /**
     * Get HTTP status code for this search result.
     */
    public function getHttpStatusCode(): int
    {
        return match ($this->matchType) {
            'exact' => 200,
            'ambiguous' => 300, // Multiple Choices
            'partial' => 200,
            'none' => 404,
            default => 200,
        };
    }

    /**
     * Convert to array for JSON response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'results' => $this->results,
            'total' => $this->total,
            'local_count' => $this->localCount,
            'external_count' => $this->externalCount,
            'match_type' => $this->matchType,
            'confidence' => $this->confidence,
        ];

        // Add pagination metadata if pagination is used
        if ($this->currentPage !== null && $this->perPage !== null && $this->totalPages !== null) {
            $data['pagination'] = [
                'current_page' => $this->currentPage,
                'per_page' => $this->perPage,
                'total_pages' => $this->totalPages,
                'total' => $this->total,
                'has_next_page' => $this->currentPage < $this->totalPages,
                'has_previous_page' => $this->currentPage > 1,
            ];
        }

        return $data;
    }
}
