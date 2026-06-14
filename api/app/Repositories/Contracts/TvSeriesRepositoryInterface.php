<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TvSeries;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TvSeriesRepositoryInterface
{
    public function searchTvSeries(?string $query, int $limit = 50): LengthAwarePaginator;

    public function findBySlugWithRelations(string $slug): ?TvSeries;

    public function findAllByTitleSlug(string $baseSlug): Collection;

    public function findBySlugForJob(string $slug, ?string $existingId = null): ?TvSeries;

    /**
     * @param  array<int, string>  $slugs
     * @param  array<int, string>  $include
     * @return Collection<int, TvSeries>
     */
    public function findBySlugs(array $slugs, array $include = []): Collection;
}
