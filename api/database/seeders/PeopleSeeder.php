<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Person;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PeopleSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production', 'staging')) {
            $this->command->warn('PeopleSeeder: Skipping test data in production/staging environment');

            return;
        }

        $matrix = Movie::where('title', 'The Matrix')->first();
        $inception = Movie::where('title', 'Inception')->first();

        if ($matrix) {
            $wachowskis = Person::firstOrCreate(
                ['slug' => Str::slug('The Wachowskis')],
                ['name' => 'The Wachowskis']
            );
            $matrix->people()->syncWithoutDetaching([
                $wachowskis->id => ['role' => 'DIRECTOR', 'job' => 'Director', 'billing_order' => 1],
            ]);
        }

        if ($inception) {
            $nolan = Person::firstOrCreate(
                ['slug' => Str::slug('Christopher Nolan')],
                ['name' => 'Christopher Nolan']
            );
            $inception->people()->syncWithoutDetaching([
                $nolan->id => ['role' => 'DIRECTOR', 'job' => 'Director', 'billing_order' => 1],
            ]);
        }
    }
}
