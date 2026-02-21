<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Movie;
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
    }

    /**
     * Movie from a distinct year so ?year=1985 returns at least one result (year-only use case).
     */
    private function seedYearOnlyFixture(): void
    {
        Movie::firstOrCreate(
            ['slug' => 'search-fixture-year-1985'],
            [
                'title' => 'Search Fixture Year 1985',
                'release_year' => 1985,
                'director' => 'Fixture Director',
            ]
        );
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
}
