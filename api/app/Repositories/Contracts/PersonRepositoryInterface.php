<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Person;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PersonRepositoryInterface
{
    public function searchPeople(?string $query, int $limit = 50): LengthAwarePaginator;

    public function findBySlugWithRelations(string $slug): ?Person;

    public function findAllByNameSlug(string $baseSlug): Collection;

    public function findBySlugForJob(string $slug, ?string $existingId = null): ?Person;

    /**
     * @param  array<int, string>  $slugs
     * @param  array<int, string>  $include
     * @return Collection<int, Person>
     */
    public function findBySlugs(array $slugs, array $include = []): Collection;
}
