<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RelationshipType;
use App\Models\TvShow;
use App\Models\TvShowRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for TV show relationships endpoint.
 */
class TvShowRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_get_related_tv_shows_returns_related_shows(): void
    {
        // Given: A TV show with related shows
        $tvShow = TvShow::factory()->create([
            'title' => 'The Tonight Show',
            'slug' => 'the-tonight-show-1954',
        ]);

        $relatedShow = TvShow::factory()->create([
            'title' => 'The Late Show',
            'slug' => 'the-late-show-1993',
        ]);

        TvShowRelationship::create([
            'tv_show_id' => $tvShow->id,
            'related_tv_show_id' => $relatedShow->id,
            'relationship_type' => RelationshipType::SPINOFF,
            'order' => 1,
        ]);

        // When: A GET request is sent
        $response = $this->getJson("/api/v1/tv-shows/{$tvShow->slug}/related");

        // Then: The response is OK and contains related shows
        $response->assertOk()
            ->assertJsonStructure([
                'tv_show' => ['id', 'slug', 'title'],
                'related_tv_shows' => [
                    '*' => ['id', 'slug', 'title', 'relationship_type', 'relationship_label', 'relationship_order'],
                ],
                'count',
                '_links',
            ])
            ->assertJsonCount(1, 'related_tv_shows')
            ->assertJsonPath('tv_show.slug', $tvShow->slug)
            ->assertJsonPath('count', 1);
    }

    public function test_get_related_tv_shows_returns_404_when_not_found(): void
    {
        $response = $this->getJson('/api/v1/tv-shows/non-existent-show/related');
        $response->assertNotFound();
    }

    public function test_get_related_tv_shows_returns_empty_when_no_relationships(): void
    {
        $tvShow = TvShow::factory()->create();
        $response = $this->getJson("/api/v1/tv-shows/{$tvShow->slug}/related");

        $response->assertOk()
            ->assertJsonCount(0, 'related_tv_shows')
            ->assertJsonPath('count', 0);
    }
}
