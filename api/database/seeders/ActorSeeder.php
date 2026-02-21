<?php

namespace Database\Seeders;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Movie;
use App\Models\Person;
use App\Models\PersonBio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ActorSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production', 'staging')) {
            $this->command->warn('ActorSeeder: Skipping test data in production/staging environment');

            return;
        }

        $reeves = Person::firstOrCreate(
            ['slug' => Str::slug('Keanu Reeves')],
            [
                'name' => 'Keanu Reeves',
                'birth_date' => '1964-09-02',
                'birthplace' => 'Beirut, Lebanon',
            ]
        );

        if ($reeves->wasRecentlyCreated) {
            $bio = PersonBio::create([
                'person_id' => $reeves->id,
                'locale' => Locale::EN_US,
                'text' => 'Canadian actor known for The Matrix and John Wick franchises.',
                'context_tag' => ContextTag::MODERN,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => 'mock',
            ]);
            $reeves->update(['default_bio_id' => $bio->id]);
        }

        $matrix = Movie::where('slug', 'the-matrix-1999')->first();
        if ($matrix) {
            $matrix->people()->syncWithoutDetaching([
                $reeves->id => [
                    'role' => 'ACTOR',
                    'character_name' => 'Neo',
                    'job' => null,
                    'billing_order' => 1,
                ],
            ]);
        }
    }
}
