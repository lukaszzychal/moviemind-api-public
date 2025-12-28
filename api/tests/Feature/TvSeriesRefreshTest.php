<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TmdbSnapshot;
use App\Models\TvSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvSeriesRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_refresh_returns_404_when_tv_series_not_found(): void
    {
        $response = $this->postJson('/api/v1/tv-series/non-existent-series/refresh');
        $response->assertNotFound();
    }

    public function test_refresh_returns_404_when_no_snapshot(): void
    {
        $tvSeries = TvSeries::factory()->create();
        $response = $this->postJson("/api/v1/tv-series/{$tvSeries->slug}/refresh");

        $response->assertStatus(404)
            ->assertJson(['error' => 'No TMDb snapshot found for this TV series']);
    }

    public function test_refresh_returns_success_when_snapshot_exists(): void
    {
        $tvSeries = TvSeries::factory()->create();
        TmdbSnapshot::create([
            'entity_type' => 'TV_SERIES',
            'entity_id' => $tvSeries->id,
            'tmdb_id' => 1396,
            'tmdb_type' => 'tv',
            'raw_data' => ['id' => 1396],
            'fetched_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/tv-series/{$tvSeries->slug}/refresh");

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'slug',
                'tv_series_id',
                'refreshed_at',
            ]);
    }
}
