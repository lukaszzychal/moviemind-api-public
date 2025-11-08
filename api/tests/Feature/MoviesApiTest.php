<?php

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MoviesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_list_movies_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/movies');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'title', 'release_year', 'director',
                    ],
                ],
            ]);
    }

    public function test_show_movie_returns_ok(): void
    {
        $index = $this->getJson('/api/v1/movies');
        $slug = $index->json('data.0.slug');

        $response = $this->getJson('/api/v1/movies/'.$slug);
        $response->assertOk()
            ->assertJsonStructure(['id', 'slug', 'title']);
    }

    public function test_show_movie_response_is_cached(): void
    {
        $index = $this->getJson('/api/v1/movies');
        $slug = $index->json('data.0.slug');

        $this->assertFalse(Cache::has('movie:'.$slug));

        $first = $this->getJson('/api/v1/movies/'.$slug);
        $first->assertOk();

        $this->assertTrue(Cache::has('movie:'.$slug));
        $this->assertSame($first->json(), Cache::get('movie:'.$slug));

        $movieId = $first->json('id');
        Movie::where('id', $movieId)->update(['title' => 'Changed Title']);

        $second = $this->getJson('/api/v1/movies/'.$slug);
        $second->assertOk();
        $this->assertSame($first->json(), $second->json());
    }
}
