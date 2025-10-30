<?php

namespace Database\Seeders;

use App\Models\Actor;
use App\Models\ActorBio;
use Illuminate\Database\Seeder;

class ActorSeeder extends Seeder
{
    public function run(): void
    {
        $reeves = Actor::create([
            'name' => 'Keanu Reeves',
            'birth_date' => '1964-09-02',
            'birthplace' => 'Beirut, Lebanon',
        ]);

        $bio = ActorBio::create([
            'actor_id' => $reeves->id,
            'locale' => 'en-US',
            'text' => 'Canadian actor known for The Matrix and John Wick franchises.',
            'context_tag' => 'modern',
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);
        $reeves->update(['default_bio_id' => $bio->id]);
    }
}


