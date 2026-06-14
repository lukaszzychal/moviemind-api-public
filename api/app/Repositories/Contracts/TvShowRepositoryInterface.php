<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TvShow;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TvShowRepositoryInterface
{
    public function searchTvShows(?string $query, int $limit = 50): LengthAwarePaginator;

    public function findBySlugWithRelations(string $slug): ?TvShow;

    public function findAllByTitleSlug(string $baseSlug): Collection;

    public function findBySlugForJob(string $slug, ?string $existingId = null): ?TvShow;

    /**
     * @param  array<int, string>  $slugs
     * @param  array<int, string>  $include
     * @return Collection<int, TvShow>
     */
    public function findBySlugs(array $slugs, array $include = []): Collection;
}
