<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\TmdbVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MovieDisambiguationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
        config(['services.tmdb.api_key' => 'test-api-key']);
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_movie_returns_disambiguation_when_multiple_matches_found(): void
    {
        Feature::activate('ai_description_generation');

        // Mock TMDb search to return multiple results
        $this->mock(TmdbVerificationService::class, function ($mock) {
            $mock->shouldReceive('verifyMovie')
                ->with('bad-boys')
                ->andReturn(null); // Not found as single match

            $mock->shouldReceive('searchMovies')
                ->with('bad-boys', 5)
                ->andReturn([
                    [
                        'id' => 9738,
                        'title' => 'Bad Boys',
                        'release_date' => '1995-04-07',
                        'overview' => 'Two hip detectives protect a witness',
                        'director' => 'Michael Bay',
                    ],
                    [
                        'id' => 9739,
                        'title' => 'Bad Boys II',
                        'release_date' => '2003-07-18',
                        'overview' => 'Two detectives investigate',
                        'director' => 'Michael Bay',
                    ],
                ]);
        });

        $response = $this->getJson('/api/v1/movies/bad-boys');

        $response->assertStatus(300)
            ->assertJsonStructure([
                'error',
                'message',
                'slug',
                'options' => [
                    '*' => ['tmdb_id', 'title', 'release_year', 'director', 'overview', 'select_url'],
                ],
                'count',
            ])
            ->assertJsonCount(2, 'options');
    }

    public function test_movie_disambiguation_allows_selection_by_tmdb_id(): void
    {
        Feature::activate('ai_description_generation');

        // Mock TMDb search and selection
        $this->mock(TmdbVerificationService::class, function ($mock) {
            $mock->shouldReceive('searchMovies')
                ->with('bad-boys', 10)
                ->andReturn([
                    [
                        'id' => 9738,
                        'title' => 'Bad Boys',
                        'release_date' => '1995-04-07',
                        'overview' => 'Two hip detectives',
                        'director' => 'Michael Bay',
                    ],
                    [
                        'id' => 9739,
                        'title' => 'Bad Boys II',
                        'release_date' => '2003-07-18',
                        'overview' => 'Two detectives investigate',
                        'director' => 'Michael Bay',
                    ],
                ]);
        });

        $response = $this->getJson('/api/v1/movies/bad-boys?tmdb_id=9739');

        $response->assertStatus(202)
            ->assertJsonStructure(['job_id', 'status', 'slug']);
    }

    public function test_movie_disambiguation_returns_404_when_invalid_tmdb_id(): void
    {
        Feature::activate('ai_description_generation');

        // Mock TMDb search with different IDs
        $this->mock(TmdbVerificationService::class, function ($mock) {
            $mock->shouldReceive('searchMovies')
                ->with('bad-boys', 10)
                ->andReturn([
                    [
                        'id' => 9738,
                        'title' => 'Bad Boys',
                        'release_date' => '1995-04-07',
                        'overview' => 'Two hip detectives',
                        'director' => 'Michael Bay',
                    ],
                ]);
        });

        $response = $this->getJson('/api/v1/movies/bad-boys?tmdb_id=99999');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Selected movie not found in search results']);
    }

    public function test_movie_returns_single_match_without_disambiguation(): void
    {
        Feature::activate('ai_description_generation');

        // Mock TMDb to return single match
        $this->mock(TmdbVerificationService::class, function ($mock) {
            $mock->shouldReceive('verifyMovie')
                ->with('unique-test-movie-xyz')
                ->andReturn([
                    'id' => 999999,
                    'title' => 'Unique Test Movie',
                    'release_date' => '2024-01-01',
                    'overview' => 'A unique test movie',
                    'director' => 'Test Director',
                ]);
        });

        $response = $this->getJson('/api/v1/movies/unique-test-movie-xyz');

        $response->assertStatus(202)
            ->assertJsonStructure(['job_id', 'status', 'slug']);
    }
}
