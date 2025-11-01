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
        // Try to find a person from movies - check director first, then people (actors)
        $movies = $this->getJson('/api/v1/movies');
        $movies->assertOk();

        $personId = null;
        foreach ($movies->json('data') as $m) {
            // First try director (most common)
            if (! empty($m['director']['id'])) {
                $personId = $m['director']['id'];
                break;
            }
            // Fallback: try people (actors)
            if (! empty($m['people'][0]['id'])) {
                $personId = $m['people'][0]['id'];
                break;
            }
        }
        $this->assertNotNull($personId, 'Expected at least one person linked to movies (director or actor)');

        $res = $this->getJson('/api/v1/actors/'.$personId);
        $res->assertOk()
            ->assertJsonStructure(['id', 'name']);
    }
}
