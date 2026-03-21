<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RelationshipType;
use App\Models\Movie;
use App\Models\MovieRelationship;
use App\Models\Person;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ensures DB has data for every search use case so manual testing and
 * automated tests get predictable results. See docs/qa/SEARCH_USE_CASES_AND_FIXTURES.md.
 */
class SearchFixturesSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production', 'staging')) {
            $this->command->warn('SearchFixturesSeeder: Skipping test data in production/staging');

            return;
        }

        $this->seedYearOnlyFixture();
        $this->seedMultipleActorsForMatrix();
        $this->seedBadBoysDisambiguation();
        $this->seedRelatedMovies();
    }

    /**
     * Movie from a distinct year so ?year=1985 returns at least one result (year-only use case).
     */
    private function seedYearOnlyFixture(): void
    {
        $movie = Movie::firstOrCreate(
            ['slug' => 'search-fixture-year-1985'],
            [
                'title' => 'Search Fixture Year 1985',
                'release_year' => 1985,
                'director' => 'Fixture Director',
            ]
        );

        $director = Person::firstOrCreate(
            ['slug' => Str::slug('Fixture Director')],
            ['name' => 'Fixture Director']
        );

        $movie->people()->syncWithoutDetaching([
            $director->id => ['role' => 'DIRECTOR', 'job' => 'Director', 'billing_order' => 1],
        ]);
    }

    /**
     * Second actor on The Matrix so ?actor[]=Keanu&actor[]=Laurence returns Matrix (multiple-actors use case).
     */
    private function seedMultipleActorsForMatrix(): void
    {
        $matrix = Movie::where('title', 'The Matrix')->first();
        if (! $matrix) {
            return;
        }

        $laurence = Person::firstOrCreate(
            ['slug' => Str::slug('Laurence Fishburne')],
            ['name' => 'Laurence Fishburne']
        );

        $matrix->people()->syncWithoutDetaching([
            $laurence->id => [
                'role' => 'ACTOR',
                'character_name' => 'Morpheus',
                'job' => null,
                'billing_order' => 2,
            ],
        ]);
    }

    /**
     * Bad Boys (1995) and Bad Boys II (2003) for disambiguation Scenario 5.
     * Ensures GET /movies/search?q=bad+boys returns both and
     * GET /movies/bad-boys?slug=bad-boys-ii-2003 returns the movie (no generation queued).
     */
    private function seedBadBoysDisambiguation(): void
    {
        $bb1 = Movie::firstOrCreate(
            ['slug' => 'bad-boys-1995'],
            [
                'title' => 'Bad Boys',
                'release_year' => 1995,
                'director' => 'Michael Bay',
            ]
        );
        $bb2 = Movie::firstOrCreate(
            ['slug' => 'bad-boys-ii-2003'],
            [
                'title' => 'Bad Boys II',
                'release_year' => 2003,
                'director' => 'Michael Bay',
            ]
        );

        $bay = Person::firstOrCreate(
            ['slug' => Str::slug('Michael Bay')],
            ['name' => 'Michael Bay']
        );

        foreach ([$bb1, $bb2] as $movie) {
            $movie->people()->syncWithoutDetaching([
                $bay->id => ['role' => 'DIRECTOR', 'job' => 'Director', 'billing_order' => 1],
            ]);
        }
    }

    /**
     * One related-movie link (The Matrix <-> Inception) so GET /movies/the-matrix-1999/related
     * returns at least one result. See Scenario 8 and TC-MOVIE-006.
     */
    private function seedRelatedMovies(): void
    {
        $matrix = Movie::where('slug', 'the-matrix-1999')->first();
        $inception = Movie::where('slug', 'inception-2010')->first();
        if (! $matrix || ! $inception) {
            return;
        }

        MovieRelationship::firstOrCreate(
            [
                'movie_id' => $matrix->id,
                'related_movie_id' => $inception->id,
            ],
            [
                'relationship_type' => RelationshipType::SAME_UNIVERSE,
                'order' => 1,
            ]
        );
    }
}
