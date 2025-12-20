<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use App\Repositories\MovieRepository;

class MovieComparisonService
{
    public function __construct(
        private readonly MovieRepository $movieRepository
    ) {}

    /**
     * Compare two movies and return common elements and differences.
     *
     * @return array{
     *     movie1: array<string, mixed>,
     *     movie2: array<string, mixed>,
     *     comparison: array{
     *         common_genres: array<string>,
     *         common_people: array<int, array{person: array<string, mixed>, roles_in_movie1: array<string>, roles_in_movie2: array<string>}>,
     *         year_difference: int,
     *         similarity_score: float
     *     }
     * }
     */
    public function compare(string $slug1, string $slug2): array
    {
        $movie1 = $this->movieRepository->findBySlugWithRelations($slug1);
        $movie2 = $this->movieRepository->findBySlugWithRelations($slug2);

        if (! $movie1 || ! $movie2) {
            throw new \InvalidArgumentException('One or both movies not found');
        }

        // Load genres and people if not already loaded
        if (! $movie1->relationLoaded('genres')) {
            $movie1->load('genres');
        }
        if (! $movie2->relationLoaded('genres')) {
            $movie2->load('genres');
        }
        if (! $movie1->relationLoaded('people')) {
            $movie1->load('people');
        }
        if (! $movie2->relationLoaded('people')) {
            $movie2->load('people');
        }

        $commonGenres = $this->findCommonGenres($movie1, $movie2);
        $commonPeople = $this->findCommonPeople($movie1, $movie2);
        $yearDifference = abs($movie1->release_year - $movie2->release_year);
        $similarityScore = $this->calculateSimilarityScore($movie1, $movie2, $commonGenres, $commonPeople);

        return [
            'movie1' => [
                'id' => $movie1->id,
                'slug' => $movie1->slug,
                'title' => $movie1->title,
                'release_year' => $movie1->release_year,
                'director' => $movie1->director,
            ],
            'movie2' => [
                'id' => $movie2->id,
                'slug' => $movie2->slug,
                'title' => $movie2->title,
                'release_year' => $movie2->release_year,
                'director' => $movie2->director,
            ],
            'comparison' => [
                'common_genres' => $commonGenres,
                'common_people' => $commonPeople,
                'year_difference' => $yearDifference,
                'similarity_score' => $similarityScore,
            ],
        ];
    }

    /**
     * Find common genres between two movies.
     *
     * @return array<string>
     */
    private function findCommonGenres(Movie $movie1, Movie $movie2): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Genre> $genres1 */
        $genres1 = $movie1->getRelation('genres');
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Genre> $genres2 */
        $genres2 = $movie2->getRelation('genres');

        $genres1Names = $genres1->pluck('name')->toArray();
        $genres2Names = $genres2->pluck('name')->toArray();

        return array_values(array_intersect($genres1Names, $genres2Names));
    }

    /**
     * Find common people between two movies with their roles.
     *
     * @return array<int, array{person: array<string, mixed>, roles_in_movie1: array<string>, roles_in_movie2: array<string>}>
     */
    private function findCommonPeople(Movie $movie1, Movie $movie2): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $people1 */
        $people1 = $movie1->getRelation('people');
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $people2 */
        $people2 = $movie2->getRelation('people');

        $people1Keyed = $people1->keyBy('id');
        $people2Keyed = $people2->keyBy('id');

        $commonPeopleIds = array_intersect($people1Keyed->keys()->toArray(), $people2Keyed->keys()->toArray());

        $result = [];
        foreach ($commonPeopleIds as $personId) {
            $person1 = $people1Keyed->get($personId);
            $person2 = $people2Keyed->get($personId);

            if (! $person1 || ! $person2) {
                continue;
            }

            // Get roles from pivot table
            $person1Relation = $movie1->people()->where('people.id', $personId)->first();
            $person2Relation = $movie2->people()->where('people.id', $personId)->first();

            $pivot1 = $person1Relation?->pivot;
            $pivot2 = $person2Relation?->pivot;

            $role1 = $pivot1 ? (string) $pivot1->getAttribute('role') : null;
            $role2 = $pivot2 ? (string) $pivot2->getAttribute('role') : null;

            $roles1 = $role1 !== null ? [$role1] : [];
            $roles2 = $role2 !== null ? [$role2] : [];

            $result[] = [
                'person' => [
                    'id' => $person1->id,
                    'slug' => $person1->slug,
                    'name' => $person1->name,
                ],
                'roles_in_movie1' => $roles1,
                'roles_in_movie2' => $roles2,
            ];
        }

        return $result;
    }

    /**
     * Calculate similarity score between two movies (0.0 to 1.0).
     *
     * @param  array<string>  $commonGenres
     * @param  array<int, array{person: array<string, mixed>, roles_in_movie1: array<string>, roles_in_movie2: array<string>}>  $commonPeople
     */
    private function calculateSimilarityScore(Movie $movie1, Movie $movie2, array $commonGenres, array $commonPeople): float
    {
        $score = 0.0;

        // Genre similarity (40% weight)
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Genre> $genres1 */
        $genres1 = $movie1->getRelation('genres');
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Genre> $genres2 */
        $genres2 = $movie2->getRelation('genres');
        $genres1Count = $genres1->count();
        $genres2Count = $genres2->count();
        if ($genres1Count > 0 && $genres2Count > 0) {
            $maxGenres = max($genres1Count, $genres2Count);
            $genreScore = count($commonGenres) / $maxGenres;
            $score += $genreScore * 0.4;
        }

        // People similarity (40% weight)
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $people1 */
        $people1 = $movie1->getRelation('people');
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $people2 */
        $people2 = $movie2->getRelation('people');
        $people1Count = $people1->count();
        $people2Count = $people2->count();
        if ($people1Count > 0 && $people2Count > 0) {
            $maxPeople = max($people1Count, $people2Count);
            $peopleScore = count($commonPeople) / $maxPeople;
            $score += $peopleScore * 0.4;
        }

        // Year proximity (20% weight) - closer years = higher score
        $yearDiff = abs($movie1->release_year - $movie2->release_year);
        $maxYearDiff = 100; // Assume max 100 years difference
        $yearScore = max(0, 1 - ($yearDiff / $maxYearDiff));
        $score += $yearScore * 0.2;

        return round($score, 2);
    }
}
