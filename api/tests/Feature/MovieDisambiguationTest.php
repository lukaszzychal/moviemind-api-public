<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MovieDisambiguationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // Chicago School - fake queue instead of executing real jobs
        $this->artisan('migrate');
        $this->artisan('db:seed');
        config(['services.tmdb.api_key' => 'test-api-key']);
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_movie_returns_disambiguation_when_multiple_matches_found(): void
    {
        Feature::activate('ai_description_generation');
        Feature::activate('tmdb_verification');

        // Use fake EntityVerificationService (Chicago School - prefer test doubles over mocks)
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovie('bad-boys', null); // Not found as single match
        $fake->setMovieSearchResults('bad-boys', [
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

        $response = $this->getJson('/api/v1/movies/bad-boys');

        $response->assertStatus(300)
            ->assertJsonStructure([
                'error',
                'message',
                'slug',
                'options' => [
                    '*' => ['slug', 'title', 'release_year', 'director', 'overview', 'select_url'],
                ],
                'count',
                'hint',
            ])
            ->assertJsonCount(2, 'options')
            ->assertJsonMissing(['options' => [['tmdb_id' => 9738]]])
            ->assertJsonMissing(['options' => [['tmdb_id' => 9739]]]);
    }

    public function test_movie_disambiguation_allows_selection_by_slug(): void
    {
        Feature::activate('ai_description_generation');
        Feature::activate('tmdb_verification');

        // Use fake EntityVerificationService (Chicago School - prefer test doubles over mocks)
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovie('bad-boys', null); // No exact match, will trigger search
        $fake->setMovieSearchResults('bad-boys', [
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

        // First get disambiguation to see the suggested slug
        $disambiguationResponse = $this->getJson('/api/v1/movies/bad-boys');
        $disambiguationResponse->assertStatus(300);
        $options = $disambiguationResponse->json('options');
        $this->assertNotEmpty($options);

        // Use the slug from disambiguation options
        $selectedSlug = $options[1]['slug'] ?? 'bad-boys-ii-2003'; // Bad Boys II slug

        $response = $this->getJson("/api/v1/movies/bad-boys?slug={$selectedSlug}");

        $response->assertStatus(202)
            ->assertJsonStructure(['job_id', 'status', 'slug']);
    }

    public function test_movie_disambiguation_returns_404_when_invalid_slug(): void
    {
        Feature::activate('ai_description_generation');
        Feature::activate('tmdb_verification');

        // Use fake EntityVerificationService (Chicago School - prefer test doubles over mocks)
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovie('bad-boys', null);
        $fake->setMovieSearchResults('bad-boys', [
            [
                'id' => 9738,
                'title' => 'Bad Boys',
                'release_date' => '1995-04-07',
                'overview' => 'Two hip detectives',
                'director' => 'Michael Bay',
            ],
        ]);

        $response = $this->getJson('/api/v1/movies/bad-boys?slug=non-existent-slug-99999');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Selected movie not found in search results']);
    }

    public function test_movie_returns_single_match_without_disambiguation(): void
    {
        Feature::activate('ai_description_generation');
        Feature::activate('tmdb_verification');

        // Use fake EntityVerificationService (Chicago School - prefer test doubles over mocks)
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovie('unique-test-movie-xyz', [
            'id' => 999999,
            'title' => 'Unique Test Movie',
            'release_date' => '2024-01-01',
            'overview' => 'A unique test movie',
            'director' => 'Test Director',
        ]);

        $response = $this->getJson('/api/v1/movies/unique-test-movie-xyz');

        $response->assertStatus(202)
            ->assertJsonStructure(['job_id', 'status', 'slug']);
    }
}
