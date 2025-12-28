<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TmdbSnapshot;
use App\Models\TvShow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvShowRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_refresh_returns_404_when_tv_show_not_found(): void
    {
        $response = $this->postJson('/api/v1/tv-shows/non-existent-show/refresh');
        $response->assertNotFound();
    }

    public function test_refresh_returns_404_when_no_snapshot(): void
    {
        $tvShow = TvShow::factory()->create();
        $response = $this->postJson("/api/v1/tv-shows/{$tvShow->slug}/refresh");

        $response->assertStatus(404)
            ->assertJson(['error' => 'No TMDb snapshot found for this TV show']);
    }

    public function test_refresh_returns_success_when_snapshot_exists(): void
    {
        $tvShow = TvShow::factory()->create();
        TmdbSnapshot::create([
            'entity_type' => 'TV_SHOW',
            'entity_id' => $tvShow->id,
            'tmdb_id' => 1448,
            'tmdb_type' => 'tv',
            'raw_data' => ['id' => 1448],
            'fetched_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/tv-shows/{$tvShow->slug}/refresh");

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'slug',
                'tv_show_id',
                'refreshed_at',
            ]);
    }
}
