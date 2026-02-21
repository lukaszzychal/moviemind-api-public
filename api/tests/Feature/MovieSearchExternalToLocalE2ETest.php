<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MovieSearchExternalToLocalE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we are using REAL services, not fakes for this test
        config(['moviemind.ai_service' => 'mock']); // we might keep mock AI to not spend money, but TMDB should be real

        $this->artisan('migrate');
        $this->artisan('db:seed');
        config(['cache.default' => 'array']);
        Cache::flush();

        // Ensure features are enabled
        \Laravel\Pennant\Feature::activate('ai_description_generation');
        \Laravel\Pennant\Feature::activate('tmdb_verification');
    }

    /**
     * @group e2e
     * @group external
     */
    public function test_real_external_search_becomes_local(): void
    {
        // Use a movie that is definitely not in our seeders, but exists in TMDB
        // Let's use something specific like "The Matrix Reloaded" or similar
        // to ensure it fetches from TMDB.
        $searchQuery = 'Matrix Reloaded';
        $year = 2003;

        // 1. Search for the movie (should be external initially)
        $searchResponse = $this->getJson('/api/v1/movies/search?q='.urlencode($searchQuery)."&year={$year}");
        $searchResponse->assertOk();

        $results = $searchResponse->json('results');
        $this->assertNotEmpty($results, 'Search should return results from TMDB');

        $foundExternal = collect($results)->firstWhere('source', 'external');
        if (! $foundExternal) {
            $this->markTestSkipped('Could not find external result for the query, might already be in DB or TMDB issue');

            return;
        }

        $this->assertEquals('external', $foundExternal['source']);
        $suggestedSlug = $foundExternal['suggested_slug'];

        // Let's dump the slug and the search results to see what's what
        dump("Found external slug: {$suggestedSlug}");

        // 2. Fetch the movie to trigger generation/saving to local DB
        $movieResponse = $this->getJson("/api/v1/movies/{$suggestedSlug}");
        if ($movieResponse->status() !== 202) {
            dump($movieResponse->json());
        }
        $movieResponse->assertStatus(202); // 202 Accepted because generation is queued

        // 3. Verify it's in the local database now
        $this->assertDatabaseHas('movies', ['slug' => $suggestedSlug]);

        // Clear cache so the next search hits the database
        Cache::flush();

        // 4. Search again, should be local now
        $searchResponse2 = $this->getJson('/api/v1/movies/search?q='.urlencode($searchQuery)."&year={$year}");
        $searchResponse2->assertOk();

        $results2 = $searchResponse2->json('results');
        $foundLocal = collect($results2)->firstWhere('slug', $suggestedSlug);

        $this->assertNotNull($foundLocal, 'Should find the movie in search results again');
        $this->assertEquals('local', $foundLocal['source'], 'Source should be local now after generation was queued');
    }
}
