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
        $reeves = Person::firstOrCreate(
            ['name' => 'Keanu Reeves'],
            [
                'slug' => Str::slug('Keanu Reeves'),
                'birth_date' => '1964-09-02',
                'birthplace' => 'Beirut, Lebanon',
            ]
        );

        $bio = PersonBio::firstOrCreate([
            'person_id' => $reeves->id,
            'locale' => Locale::EN_US,
            'context_tag' => ContextTag::MODERN,
        ], [
            'text' => 'Canadian actor known for The Matrix and John Wick franchises.',
            'origin' => DescriptionOrigin::GENERATED,
            'ai_model' => 'mock',
        ]);

        $reeves->update(['default_bio_id' => $bio->id]);

        // Link Keanu Reeves to The Matrix as an actor
        $matrix = Movie::where('title', 'The Matrix')->first();
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
