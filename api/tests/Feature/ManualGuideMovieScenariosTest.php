<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests mirroring MANUAL_TESTING_GUIDE and MANUAL_TEST_PLANS (TC-MOVIE-*).
 * Uses same slugs and requests as scripts/run-manual-scenarios.sh so both stay in sync.
 *
 * Prerequisites: db:seed creates the-matrix-1999, inception-2010, bad-boys-ii-2003 (MovieSeeder, SearchFixturesSeeder).
 */
class ManualGuideMovieScenariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
        config(['cache.default' => 'array']);
        config(['rate-limiting.logging.enabled' => false]);
    }

    public function test_scenario_1_list_all_movies_returns_200(): void
    {
        $response = $this->getJson('/api/v1/movies');
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'slug', 'title', 'release_year'],
            ],
        ]);
    }

    public function test_scenario_1_list_movies_with_q_returns_200(): void
    {
        $response = $this->getJson('/api/v1/movies?q=matrix');
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_scenario_2_search_by_title_returns_200(): void
    {
        $response = $this->getJson('/api/v1/movies/search?q=matrix');
        $response->assertOk()
            ->assertJsonStructure(['results', 'total', 'match_type']);
    }

    public function test_scenario_2_search_by_actor_only_returns_200_when_fixture_has_keanu(): void
    {
        $response = $this->getJson('/api/v1/movies/search?actor=Keanu%20Reeves');
        $response->assertOk();
        $this->assertArrayHasKey('results', $response->json());
    }

    public function test_scenario_2_search_no_results_returns_404(): void
    {
        $query = 'NonexistentMovieXYZ123';
        $slug = \Illuminate\Support\Str::slug($query); // nonexistentmoviexyz123
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovie($slug, null);
        $fake->setMovieSearchResults($slug, []);

        $response = $this->getJson('/api/v1/movies/search?q='.urlencode($query));
        $response->assertNotFound();
    }

    public function test_scenario_3_get_movie_details_the_matrix_1999_returns_200(): void
    {
        $response = $this->getJson('/api/v1/movies/the-matrix-1999');
        $response->assertOk()
            ->assertJsonPath('slug', 'the-matrix-1999')
            ->assertJsonPath('title', 'The Matrix');
    }

    public function test_scenario_4_bulk_retrieve_get_slugs_returns_200(): void
    {
        $response = $this->getJson('/api/v1/movies?slugs=the-matrix-1999,inception-2010');
        $response->assertOk()
            ->assertJsonStructure(['data', 'not_found', 'count', 'requested_count'])
            ->assertJsonPath('count', 2)
            ->assertJsonPath('requested_count', 2);
        $this->assertEmpty($response->json('not_found'));
    }

    public function test_scenario_4_bulk_post_returns_200(): void
    {
        $response = $this->postJson('/api/v1/movies/bulk', [
            'slugs' => ['the-matrix-1999', 'inception-2010'],
        ]);
        $response->assertOk()
            ->assertJsonStructure(['data', 'not_found', 'count', 'requested_count'])
            ->assertJsonPath('count', 2)
            ->assertJsonPath('requested_count', 2);
    }

    public function test_scenario_5_search_disambiguation_bad_boys_returns_200(): void
    {
        $response = $this->getJson('/api/v1/movies/search?q=bad+boys');
        $response->assertOk()
            ->assertJsonStructure(['results', 'match_type']);
    }

    public function test_scenario_5_get_movie_by_slug_bad_boys_ii_2003_returns_200(): void
    {
        $response = $this->getJson('/api/v1/movies/bad-boys-ii-2003');
        $response->assertOk()
            ->assertJsonPath('slug', 'bad-boys-ii-2003')
            ->assertJsonPath('title', 'Bad Boys II');
    }

    public function test_scenario_6_refresh_movie_returns_200(): void
    {
        $response = $this->postJson('/api/v1/movies/the-matrix-1999/refresh');
        $response->assertOk();
    }

    public function test_scenario_7_movie_collection_returns_200(): void
    {
        $response = $this->getJson('/api/v1/movies/the-matrix-1999/collection');
        $response->assertOk()
            ->assertJsonStructure(['collection', 'movies']);
    }

    public function test_scenario_8_related_movies_returns_200(): void
    {
        $response = $this->getJson('/api/v1/movies/the-matrix-1999/related');
        $response->assertOk()
            ->assertJsonStructure(['movie', 'related_movies']);
    }

    public function test_tc_movie_005_compare_movies_returns_200(): void
    {
        $response = $this->getJson('/api/v1/movies/compare?slug1=the-matrix-1999&slug2=inception-2010');
        $response->assertOk()
            ->assertJsonStructure(['movie1', 'movie2']);
    }

    public function test_tc_movie_009_report_movie_issue_returns_201(): void
    {
        $response = $this->postJson('/api/v1/movies/the-matrix-1999/report', [
            'type' => 'factual_error',
            'message' => 'Test report from manual guide scenarios',
        ]);
        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'movie_id', 'type', 'message', 'status']]);
    }

    public function test_scenario_health_check_returns_200(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertOk();
    }
}
