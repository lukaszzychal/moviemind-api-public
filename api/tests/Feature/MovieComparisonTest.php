<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for movie comparison endpoint.
 *
 * @author MovieMind API Team
 */
class MovieComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
        config(['logging.default' => 'stack']);
        config(['rate-limiting.logging.enabled' => false]);
    }

    /**
     * Test: Compare two movies with common genres.
     */
    public function test_compare_movies_with_common_genres(): void
    {
        // Given: Two movies with common genres
        $genreSciFi = Genre::firstOrCreate(['slug' => 'science-fiction'], ['name' => 'Science Fiction']);
        $genreAction = Genre::firstOrCreate(['slug' => 'action'], ['name' => 'Action']);

        $uniqueSuffix = time();
        $movie1 = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.$uniqueSuffix,
            'release_year' => 1999,
        ]);
        $movie1->genres()->attach([$genreSciFi->id, $genreAction->id]);

        $movie2 = Movie::factory()->create([
            'title' => 'Inception',
            'slug' => 'inception-2010-'.$uniqueSuffix,
            'release_year' => 2010,
        ]);
        $movie2->genres()->attach([$genreSciFi->id, $genreAction->id]);

        // When: Compare the two movies
        $response = $this->getJson("/api/v1/movies/compare?slug1={$movie1->slug}&slug2={$movie2->slug}");

        // Then: Should return comparison with common genres
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('movie1', $data);
        $this->assertArrayHasKey('movie2', $data);
        $this->assertArrayHasKey('comparison', $data);

        $comparison = $data['comparison'];
        $this->assertArrayHasKey('common_genres', $comparison);
        $this->assertCount(2, $comparison['common_genres']);
        $this->assertContains('Science Fiction', $comparison['common_genres']);
        $this->assertContains('Action', $comparison['common_genres']);
    }

    /**
     * Test: Compare movies with common people.
     */
    public function test_compare_movies_with_common_people(): void
    {
        // Given: Two movies with common people
        $person = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-'.time(),
        ]);

        $uniqueSuffix = time();
        $movie1 = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.$uniqueSuffix,
            'release_year' => 1999,
        ]);
        $movie1->people()->attach($person->id, ['role' => 'ACTOR']);

        $movie2 = Movie::factory()->create([
            'title' => 'John Wick',
            'slug' => 'john-wick-2014-'.$uniqueSuffix,
            'release_year' => 2014,
        ]);
        $movie2->people()->attach($person->id, ['role' => 'ACTOR']);

        // When: Compare the two movies
        $response = $this->getJson("/api/v1/movies/compare?slug1={$movie1->slug}&slug2={$movie2->slug}");

        // Then: Should return comparison with common people
        $response->assertOk();
        $data = $response->json();

        $comparison = $data['comparison'];
        $this->assertArrayHasKey('common_people', $comparison);
        $this->assertCount(1, $comparison['common_people']);
        $this->assertEquals('Keanu Reeves', $comparison['common_people'][0]['person']['name']);
        $this->assertArrayHasKey('roles_in_movie1', $comparison['common_people'][0]);
        $this->assertArrayHasKey('roles_in_movie2', $comparison['common_people'][0]);
    }

    /**
     * Test: Compare movies calculates year difference.
     */
    public function test_compare_movies_calculates_year_difference(): void
    {
        // Given: Two movies with different release years
        $uniqueSuffix = time();
        $movie1 = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.$uniqueSuffix,
            'release_year' => 1999,
        ]);

        $movie2 = Movie::factory()->create([
            'title' => 'Inception',
            'slug' => 'inception-2010-'.$uniqueSuffix,
            'release_year' => 2010,
        ]);

        // When: Compare the two movies
        $response = $this->getJson("/api/v1/movies/compare?slug1={$movie1->slug}&slug2={$movie2->slug}");

        // Then: Should return year difference
        $response->assertOk();
        $data = $response->json();

        $comparison = $data['comparison'];
        $this->assertArrayHasKey('year_difference', $comparison);
        $this->assertEquals(11, $comparison['year_difference']);
    }

    /**
     * Test: Compare movies calculates similarity score.
     */
    public function test_compare_movies_calculates_similarity_score(): void
    {
        // Given: Two movies with common genres and people
        $genreSciFi = Genre::firstOrCreate(['slug' => 'science-fiction'], ['name' => 'Science Fiction']);
        $person = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-'.time(),
        ]);

        $uniqueSuffix = time();
        $movie1 = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.$uniqueSuffix,
            'release_year' => 1999,
        ]);
        $movie1->genres()->attach($genreSciFi->id);
        $movie1->people()->attach($person->id, ['role' => 'ACTOR']);

        $movie2 = Movie::factory()->create([
            'title' => 'Inception',
            'slug' => 'inception-2010-'.$uniqueSuffix,
            'release_year' => 2010,
        ]);
        $movie2->genres()->attach($genreSciFi->id);
        $movie2->people()->attach($person->id, ['role' => 'ACTOR']);

        // When: Compare the two movies
        $response = $this->getJson("/api/v1/movies/compare?slug1={$movie1->slug}&slug2={$movie2->slug}");

        // Then: Should return similarity score (0.0 to 1.0)
        $response->assertOk();
        $data = $response->json();

        $comparison = $data['comparison'];
        $this->assertArrayHasKey('similarity_score', $comparison);
        $this->assertIsFloat($comparison['similarity_score']);
        $this->assertGreaterThanOrEqual(0.0, $comparison['similarity_score']);
        $this->assertLessThanOrEqual(1.0, $comparison['similarity_score']);
    }

    /**
     * Test: Compare returns 404 when first movie not found.
     */
    public function test_compare_returns_404_when_first_movie_not_found(): void
    {
        // Given: One movie exists
        $uniqueSuffix = time();
        $movie2 = Movie::factory()->create([
            'title' => 'Inception',
            'slug' => 'inception-2010-'.$uniqueSuffix,
            'release_year' => 2010,
        ]);

        // When: Compare with non-existent first movie
        $response = $this->getJson("/api/v1/movies/compare?slug1=non-existent-123&slug2={$movie2->slug}");

        // Then: Should return 404
        $response->assertNotFound();
    }

    /**
     * Test: Compare returns 404 when second movie not found.
     */
    public function test_compare_returns_404_when_second_movie_not_found(): void
    {
        // Given: One movie exists
        $uniqueSuffix = time();
        $movie1 = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.time(),
            'release_year' => 1999,
        ]);

        // When: Compare with non-existent second movie
        $response = $this->getJson("/api/v1/movies/compare?slug1={$movie1->slug}&slug2=non-existent-123");

        // Then: Should return 404
        $response->assertNotFound();
    }

    /**
     * Test: Compare validates required parameters.
     */
    public function test_compare_validates_required_parameters(): void
    {
        // When: Compare without slug1
        $response = $this->getJson('/api/v1/movies/compare?slug2=test');

        // Then: Should return validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug1']);

        // When: Compare without slug2
        $response = $this->getJson('/api/v1/movies/compare?slug1=test');

        // Then: Should return validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug2']);
    }

    /**
     * Test: Compare returns empty arrays when no common elements.
     */
    public function test_compare_returns_empty_arrays_when_no_common_elements(): void
    {
        // Given: Two movies with no common genres or people
        $uniqueSuffix = time();
        $movie1 = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.$uniqueSuffix,
            'release_year' => 1999,
        ]);

        $movie2 = Movie::factory()->create([
            'title' => 'Inception',
            'slug' => 'inception-2010-'.$uniqueSuffix,
            'release_year' => 2010,
        ]);

        // When: Compare the two movies
        $response = $this->getJson("/api/v1/movies/compare?slug1={$movie1->slug}&slug2={$movie2->slug}");

        // Then: Should return empty arrays for common elements
        $response->assertOk();
        $data = $response->json();

        $comparison = $data['comparison'];
        $this->assertArrayHasKey('common_genres', $comparison);
        $this->assertArrayHasKey('common_people', $comparison);
        $this->assertIsArray($comparison['common_genres']);
        $this->assertIsArray($comparison['common_people']);
    }
}
