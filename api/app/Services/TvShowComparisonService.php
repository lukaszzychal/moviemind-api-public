<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TvShow;
use App\Repositories\TvShowRepository;

class TvShowComparisonService
{
    public function __construct(
        private readonly TvShowRepository $tvShowRepository
    ) {}

    /**
     * Compare two TV shows and return common elements and differences.
     *
     * @return array{
     *     tv_show1: array<string, mixed>,
     *     tv_show2: array<string, mixed>,
     *     comparison: array{
     *         common_genres: array<string>,
     *         common_people: array<int, array{person: array<string, mixed>, roles_in_tv_show1: array<string>, roles_in_tv_show2: array<string>}>,
     *         year_difference: int,
     *         similarity_score: float
     *     }
     * }
     */
    public function compare(string $slug1, string $slug2): array
    {
        $tvShow1 = $this->tvShowRepository->findBySlugWithRelations($slug1);
        $tvShow2 = $this->tvShowRepository->findBySlugWithRelations($slug2);

        if (! $tvShow1 || ! $tvShow2) {
            throw new \InvalidArgumentException('One or both TV shows not found');
        }

        // Load people if not already loaded
        if (! $tvShow1->relationLoaded('people')) {
            $tvShow1->load('people');
        }
        if (! $tvShow2->relationLoaded('people')) {
            $tvShow2->load('people');
        }

        $commonGenres = $this->findCommonGenres($tvShow1, $tvShow2);
        $commonPeople = $this->findCommonPeople($tvShow1, $tvShow2);
        $yearDifference = $this->calculateYearDifference($tvShow1, $tvShow2);
        $similarityScore = $this->calculateSimilarityScore($tvShow1, $tvShow2, $commonGenres, $commonPeople);

        $firstAirYear1 = $tvShow1->first_air_date ? (int) $tvShow1->first_air_date->format('Y') : null;
        $firstAirYear2 = $tvShow2->first_air_date ? (int) $tvShow2->first_air_date->format('Y') : null;

        return [
            'tv_show1' => [
                'id' => $tvShow1->id,
                'slug' => $tvShow1->slug,
                'title' => $tvShow1->title,
                'first_air_year' => $firstAirYear1,
                'show_type' => $tvShow1->show_type,
            ],
            'tv_show2' => [
                'id' => $tvShow2->id,
                'slug' => $tvShow2->slug,
                'title' => $tvShow2->title,
                'first_air_year' => $firstAirYear2,
                'show_type' => $tvShow2->show_type,
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
     * Find common genres between two TV shows.
     *
     * @return array<string>
     */
    private function findCommonGenres(TvShow $tvShow1, TvShow $tvShow2): array
    {
        /** @var array<string>|null $genres1 */
        $genres1 = $tvShow1->genres ?? [];
        /** @var array<string>|null $genres2 */
        $genres2 = $tvShow2->genres ?? [];

        if (! is_array($genres1) || ! is_array($genres2)) {
            return [];
        }

        return array_values(array_intersect($genres1, $genres2));
    }

    /**
     * Find common people between two TV shows with their roles.
     *
     * @return array<int, array{person: array<string, mixed>, roles_in_tv_show1: array<string>, roles_in_tv_show2: array<string>}>
     */
    private function findCommonPeople(TvShow $tvShow1, TvShow $tvShow2): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $people1 */
        $people1 = $tvShow1->getRelation('people');
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $people2 */
        $people2 = $tvShow2->getRelation('people');

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
            $person1Relation = $tvShow1->people()->where('people.id', $personId)->first();
            $person2Relation = $tvShow2->people()->where('people.id', $personId)->first();

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
                'roles_in_tv_show1' => $roles1,
                'roles_in_tv_show2' => $roles2,
            ];
        }

        return $result;
    }

    /**
     * Calculate year difference between two TV shows.
     */
    private function calculateYearDifference(TvShow $tvShow1, TvShow $tvShow2): int
    {
        $year1 = $tvShow1->first_air_date ? (int) $tvShow1->first_air_date->format('Y') : 0;
        $year2 = $tvShow2->first_air_date ? (int) $tvShow2->first_air_date->format('Y') : 0;

        if ($year1 === 0 || $year2 === 0) {
            return 0;
        }

        return abs($year1 - $year2);
    }

    /**
     * Calculate similarity score between two TV shows (0.0 to 1.0).
     *
     * @param  array<string>  $commonGenres
     * @param  array<int, array{person: array<string, mixed>, roles_in_tv_show1: array<string>, roles_in_tv_show2: array<string>}>  $commonPeople
     */
    private function calculateSimilarityScore(TvShow $tvShow1, TvShow $tvShow2, array $commonGenres, array $commonPeople): float
    {
        $score = 0.0;

        // Genre similarity (40% weight)
        $genres1 = is_array($tvShow1->genres) ? $tvShow1->genres : [];
        $genres2 = is_array($tvShow2->genres) ? $tvShow2->genres : [];
        $genres1Count = count($genres1);
        $genres2Count = count($genres2);
        if ($genres1Count > 0 && $genres2Count > 0) {
            $maxGenres = max($genres1Count, $genres2Count);
            $genreScore = count($commonGenres) / $maxGenres;
            $score += $genreScore * 0.4;
        }

        // People similarity (40% weight)
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $people1 */
        $people1 = $tvShow1->getRelation('people');
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $people2 */
        $people2 = $tvShow2->getRelation('people');
        $people1Count = $people1->count();
        $people2Count = $people2->count();
        if ($people1Count > 0 && $people2Count > 0) {
            $maxPeople = max($people1Count, $people2Count);
            $peopleScore = count($commonPeople) / $maxPeople;
            $score += $peopleScore * 0.4;
        }

        // Year proximity (20% weight) - closer years = higher score
        $yearDiff = $this->calculateYearDifference($tvShow1, $tvShow2);
        $maxYearDiff = 100; // Assume max 100 years difference
        $yearScore = max(0, 1 - ($yearDiff / $maxYearDiff));
        $score += $yearScore * 0.2;

        return round($score, 2);
    }
}
