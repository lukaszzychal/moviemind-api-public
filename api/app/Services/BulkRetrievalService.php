<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BulkRetrievalService
{
    /**
     * Retrieve multiple entities by slugs, map them to resources, and calculate missing slugs.
     *
     * @param  mixed  $repository  Repository with findBySlugs(array $slugs, array $include): Collection
     * @param  array<string>  $slugs  Requested slugs
     * @param  array<string>  $include  Relations to include
     * @param  callable  $resourceMapper  Function that converts Model to array/resource
     * @return array{
     *     data: array,
     *     not_found: array<string>,
     *     count: int,
     *     requested_count: int
     * }
     */
    public function retrieve(
        mixed $repository,
        array $slugs,
        array $include,
        callable $resourceMapper
    ): array {
        // Find entities using the repository
        /** @var Collection $entities */
        $entities = $repository->findBySlugs($slugs, $include);

        // Map entities to resources using the provided mapper
        $data = $entities->map($resourceMapper)->toArray();

        // Calculate which slugs were not found
        $foundSlugs = $entities->pluck('slug')->toArray();
        $notFound = array_values(array_diff($slugs, $foundSlugs));

        return [
            'data' => $data,
            'not_found' => $notFound,
            'count' => count($data),
            'requested_count' => count($slugs),
        ];
    }
}
