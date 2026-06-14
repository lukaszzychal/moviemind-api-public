<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Movie;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MovieRepositoryInterface
{
    public function searchMovies(
        ?string $query,
        int $limit = 50,
        string|array|null $actor = null,
        ?string $director = null,
        ?int $year = null
    ): LengthAwarePaginator;

    public function findBySlugWithRelations(string $slug): ?Movie;

    public function findAllByTitleSlug(string $baseSlug): Collection;

    public function findBySlugForJob(string $slug, ?string $existingId = null): ?Movie;

    /**
     * @param  array<int, string>  $slugs
     * @param  array<int, string>  $include
     * @return Collection<int, Movie>
     */
    public function findBySlugs(array $slugs, array $include = []): Collection;
}
