<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RelationshipType;
use App\Models\Movie;
use App\Models\MovieRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for movie relationships endpoint.
 *
 * @author MovieMind API Team
 */
class MovieRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    /**
     * Scenario: Get related movies for a movie
     *
     * Given: A movie exists with related movies (sequel, prequel)
     * When: A GET request is sent to /api/v1/movies/{slug}/related
     * Then:
     *   - The response status should be 200 OK
     *   - The response should contain the movie and related movies
     *   - Each related movie should have relationship_type and relationship_label
     */
    public function test_get_related_movies_returns_related_movies(): void
    {
        // Given: A movie with related movies
        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
            'director' => 'Wachowski Brothers',
        ]);

        $sequel = Movie::create([
            'title' => 'The Matrix Reloaded',
            'slug' => 'the-matrix-reloaded-2003',
            'release_year' => 2003,
            'director' => 'Wachowski Brothers',
        ]);

        $prequel = Movie::create([
            'title' => 'The Matrix Resurrections',
            'slug' => 'the-matrix-resurrections-2021',
            'release_year' => 2021,
            'director' => 'Lana Wachowski',
        ]);

        MovieRelationship::create([
            'movie_id' => $movie->id,
            'related_movie_id' => $sequel->id,
            'relationship_type' => RelationshipType::SEQUEL,
            'order' => 1,
        ]);

        MovieRelationship::create([
            'movie_id' => $movie->id,
            'related_movie_id' => $prequel->id,
            'relationship_type' => RelationshipType::PREQUEL,
            'order' => 2,
        ]);

        // When: A GET request is sent to /api/v1/movies/{slug}/related
        $response = $this->getJson("/api/v1/movies/{$movie->slug}/related");

        // Then: The response is OK and contains related movies
        $response->assertOk()
            ->assertJsonStructure([
                'movie' => ['id', 'slug', 'title'],
                'related_movies' => [
                    '*' => ['id', 'slug', 'title', 'relationship_type', 'relationship_label', 'relationship_order'],
                ],
                'count',
                '_links',
            ])
            ->assertJsonCount(2, 'related_movies')
            ->assertJsonPath('movie.slug', $movie->slug)
            ->assertJsonPath('count', 2);
    }

    /**
     * Scenario: Filter related movies by relationship type
     *
     * Given: A movie exists with related movies of different types
     * When: A GET request is sent with type filter
     * Then:
     *   - Only movies with specified relationship types should be returned
     */
    public function test_get_related_movies_filters_by_type(): void
    {
        // Given: A movie with related movies of different types
        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
        ]);

        $sequel = Movie::create([
            'title' => 'The Matrix Reloaded',
            'slug' => 'the-matrix-reloaded-2003',
            'release_year' => 2003,
        ]);

        $remake = Movie::create([
            'title' => 'Matrix Remake',
            'slug' => 'matrix-remake-2025',
            'release_year' => 2025,
        ]);

        MovieRelationship::create([
            'movie_id' => $movie->id,
            'related_movie_id' => $sequel->id,
            'relationship_type' => RelationshipType::SEQUEL,
        ]);

        MovieRelationship::create([
            'movie_id' => $movie->id,
            'related_movie_id' => $remake->id,
            'relationship_type' => RelationshipType::REMAKE,
        ]);

        // When: A GET request is sent with type filter for SEQUEL only
        $response = $this->getJson("/api/v1/movies/{$movie->slug}/related?type[]=SEQUEL");

        // Then: Only sequel is returned
        $response->assertOk()
            ->assertJsonCount(1, 'related_movies')
            ->assertJsonPath('related_movies.0.relationship_type', 'SEQUEL')
            ->assertJsonPath('related_movies.0.slug', $sequel->slug);
    }

    /**
     * Scenario: Return 404 when movie not found
     *
     * Given: A movie does not exist
     * When: A GET request is sent to /api/v1/movies/{slug}/related
     * Then:
     *   - The response status should be 404 Not Found
     */
    public function test_get_related_movies_returns_404_when_movie_not_found(): void
    {
        // When: A GET request is sent for non-existent movie
        $response = $this->getJson('/api/v1/movies/non-existent-movie/related');

        // Then: The response is 404
        $response->assertNotFound();
    }

    /**
     * Scenario: Return empty list when no related movies
     *
     * Given: A movie exists but has no related movies
     * When: A GET request is sent to /api/v1/movies/{slug}/related
     * Then:
     *   - The response status should be 200 OK
     *   - The related_movies array should be empty
     */
    public function test_get_related_movies_returns_empty_when_no_relationships(): void
    {
        // Given: A movie with no related movies
        $movie = Movie::create([
            'title' => 'Standalone Movie',
            'slug' => 'standalone-movie-2020',
            'release_year' => 2020,
        ]);

        // When: A GET request is sent
        $response = $this->getJson("/api/v1/movies/{$movie->slug}/related");

        // Then: Empty list is returned
        $response->assertOk()
            ->assertJsonCount(0, 'related_movies')
            ->assertJsonPath('count', 0);
    }
}
