<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\TmdbVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PersonDisambiguationTest extends TestCase
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

    public function test_person_returns_disambiguation_when_multiple_matches_found(): void
    {
        Feature::activate('ai_bio_generation');
        Feature::activate('tmdb_verification');

        // Mock TMDb search to return multiple results
        $this->mock(TmdbVerificationService::class, function ($mock) {
            $mock->shouldReceive('verifyPerson')
                ->with('john-smith')
                ->andReturn(null); // Not found as single match

            $mock->shouldReceive('searchPeople')
                ->with('john-smith', 5)
                ->andReturn([
                    [
                        'id' => 12345,
                        'name' => 'John Smith',
                        'birthday' => '1980-01-01',
                        'place_of_birth' => 'New York, USA',
                        'biography' => 'Actor and director',
                    ],
                    [
                        'id' => 12346,
                        'name' => 'John Smith',
                        'birthday' => '1990-05-15',
                        'place_of_birth' => 'Los Angeles, USA',
                        'biography' => 'Director',
                    ],
                ]);
        });

        $response = $this->getJson('/api/v1/people/john-smith');

        $response->assertStatus(300)
            ->assertJsonStructure([
                'error',
                'message',
                'slug',
                'options' => [
                    '*' => ['slug', 'name', 'birth_year', 'birthplace', 'biography', 'select_url'],
                ],
                'count',
                'hint',
            ])
            ->assertJsonCount(2, 'options')
            ->assertJsonMissing(['options' => [['tmdb_id' => 12345]]])
            ->assertJsonMissing(['options' => [['tmdb_id' => 12346]]]);
    }

    public function test_person_disambiguation_allows_selection_by_slug(): void
    {
        Feature::activate('ai_bio_generation');
        Feature::activate('tmdb_verification');

        // Mock TMDb search and selection
        $this->mock(TmdbVerificationService::class, function ($mock) {
            $mock->shouldReceive('verifyPerson')
                ->with('john-smith')
                ->andReturn(null); // Not found as single match

            $mock->shouldReceive('searchPeople')
                ->with('john-smith', 5)
                ->andReturn([
                    [
                        'id' => 12345,
                        'name' => 'John Smith',
                        'birthday' => '1980-01-01',
                        'place_of_birth' => 'New York, USA',
                        'biography' => 'Actor and director',
                    ],
                    [
                        'id' => 12346,
                        'name' => 'John Smith',
                        'birthday' => '1990-05-15',
                        'place_of_birth' => 'Los Angeles, USA',
                        'biography' => 'Director',
                    ],
                ]);

            $mock->shouldReceive('searchPeople')
                ->with('john-smith', 10)
                ->andReturn([
                    [
                        'id' => 12345,
                        'name' => 'John Smith',
                        'birthday' => '1980-01-01',
                        'place_of_birth' => 'New York, USA',
                        'biography' => 'Actor and director',
                    ],
                    [
                        'id' => 12346,
                        'name' => 'John Smith',
                        'birthday' => '1990-05-15',
                        'place_of_birth' => 'Los Angeles, USA',
                        'biography' => 'Director',
                    ],
                ]);
        });

        // First get disambiguation to see the suggested slug
        $disambiguationResponse = $this->getJson('/api/v1/people/john-smith');
        $disambiguationResponse->assertStatus(300);
        $options = $disambiguationResponse->json('options');
        $this->assertNotEmpty($options);

        // Use the slug from disambiguation options (second person, born 1990)
        $selectedSlug = $options[1]['slug'] ?? 'john-smith-1990';

        $response = $this->getJson("/api/v1/people/john-smith?slug={$selectedSlug}");

        $response->assertStatus(202)
            ->assertJsonStructure(['job_id', 'status', 'slug']);
    }

    public function test_person_disambiguation_returns_404_when_invalid_slug(): void
    {
        Feature::activate('ai_bio_generation');
        Feature::activate('tmdb_verification');

        // Mock TMDb search with different IDs
        $this->mock(TmdbVerificationService::class, function ($mock) {
            $mock->shouldReceive('searchPeople')
                ->with('john-smith', 10)
                ->andReturn([
                    [
                        'id' => 12345,
                        'name' => 'John Smith',
                        'birthday' => '1980-01-01',
                        'place_of_birth' => 'New York, USA',
                        'biography' => 'Actor and director',
                    ],
                ]);
        });

        $response = $this->getJson('/api/v1/people/john-smith?slug=non-existent-slug-99999');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Selected person not found in search results']);
    }

    public function test_person_returns_single_match_without_disambiguation(): void
    {
        Feature::activate('ai_bio_generation');
        Feature::activate('tmdb_verification');

        // Mock TMDb to return single match
        $this->mock(TmdbVerificationService::class, function ($mock) {
            $mock->shouldReceive('verifyPerson')
                ->with('unique-test-person-xyz')
                ->andReturn([
                    'id' => 999999,
                    'name' => 'Unique Test Person',
                    'birthday' => '1990-01-01',
                    'place_of_birth' => 'Test City',
                    'biography' => 'A unique test person',
                ]);
        });

        $response = $this->getJson('/api/v1/people/unique-test-person-xyz');

        $response->assertStatus(202)
            ->assertJsonStructure(['job_id', 'status', 'slug']);
    }
}
