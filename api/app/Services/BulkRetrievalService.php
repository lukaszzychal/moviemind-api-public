<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MovieRepository;
use App\Repositories\PersonRepository;
use Illuminate\Database\Eloquent\Model;

/**
 * Service for bulk retrieval of entities (movies, people, etc.).
 * Handles retrieval of multiple entities by slugs with optional relations.
 */
class BulkRetrievalService
{
    /**
     * Retrieve multiple entities by slugs.
     *
     * @param  MovieRepository|PersonRepository  $repository  Repository to use for retrieval
     * @param  array<int, string>  $slugs  Array of slugs to retrieve
     * @param  array<int, string>  $include  Relations to include (descriptions, people, genres, etc.)
     * @param  callable  $formatter  Callback to format each entity (receives Model, returns array)
     * @return array{data: array<int, array>, not_found: array<int, string>, count: int, requested_count: int}
     */
    public function retrieve(
        MovieRepository|PersonRepository $repository,
        array $slugs,
        array $include,
        callable $formatter
    ): array {
        // Deduplicate slugs while preserving order
        $uniqueSlugs = array_values(array_unique($slugs));
        $requestedCount = count($slugs);

        if (empty($uniqueSlugs)) {
            return [
                'data' => [],
                'not_found' => [],
                'count' => 0,
                'requested_count' => $requestedCount,
            ];
        }

        // Fetch entities using repository
        $entities = $repository->findBySlugs($uniqueSlugs, $include);

        // Create a map for quick lookup
        $entitiesBySlug = $entities->keyBy('slug');

        // Format found entities and track not found slugs
        $data = [];
        $notFound = [];

        foreach ($uniqueSlugs as $slug) {
            $entity = $entitiesBySlug->get($slug);

            if ($entity === null) {
                $notFound[] = $slug;
            } else {
                $data[] = $formatter($entity);
            }
        }

        return [
            'data' => $data,
            'not_found' => $notFound,
            'count' => count($data),
            'requested_count' => $requestedCount,
        ];
    }
}
