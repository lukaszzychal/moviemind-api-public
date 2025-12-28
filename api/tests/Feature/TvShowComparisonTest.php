<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TvShow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvShowComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_compare_two_tv_shows_returns_comparison(): void
    {
        // Given: Two TV shows with common genres
        $tvShow1 = TvShow::factory()->create([
            'title' => 'The Tonight Show',
            'slug' => 'the-tonight-show-1954',
            'genres' => ['Talk', 'Variety'],
            'first_air_date' => '1954-09-27',
        ]);

        $tvShow2 = TvShow::factory()->create([
            'title' => 'The Late Show',
            'slug' => 'the-late-show-1993',
            'genres' => ['Talk'],
            'first_air_date' => '1993-08-30',
        ]);

        // When: Comparing the two TV shows
        $response = $this->getJson('/api/v1/tv-shows/compare?slug1=the-tonight-show-1954&slug2=the-late-show-1993');

        // Then: Comparison data should be returned
        $response->assertOk()
            ->assertJsonStructure([
                'tv_show1' => ['id', 'slug', 'title', 'first_air_year'],
                'tv_show2' => ['id', 'slug', 'title', 'first_air_year'],
                'comparison' => [
                    'common_genres',
                    'common_people',
                    'year_difference',
                    'similarity_score',
                ],
            ])
            ->assertJsonPath('tv_show1.slug', 'the-tonight-show-1954')
            ->assertJsonPath('tv_show2.slug', 'the-late-show-1993')
            ->assertJsonPath('comparison.common_genres', ['Talk']);
    }

    public function test_compare_validates_required_slugs(): void
    {
        $response = $this->getJson('/api/v1/tv-shows/compare');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug1', 'slug2']);
    }

    public function test_compare_returns_404_when_tv_show_not_found(): void
    {
        $response = $this->getJson('/api/v1/tv-shows/compare?slug1=non-existent-1&slug2=non-existent-2');

        $response->assertNotFound();
    }
}
