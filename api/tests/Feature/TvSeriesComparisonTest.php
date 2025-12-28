<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TvSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvSeriesComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_compare_two_tv_series_returns_comparison(): void
    {
        // Given: Two TV series with common genres
        $tvSeries1 = TvSeries::factory()->create([
            'title' => 'Breaking Bad',
            'slug' => 'breaking-bad-2008',
            'genres' => ['Drama', 'Crime', 'Thriller'],
            'first_air_date' => '2008-01-20',
        ]);

        $tvSeries2 = TvSeries::factory()->create([
            'title' => 'Better Call Saul',
            'slug' => 'better-call-saul-2015',
            'genres' => ['Drama', 'Crime'],
            'first_air_date' => '2015-02-08',
        ]);

        // When: Comparing the two TV series
        $response = $this->getJson('/api/v1/tv-series/compare?slug1=breaking-bad-2008&slug2=better-call-saul-2015');

        // Then: Comparison data should be returned
        $response->assertOk()
            ->assertJsonStructure([
                'tv_series1' => ['id', 'slug', 'title', 'first_air_year'],
                'tv_series2' => ['id', 'slug', 'title', 'first_air_year'],
                'comparison' => [
                    'common_genres',
                    'common_people',
                    'year_difference',
                    'similarity_score',
                ],
            ])
            ->assertJsonPath('tv_series1.slug', 'breaking-bad-2008')
            ->assertJsonPath('tv_series2.slug', 'better-call-saul-2015')
            ->assertJsonPath('comparison.common_genres', ['Drama', 'Crime']);
    }

    public function test_compare_validates_required_slugs(): void
    {
        $response = $this->getJson('/api/v1/tv-series/compare');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug1', 'slug2']);
    }

    public function test_compare_returns_404_when_tv_series_not_found(): void
    {
        $response = $this->getJson('/api/v1/tv-series/compare?slug1=non-existent-1&slug2=non-existent-2');

        $response->assertNotFound();
    }
}
