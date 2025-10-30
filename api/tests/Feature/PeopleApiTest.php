<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeopleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_show_person_returns_payload(): void
    {
        $movies = $this->getJson('/api/v1/movies');
        $movies->assertOk();

        $personSlug = null;
        foreach ($movies->json('data') as $m) {
            if (!empty($m['people'][0]['slug'])) { $personSlug = $m['people'][0]['slug']; break; }
        }
        $this->assertNotNull($personSlug, 'Expected at least one person linked to movies');

        $res = $this->getJson('/api/v1/people/'.$personSlug);
        $res->assertOk()->assertJsonStructure(['id','slug','name']);
    }
}



