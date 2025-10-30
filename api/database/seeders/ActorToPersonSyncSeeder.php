<?php

namespace Database\Seeders;

use App\Models\Actor;
use App\Models\ActorBio;
use App\Models\Person;
use App\Models\PersonBio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ActorToPersonSyncSeeder extends Seeder
{
    public function run(): void
    {
        Actor::query()->orderBy('id')->chunk(100, function ($actors) {
            foreach ($actors as $actor) {
                $person = Person::firstOrCreate(
                    ['name' => $actor->name],
                    [
                        'slug' => Str::slug($actor->name),
                        'birth_date' => $actor->birth_date,
                        'birthplace' => $actor->birthplace,
                    ]
                );

                $actorBios = ActorBio::where('actor_id', $actor->id)->get();
                foreach ($actorBios as $ab) {
                    $pb = PersonBio::firstOrCreate([
                        'person_id' => $person->id,
                        'locale' => $ab->locale,
                        'context_tag' => $ab->context_tag,
                    ], [
                        'text' => $ab->text,
                        'origin' => $ab->origin,
                        'ai_model' => $ab->ai_model,
                    ]);
                    if ($actor->default_bio_id === $ab->id) {
                        $person->update(['default_bio_id' => $pb->id]);
                    }
                }
            }
        });
    }
}
