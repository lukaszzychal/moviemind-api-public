<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TvSeries;
use App\Repositories\TvSeriesRepository;

class TvSeriesComparisonService
{
    public function __construct(
        private readonly TvSeriesRepository $tvSeriesRepository
    ) {}

    /**
     * Compare two TV series and return common elements and differences.
     *
     * @return array{
     *     tv_series1: array<string, mixed>,
     *     tv_series2: array<string, mixed>,
     *     comparison: array{
     *         common_genres: array<string>,
     *         common_people: array<int, array{person: array<string, mixed>, roles_in_tv_series1: array<string>, roles_in_tv_series2: array<string>}>,
     *         year_difference: int,
     *         similarity_score: float
     *     }
     * }
     */
    public function compare(string $slug1, string $slug2): array
    {
        $tvSeries1 = $this->tvSeriesRepository->findBySlugWithRelations($slug1);
        $tvSeries2 = $this->tvSeriesRepository->findBySlugWithRelations($slug2);

        if (! $tvSeries1 || ! $tvSeries2) {
            throw new \InvalidArgumentException('One or both TV series not found');
        }

        // Load people if not already loaded
        if (! $tvSeries1->relationLoaded('people')) {
            $tvSeries1->load('people');
        }
        if (! $tvSeries2->relationLoaded('people')) {
            $tvSeries2->load('people');
        }

        $commonGenres = $this->findCommonGenres($tvSeries1, $tvSeries2);
        $commonPeople = $this->findCommonPeople($tvSeries1, $tvSeries2);
        $yearDifference = $this->calculateYearDifference($tvSeries1, $tvSeries2);
        $similarityScore = $this->calculateSimilarityScore($tvSeries1, $tvSeries2, $commonGenres, $commonPeople);

        $firstAirYear1 = $tvSeries1->first_air_date ? (int) $tvSeries1->first_air_date->format('Y') : null;
        $firstAirYear2 = $tvSeries2->first_air_date ? (int) $tvSeries2->first_air_date->format('Y') : null;

        return [
            'tv_series1' => [
                'id' => $tvSeries1->id,
                'slug' => $tvSeries1->slug,
                'title' => $tvSeries1->title,
                'first_air_year' => $firstAirYear1,
                'number_of_seasons' => $tvSeries1->number_of_seasons,
            ],
            'tv_series2' => [
                'id' => $tvSeries2->id,
                'slug' => $tvSeries2->slug,
                'title' => $tvSeries2->title,
                'first_air_year' => $firstAirYear2,
                'number_of_seasons' => $tvSeries2->number_of_seasons,
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
     * Find common genres between two TV series.
     *
     * @return array<string>
     */
    private function findCommonGenres(TvSeries $tvSeries1, TvSeries $tvSeries2): array
    {
        /** @var array<string>|null $genres1 */
        $genres1 = $tvSeries1->genres ?? [];
        /** @var array<string>|null $genres2 */
        $genres2 = $tvSeries2->genres ?? [];

        if (! is_array($genres1) || ! is_array($genres2)) {
            return [];
        }

        return array_values(array_intersect($genres1, $genres2));
    }

    /**
     * Find common people between two TV series with their roles.
     *
     * @return array<int, array{person: array<string, mixed>, roles_in_tv_series1: array<string>, roles_in_tv_series2: array<string>}>
     */
    private function findCommonPeople(TvSeries $tvSeries1, TvSeries $tvSeries2): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $people1 */
        $people1 = $tvSeries1->getRelation('people');
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $people2 */
        $people2 = $tvSeries2->getRelation('people');

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
            $person1Relation = $tvSeries1->people()->where('people.id', $personId)->first();
            $person2Relation = $tvSeries2->people()->where('people.id', $personId)->first();

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
                'roles_in_tv_series1' => $roles1,
                'roles_in_tv_series2' => $roles2,
            ];
        }

        return $result;
    }

    /**
     * Calculate year difference between two TV series.
     */
    private function calculateYearDifference(TvSeries $tvSeries1, TvSeries $tvSeries2): int
    {
        $year1 = $tvSeries1->first_air_date ? (int) $tvSeries1->first_air_date->format('Y') : 0;
        $year2 = $tvSeries2->first_air_date ? (int) $tvSeries2->first_air_date->format('Y') : 0;

        if ($year1 === 0 || $year2 === 0) {
            return 0;
        }

        return abs($year1 - $year2);
    }

    /**
     * Calculate similarity score between two TV series (0.0 to 1.0).
     *
     * @param  array<string>  $commonGenres
     * @param  array<int, array{person: array<string, mixed>, roles_in_tv_series1: array<string>, roles_in_tv_series2: array<string>}>  $commonPeople
     */
    private function calculateSimilarityScore(TvSeries $tvSeries1, TvSeries $tvSeries2, array $commonGenres, array $commonPeople): float
    {
        $score = 0.0;

        // Genre similarity (40% weight)
        /** @var array<string>|null $genres1Raw */
        $genres1Raw = $tvSeries1->genres;
        /** @var array<string>|null $genres2Raw */
        $genres2Raw = $tvSeries2->genres;
        $genres1 = is_array($genres1Raw) ? $genres1Raw : [];
        $genres2 = is_array($genres2Raw) ? $genres2Raw : [];
        $genres1Count = count($genres1);
        $genres2Count = count($genres2);
        if ($genres1Count > 0 && $genres2Count > 0) {
            $maxGenres = max($genres1Count, $genres2Count);
            $genreScore = count($commonGenres) / $maxGenres;
            $score += $genreScore * 0.4;
        }

        // People similarity (40% weight)
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $people1 */
        $people1 = $tvSeries1->getRelation('people');
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $people2 */
        $people2 = $tvSeries2->getRelation('people');
        $people1Count = $people1->count();
        $people2Count = $people2->count();
        if ($people1Count > 0 && $people2Count > 0) {
            $maxPeople = max($people1Count, $people2Count);
            $peopleScore = count($commonPeople) / $maxPeople;
            $score += $peopleScore * 0.4;
        }

        // Year proximity (20% weight) - closer years = higher score
        $yearDiff = $this->calculateYearDifference($tvSeries1, $tvSeries2);
        $maxYearDiff = 100; // Assume max 100 years difference
        $yearScore = max(0, 1 - ($yearDiff / $maxYearDiff));
        $score += $yearScore * 0.2;

        return round($score, 2);
    }
}
