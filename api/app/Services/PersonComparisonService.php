<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Person;
use App\Repositories\PersonRepository;

class PersonComparisonService
{
    public function __construct(
        private readonly PersonRepository $personRepository
    ) {}

    /**
     * Compare two people and return common elements and differences.
     *
     * @return array{
     *     person1: array<string, mixed>,
     *     person2: array<string, mixed>,
     *     comparison: array{
     *         common_movies: array<int, array{movie_id: string, movie_slug: string, movie_title: string, person1_role: string|null, person2_role: string|null}>,
     *         common_movies_count: int,
     *         birth_year_difference: int|null,
     *         similarity_score: float
     *     }
     * }
     */
    public function compare(string $slug1, string $slug2): array
    {
        $person1 = $this->personRepository->findBySlugWithRelations($slug1);
        $person2 = $this->personRepository->findBySlugWithRelations($slug2);

        if (! $person1 || ! $person2) {
            throw new \InvalidArgumentException('One or both people not found');
        }

        // Load movies with pivot data if not already loaded
        if (! $person1->relationLoaded('movies')) {
            $person1->load(['movies' => function ($query) {
                $query->withPivot('role');
            }]);
        }
        if (! $person2->relationLoaded('movies')) {
            $person2->load(['movies' => function ($query) {
                $query->withPivot('role');
            }]);
        }

        $commonMovies = $this->findCommonMovies($person1, $person2);
        $birthYearDifference = $this->calculateBirthYearDifference($person1, $person2);
        $similarityScore = $this->calculateSimilarityScore($person1, $person2, $commonMovies, $birthYearDifference);

        return [
            'person1' => [
                'id' => $person1->id,
                'slug' => $person1->slug,
                'name' => $person1->name,
                'birth_date' => $person1->birth_date?->format('Y-m-d'),
                'birthplace' => $person1->birthplace,
            ],
            'person2' => [
                'id' => $person2->id,
                'slug' => $person2->slug,
                'name' => $person2->name,
                'birth_date' => $person2->birth_date?->format('Y-m-d'),
                'birthplace' => $person2->birthplace,
            ],
            'comparison' => [
                'common_movies' => $commonMovies,
                'common_movies_count' => count($commonMovies),
                'birth_year_difference' => $birthYearDifference,
                'similarity_score' => $similarityScore, // Float (may be serialized as int 0 in JSON)
            ],
        ];
    }

    /**
     * Find common movies between two people with their roles.
     *
     * @return array<int, array{movie_id: string, movie_slug: string, movie_title: string, person1_role: string|null, person2_role: string|null}>
     */
    private function findCommonMovies(Person $person1, Person $person2): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Movie> $movies1 */
        $movies1 = $person1->getRelation('movies');
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Movie> $movies2 */
        $movies2 = $person2->getRelation('movies');

        $movies1Keyed = $movies1->keyBy('id');
        $movies2Keyed = $movies2->keyBy('id');

        $commonMovieIds = array_intersect($movies1Keyed->keys()->toArray(), $movies2Keyed->keys()->toArray());

        $result = [];
        foreach ($commonMovieIds as $movieId) {
            $movie1 = $movies1Keyed->get($movieId);
            $movie2 = $movies2Keyed->get($movieId);

            if (! $movie1 || ! $movie2) {
                continue;
            }

            // Get roles from pivot table (already loaded in relation)
            $pivot1 = $movie1->pivot;
            $pivot2 = $movie2->pivot;

            /** @var string|null $role1 */
            $role1 = $pivot1 ? (string) ($pivot1->role ?? null) : null;
            /** @var string|null $role2 */
            $role2 = $pivot2 ? (string) ($pivot2->role ?? null) : null;

            $result[] = [
                'movie_id' => $movie1->id,
                'movie_slug' => $movie1->slug,
                'movie_title' => $movie1->title,
                'person1_role' => $role1,
                'person2_role' => $role2,
            ];
        }

        return $result;
    }

    /**
     * Calculate birth year difference between two people.
     */
    private function calculateBirthYearDifference(Person $person1, Person $person2): ?int
    {
        if (! $person1->birth_date || ! $person2->birth_date) {
            return null;
        }

        $year1 = (int) $person1->birth_date->format('Y');
        $year2 = (int) $person2->birth_date->format('Y');

        return abs($year1 - $year2);
    }

    /**
     * Calculate similarity score between two people (0.0 to 1.0).
     *
     * @param  array<int, array{movie_id: string, movie_slug: string, movie_title: string, person1_role: string|null, person2_role: string|null}>  $commonMovies
     */
    private function calculateSimilarityScore(Person $person1, Person $person2, array $commonMovies, ?int $birthYearDifference): float
    {
        $score = 0.0;

        // Common movies similarity (60% weight)
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Movie> $movies1 */
        $movies1 = $person1->getRelation('movies');
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Movie> $movies2 */
        $movies2 = $person2->getRelation('movies');
        $movies1Count = $movies1->count();
        $movies2Count = $movies2->count();
        if ($movies1Count > 0 && $movies2Count > 0) {
            $maxMovies = max($movies1Count, $movies2Count);
            $moviesScore = count($commonMovies) / $maxMovies;
            $score += $moviesScore * 0.6;
        }

        // Birth year proximity (40% weight) - closer years = higher score
        if ($birthYearDifference !== null) {
            $maxYearDiff = 100; // Assume max 100 years difference
            $yearScore = max(0.0, 1.0 - ($birthYearDifference / $maxYearDiff));
            $score += $yearScore * 0.4;
        }

        // Ensure score is always a float (even if 0.0)
        // Round to 2 decimal places and ensure float type
        $rounded = round($score, 2);

        // Force float type - if rounded is 0, return 0.0 explicitly
        // Use abs() < 0.01 instead of === 0 to handle float comparison
        return abs($rounded) < 0.01 ? 0.0 : (float) $rounded;
    }
}
