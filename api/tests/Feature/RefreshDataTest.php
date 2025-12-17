<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Person;
use App\Models\TmdbSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RefreshDataTest extends TestCase
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

    public function test_refresh_movie_returns_404_when_movie_not_found(): void
    {
        $response = $this->postJson('/api/v1/movies/non-existent-movie/refresh');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Movie not found']);
    }

    public function test_refresh_movie_returns_404_when_no_snapshot(): void
    {
        $movie = Movie::create([
            'title' => 'Test Movie',
            'slug' => 'test-movie',
            'release_year' => 2000,
        ]);

        $response = $this->postJson('/api/v1/movies/test-movie/refresh');

        $response->assertStatus(404)
            ->assertJson(['error' => 'No TMDb snapshot found for this movie']);
    }

    public function test_refresh_person_returns_404_when_person_not_found(): void
    {
        $response = $this->postJson('/api/v1/people/non-existent-person/refresh');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Person not found']);
    }

    public function test_refresh_person_returns_404_when_no_snapshot(): void
    {
        $person = Person::create([
            'name' => 'Test Person',
            'slug' => 'test-person',
        ]);

        $response = $this->postJson('/api/v1/people/test-person/refresh');

        $response->assertStatus(404)
            ->assertJson(['error' => 'No TMDb snapshot found for this person']);
    }

    public function test_refresh_movie_updates_snapshot(): void
    {
        $movie = Movie::create([
            'title' => 'Test Movie',
            'slug' => 'test-movie',
            'release_year' => 2000,
        ]);
        $snapshot = TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->id,
            'tmdb_id' => 123,
            'tmdb_type' => 'movie',
            'raw_data' => ['old' => 'data'],
            'fetched_at' => now(),
        ]);

        // Mock TmdbVerificationService
        $this->mock(\App\Services\TmdbVerificationService::class, function ($mock) {
            $mock->shouldReceive('refreshMovieDetails')
                ->with(123)
                ->andReturn(['new' => 'data', 'id' => 123]);
        });

        $response = $this->postJson('/api/v1/movies/test-movie/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'slug',
                'movie_id',
                'refreshed_at',
            ])
            ->assertJsonMissing(['tmdb_id']);

        $snapshot->refresh();
        $this->assertEquals(['new' => 'data', 'id' => 123], $snapshot->raw_data);
    }

    public function test_refresh_person_updates_snapshot(): void
    {
        $person = Person::create([
            'name' => 'Test Person',
            'slug' => 'test-person',
        ]);
        $snapshot = TmdbSnapshot::create([
            'entity_type' => 'PERSON',
            'entity_id' => $person->id,
            'tmdb_id' => 456,
            'tmdb_type' => 'person',
            'raw_data' => ['old' => 'data'],
            'fetched_at' => now(),
        ]);

        // Mock TmdbVerificationService
        $this->mock(\App\Services\TmdbVerificationService::class, function ($mock) {
            $mock->shouldReceive('refreshPersonDetails')
                ->with(456)
                ->andReturn(['new' => 'data', 'id' => 456]);
        });

        $response = $this->postJson('/api/v1/people/test-person/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'slug',
                'person_id',
                'refreshed_at',
            ])
            ->assertJsonMissing(['tmdb_id']);

        $snapshot->refresh();
        $this->assertEquals(['new' => 'data', 'id' => 456], $snapshot->raw_data);
    }
}
