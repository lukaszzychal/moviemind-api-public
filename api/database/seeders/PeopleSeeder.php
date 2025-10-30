<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Person;
use Illuminate\Database\Seeder;

class PeopleSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = Movie::where('title', 'The Matrix')->first();
        $inception = Movie::where('title', 'Inception')->first();

        if ($matrix) {
            $wachowskis = Person::firstOrCreate(['name' => 'The Wachowskis']);
            $matrix->people()->syncWithoutDetaching([
                $wachowskis->id => ['role' => 'DIRECTOR', 'job' => 'Director', 'billing_order' => 1],
            ]);
        }

        if ($inception) {
            $nolan = Person::firstOrCreate(['name' => 'Christopher Nolan']);
            $inception->people()->syncWithoutDetaching([
                $nolan->id => ['role' => 'DIRECTOR', 'job' => 'Director', 'billing_order' => 1],
            ]);
        }
    }
}


