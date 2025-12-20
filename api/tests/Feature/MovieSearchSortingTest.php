<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieSearchSortingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        config(['logging.default' => 'stack']);
        config(['rate-limiting.logging.enabled' => false]);
        config(['services.tmdb.api_key' => 'test-api-key']);

        // Clear cache before each test
        \Illuminate\Support\Facades\Cache::flush();

        // Mock TMDb service to return empty results (so we only test local sorting)
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovieSearchResults('', []); // Empty results for any query
    }

    public function test_search_can_sort_by_title_ascending(): void
    {
        // Create movies with different titles
        $movie1 = Movie::factory()->create(['title' => 'Zebra', 'release_year' => 2000]);
        $movie2 = Movie::factory()->create(['title' => 'Alpha', 'release_year' => 2000]);
        $movie3 = Movie::factory()->create(['title' => 'Beta', 'release_year' => 2000]);

        // Use empty query to get all movies, then sort
        $response = $this->getJson('/api/v1/movies/search?q=&sort=title&order=asc');

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('results', $data);

        // Filter only our test movies
        $testTitles = ['Alpha', 'Beta', 'Zebra'];
        $results = array_values(array_filter($data['results'], function ($movie) use ($testTitles) {
            return in_array($movie['title'], $testTitles);
        }));

        $this->assertGreaterThanOrEqual(3, count($results), 'Should have at least 3 test movies');

        // Verify ascending order
        $titles = array_column($results, 'title');
        for ($i = 0; $i < count($titles) - 1; $i++) {
            $this->assertLessThanOrEqual(0, strcasecmp($titles[$i], $titles[$i + 1]), "Title at index $i should be <= next title");
        }
    }

    public function test_search_can_sort_by_title_descending(): void
    {
        // Create movies with different titles
        Movie::factory()->create(['title' => 'Alpha', 'release_year' => 2000]);
        Movie::factory()->create(['title' => 'Zebra', 'release_year' => 2000]);
        Movie::factory()->create(['title' => 'Beta', 'release_year' => 2000]);

        // Use empty query to get all movies, then sort
        $response = $this->getJson('/api/v1/movies/search?q=&sort=title&order=desc');

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('results', $data);

        // Filter only our test movies and check order
        $testTitles = ['Alpha', 'Beta', 'Zebra'];
        $results = array_values(array_filter($data['results'], function ($movie) use ($testTitles) {
            return in_array($movie['title'], $testTitles);
        }));

        // Check that results are sorted descending by title
        $titles = array_column($results, 'title');
        $this->assertGreaterThanOrEqual(3, count($titles), 'Should have at least 3 test movies');
        // Verify descending order
        for ($i = 0; $i < count($titles) - 1; $i++) {
            $this->assertGreaterThanOrEqual(0, strcasecmp($titles[$i], $titles[$i + 1]), "Title at index $i should be >= next title");
        }
    }

    public function test_search_can_sort_by_release_year_ascending(): void
    {
        // Create movies with different years
        Movie::factory()->create(['title' => 'Movie C', 'release_year' => 2020]);
        Movie::factory()->create(['title' => 'Movie A', 'release_year' => 2000]);
        Movie::factory()->create(['title' => 'Movie B', 'release_year' => 2010]);

        // Use empty query to get all movies, then sort
        $response = $this->getJson('/api/v1/movies/search?q=&sort=release_year&order=asc');

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('results', $data);

        // Filter only our test movies to avoid issues with seeder data
        $testTitles = ['Movie A', 'Movie B', 'Movie C'];
        $testResults = array_values(array_filter($data['results'], function ($movie) use ($testTitles) {
            return in_array($movie['title'], $testTitles);
        }));
        $testYears = array_column($testResults, 'release_year');
        $testTitlesOrdered = array_column($testResults, 'title');
        $this->assertGreaterThanOrEqual(3, count($testYears), 'Should have at least 3 test movies');

        // Debug: Print actual order for debugging
        $actualOrder = count($testYears) >= 3
            ? "{$testYears[0]} ({$testTitlesOrdered[0]}), {$testYears[1]} ({$testTitlesOrdered[1]}), {$testYears[2]} ({$testTitlesOrdered[2]})"
            : implode(', ', $testYears);

        // Verify the order matches expected: 2000, 2010, 2020
        $this->assertEquals(2000, $testYears[0], "First movie should be from 2000, but got: $actualOrder");
        $this->assertEquals(2010, $testYears[1], "Second movie should be from 2010, but got: $actualOrder");
        $this->assertEquals(2020, $testYears[2], "Third movie should be from 2020, but got: $actualOrder");
    }

    public function test_search_can_sort_by_release_year_descending(): void
    {
        // Create movies with different years
        Movie::factory()->create(['title' => 'Movie A', 'release_year' => 2000]);
        Movie::factory()->create(['title' => 'Movie C', 'release_year' => 2020]);
        Movie::factory()->create(['title' => 'Movie B', 'release_year' => 2010]);

        // Use empty query to get all movies, then sort
        $response = $this->getJson('/api/v1/movies/search?q=&sort=release_year&order=desc');

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('results', $data);

        // Filter only our test movies to avoid issues with seeder data
        $testTitles = ['Movie A', 'Movie B', 'Movie C'];
        $testResults = array_values(array_filter($data['results'], function ($movie) use ($testTitles) {
            return in_array($movie['title'], $testTitles);
        }));
        $testYears = array_column($testResults, 'release_year');
        $testTitlesOrdered = array_column($testResults, 'title');
        $this->assertGreaterThanOrEqual(3, count($testYears), 'Should have at least 3 test movies');

        // Debug: Print actual order for debugging
        $actualOrder = count($testYears) >= 3
            ? "{$testYears[0]} ({$testTitlesOrdered[0]}), {$testYears[1]} ({$testTitlesOrdered[1]}), {$testYears[2]} ({$testTitlesOrdered[2]})"
            : implode(', ', $testYears);

        // Verify the order matches expected: 2020, 2010, 2000
        $this->assertEquals(2020, $testYears[0], "First movie should be from 2020, but got: $actualOrder");
        $this->assertEquals(2010, $testYears[1], "Second movie should be from 2010, but got: $actualOrder");
        $this->assertEquals(2000, $testYears[2], "Third movie should be from 2000, but got: $actualOrder");
    }

    public function test_search_defaults_to_relevance_when_no_sort_specified(): void
    {
        // Create movies
        Movie::factory()->create(['title' => 'Zebra', 'release_year' => 2000]);
        Movie::factory()->create(['title' => 'Alpha', 'release_year' => 2000]);

        // Use empty query to get all movies
        $response = $this->getJson('/api/v1/movies/search?q=');

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('results', $data);
        // Results should be returned (order may vary based on relevance/confidence)
        $this->assertGreaterThan(0, count($data['results']));
    }

    public function test_search_validates_sort_parameter(): void
    {
        $response = $this->getJson('/api/v1/movies/search?q=&sort=invalid_field');

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_search_validates_order_parameter(): void
    {
        $response = $this->getJson('/api/v1/movies/search?q=&sort=title&order=invalid');

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
    }
}
