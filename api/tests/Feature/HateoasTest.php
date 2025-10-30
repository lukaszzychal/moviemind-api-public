<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HateoasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_movies_list_contains_links(): void
    {
        $res = $this->getJson('/api/v1/movies');
        $res->assertOk();
        $first = $res->json('data.0');
        $this->assertArrayHasKey('_links', $first);
        $this->assertArrayHasKey('self', $first['_links']);
    }

    public function test_movie_show_contains_links(): void
    {
        $res = $this->getJson('/api/v1/movies/the-matrix');
        $res->assertOk();
        $body = $res->json();
        $this->assertArrayHasKey('_links', $body);
        $this->assertArrayHasKey('self', $body['_links']);
        $this->assertArrayHasKey('generate', $body['_links']);
    }

    public function test_person_show_contains_links(): void
    {
        // Find any person via movies listing
        $movies = $this->getJson('/api/v1/movies');
        $movies->assertOk();
        $slug = null;
        foreach ($movies->json('data') as $m) {
            if (!empty($m['people'][0]['slug'])) { $slug = $m['people'][0]['slug']; break; }
        }
        $this->assertNotNull($slug, 'Expected at least one linked person');

        $res = $this->getJson('/api/v1/people/'.$slug);
        $res->assertOk();
        $body = $res->json();
        $this->assertArrayHasKey('_links', $body);
        $this->assertArrayHasKey('self', $body['_links']);
        $this->assertArrayHasKey('movies', $body['_links']);
    }
}


