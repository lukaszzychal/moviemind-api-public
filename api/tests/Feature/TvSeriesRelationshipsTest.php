<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RelationshipType;
use App\Models\TvSeries;
use App\Models\TvSeriesRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for TV series relationships endpoint.
 */
class TvSeriesRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_get_related_tv_series_returns_related_series(): void
    {
        // Given: A TV series with related series
        $tvSeries = TvSeries::factory()->create([
            'title' => 'Breaking Bad',
            'slug' => 'breaking-bad-2008',
        ]);

        $sequel = TvSeries::factory()->create([
            'title' => 'Better Call Saul',
            'slug' => 'better-call-saul-2015',
        ]);

        TvSeriesRelationship::create([
            'tv_series_id' => $tvSeries->id,
            'related_tv_series_id' => $sequel->id,
            'relationship_type' => RelationshipType::SPINOFF,
            'order' => 1,
        ]);

        // When: A GET request is sent
        $response = $this->getJson("/api/v1/tv-series/{$tvSeries->slug}/related");

        // Then: The response is OK and contains related series
        $response->assertOk()
            ->assertJsonStructure([
                'tv_series' => ['id', 'slug', 'title'],
                'related_tv_series' => [
                    '*' => ['id', 'slug', 'title', 'relationship_type', 'relationship_label', 'relationship_order'],
                ],
                'count',
                '_links',
            ])
            ->assertJsonCount(1, 'related_tv_series')
            ->assertJsonPath('tv_series.slug', $tvSeries->slug)
            ->assertJsonPath('count', 1);
    }

    public function test_get_related_tv_series_filters_by_type(): void
    {
        // Given: A TV series with related series of different types
        $tvSeries = TvSeries::factory()->create([
            'title' => 'Breaking Bad',
            'slug' => 'breaking-bad-2008',
        ]);

        $sequel = TvSeries::factory()->create([
            'title' => 'Better Call Saul',
            'slug' => 'better-call-saul-2015',
        ]);

        $prequel = TvSeries::factory()->create([
            'title' => 'Breaking Bad: The Movie',
            'slug' => 'breaking-bad-the-movie-2019',
        ]);

        TvSeriesRelationship::create([
            'tv_series_id' => $tvSeries->id,
            'related_tv_series_id' => $sequel->id,
            'relationship_type' => RelationshipType::SPINOFF,
        ]);

        TvSeriesRelationship::create([
            'tv_series_id' => $tvSeries->id,
            'related_tv_series_id' => $prequel->id,
            'relationship_type' => RelationshipType::SEQUEL,
        ]);

        // When: A GET request is sent with type filter
        $response = $this->getJson("/api/v1/tv-series/{$tvSeries->slug}/related?type=spinoff");

        // Then: Only spinoff relationships are returned
        $response->assertOk()
            ->assertJsonCount(1, 'related_tv_series')
            ->assertJsonPath('related_tv_series.0.relationship_type', 'SPINOFF');
    }

    public function test_get_related_tv_series_returns_404_when_not_found(): void
    {
        // When: A GET request is sent for non-existent TV series
        $response = $this->getJson('/api/v1/tv-series/non-existent-series/related');

        // Then: The response is 404
        $response->assertNotFound();
    }

    public function test_get_related_tv_series_returns_empty_when_no_relationships(): void
    {
        // Given: A TV series with no related series
        $tvSeries = TvSeries::factory()->create([
            'title' => 'Standalone Series',
            'slug' => 'standalone-series-2020',
        ]);

        // When: A GET request is sent
        $response = $this->getJson("/api/v1/tv-series/{$tvSeries->slug}/related");

        // Then: Empty list is returned
        $response->assertOk()
            ->assertJsonCount(0, 'related_tv_series')
            ->assertJsonPath('count', 0);
    }
}
