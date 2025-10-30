<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActorsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_show_actor_returns_person_payload(): void
    {
        // seeded PeopleSeeder creates directors; ActorSeeder creates an actor + bio
        // We'll fetch first person id by hitting movies endpoint and pulling a person id from relations if needed
        $movies = $this->getJson('/api/v1/movies');
        $movies->assertOk();

        $personId = null;
        foreach ($movies->json('data') as $m) {
            if (!empty($m['people'][0]['id'])) { $personId = $m['people'][0]['id']; break; }
        }
        $this->assertNotNull($personId, 'Expected at least one person linked to movies');

        $res = $this->getJson('/api/v1/actors/'.$personId);
        $res->assertOk()
            ->assertJsonStructure(['id','name']);
    }
}


