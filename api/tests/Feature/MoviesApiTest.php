<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoviesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_list_movies_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/movies');

        $response->assertOk()
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id', 'title', 'release_year', 'director',
                         ]
                     ]
                 ]);
    }

    public function test_show_movie_returns_ok(): void
    {
        $index = $this->getJson('/api/v1/movies');
        $slug = $index->json('data.0.slug');

        $response = $this->getJson('/api/v1/movies/'.$slug);
        $response->assertOk()
                 ->assertJsonStructure(['id','slug','title']);
    }
}


