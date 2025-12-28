<?php

namespace Tests\Feature;

use App\Enums\ContextTag;
use App\Models\Movie;
use App\Models\MovieDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MoviesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_list_movies_returns_ok(): void
    {
        // GIVEN: Movies exist in database (from seeders)

        // WHEN: Requesting list of movies
        $response = $this->getJson('/api/v1/movies');

        // THEN: Should return OK with correct structure
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'title', 'release_year', 'director', 'descriptions_count',
                    ],
                ],
            ]);

        // THEN: Descriptions count should be an integer
        $this->assertIsInt($response->json('data.0.descriptions_count'));
    }

    public function test_list_movies_with_search_query(): void
    {
        // GIVEN: Movies exist in database (from seeders)

        // WHEN: Searching for movies with query parameter
        $response = $this->getJson('/api/v1/movies?q=Matrix');

        // THEN: Should return OK with correct structure
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'title', 'release_year', 'director', 'descriptions_count',
                    ],
                ],
            ]);
    }

    public function test_list_movies_search_is_case_insensitive(): void
    {
        // GIVEN: Movies exist in database (from seeders)

        // WHEN: Searching with different case variations
        $response1 = $this->getJson('/api/v1/movies?q=matrix');
        $response2 = $this->getJson('/api/v1/movies?q=MATRIX');
        $response3 = $this->getJson('/api/v1/movies?q=Matrix');

        // THEN: All requests should return OK
        $response1->assertOk();
        $response2->assertOk();
        $response3->assertOk();

        // THEN: All should return the same results (case-insensitive)
        $this->assertSame(
            count($response1->json('data')),
            count($response2->json('data')),
            'Search should be case-insensitive'
        );
        $this->assertSame(
            count($response2->json('data')),
            count($response3->json('data')),
            'Search should be case-insensitive'
        );
    }

    public function test_show_movie_returns_ok(): void
    {
        // GIVEN: Movies exist in database (from seeders)
        $slug = $this->getFirstMovieSlug();

        // WHEN: Requesting a specific movie
        $response = $this->getJson('/api/v1/movies/'.$slug);

        // THEN: Should return OK with correct structure
        $response->assertOk()
            ->assertJsonStructure(['id', 'slug', 'title', 'descriptions_count']);

        // THEN: Descriptions count should be an integer
        $this->assertIsInt($response->json('descriptions_count'));

        // THEN: Should contain HATEOAS links
        $response->assertJsonPath('_links.self.href', url('/api/v1/movies/'.$slug));

        $peopleLinks = $response->json('_links.people');
        $this->assertIsArray($peopleLinks);
        $this->assertNotEmpty($peopleLinks, 'Expected movie links to include people entries');
        $this->assertArrayHasKey('href', $peopleLinks[0]);
        $this->assertStringStartsWith(url('/api/v1/people/'), $peopleLinks[0]['href']);
    }

    public function test_show_movie_response_is_cached(): void
    {
        // GIVEN: Movies exist in database and cache is empty
        $slug = $this->getFirstMovieSlug();
        $this->assertFalse(Cache::has('movie:'.$slug.':desc:default'));

        // WHEN: Requesting a movie for the first time
        $first = $this->getJson('/api/v1/movies/'.$slug);
        $first->assertOk();

        // THEN: Response should be cached
        $this->assertTrue(Cache::has('movie:'.$slug.':desc:default'));
        $this->assertSame($first->json(), Cache::get('movie:'.$slug.':desc:default'));

        // WHEN: Movie data is updated but cache is not invalidated
        $movieId = $first->json('id');
        Movie::where('id', $movieId)->update(['title' => 'Changed Title']);

        // WHEN: Requesting the same movie again
        $second = $this->getJson('/api/v1/movies/'.$slug);
        $second->assertOk();

        // THEN: Should return cached response (not updated data)
        $this->assertSame($first->json(), $second->json());
    }

    /**
     * Scenario: Select specific description by ID
     *
     * Given: A movie exists with multiple descriptions (different context_tag values)
     * When: A GET request is made with ?description_id={id} parameter
     * Then:
     *   - The response should contain selected_description with the requested description
     *   - The response should contain default_description with the baseline description
     *   - The response should be cached with a specific cache key
     */
    public function test_show_movie_can_select_specific_description(): void
    {
        // GIVEN: Movie with multiple descriptions
        $movie = Movie::with('descriptions')->firstOrFail();
        $baselineDescriptionId = $movie->default_description_id;

        $altDescription = $movie->descriptions()->create([
            'locale' => 'en-US',
            'text' => 'Alternate historical description generated for testing.',
            'context_tag' => ContextTag::CRITICAL->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);

        // WHEN: GET request with description_id parameter
        $response = $this->getJson(sprintf(
            '/api/v1/movies/%s?description_id=%s',
            $movie->slug,
            $altDescription->id
        ));

        // THEN: Response contains selected and default descriptions
        $response->assertOk()
            ->assertJsonPath('selected_description.id', $altDescription->id)
            ->assertJsonPath('default_description.id', $baselineDescriptionId);

        // THEN: Response is cached
        $cacheKey = $movie->slug.':desc:'.$altDescription->id;
        $this->assertTrue(Cache::has('movie:'.$cacheKey));
        $this->assertSame($response->json(), Cache::get('movie:'.$cacheKey));
    }

    public function test_unique_constraint_prevents_duplicate_same_context_tag(): void
    {
        $movie = Movie::firstOrFail();

        // Delete any existing descriptions for this movie to avoid conflicts
        MovieDescription::where('movie_id', $movie->id)->delete();

        // Create first description with modern context_tag (active, not archived)
        $firstDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'First description with modern context tag.',
            'context_tag' => ContextTag::MODERN->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
            'version_number' => 1,
            'archived_at' => null, // Active description
        ]);

        // Try to create duplicate with same (movie_id, locale, context_tag) while first is active
        // This should fail with unique constraint violation (partial unique index for active descriptions)
        // Note: SQLite doesn't support partial unique indexes, so we skip the constraint test in SQLite
        $isPostgres = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql';

        if ($isPostgres) {
            // In PostgreSQL, partial unique index should prevent duplicate active descriptions
            $this->expectException(\Illuminate\Database\QueryException::class);

            MovieDescription::create([
                'movie_id' => $movie->id,
                'locale' => 'en-US',
                'text' => 'Duplicate description with modern context tag.',
                'context_tag' => ContextTag::MODERN->value,
                'origin' => 'GENERATED',
                'ai_model' => 'mock',
                'version_number' => 2,
                'archived_at' => null, // Also active - should fail in PostgreSQL
            ]);
        } else {
            // In SQLite, partial unique index is not supported, so we verify the constraint
            // is documented but not enforced at database level
            // The application should handle this through business logic (e.g., RegenerateMovieDescriptionJob)
            $this->markTestSkipped('Partial unique indexes are not supported in SQLite. Constraint is enforced in PostgreSQL production environment.');
        }
    }

    public function test_multiple_context_tags_for_same_movie_allowed(): void
    {
        $movie = Movie::firstOrFail();

        // Delete any existing descriptions for this movie to avoid conflicts
        MovieDescription::where('movie_id', $movie->id)->delete();

        // Create description with modern context_tag
        $modernDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'Modern description.',
            'context_tag' => ContextTag::MODERN->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);

        // Create description with humorous context_tag for the same movie (different context_tag)
        $humorousDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'Humorous description.',
            'context_tag' => ContextTag::HUMOROUS->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);

        // Both should exist in database
        $this->assertDatabaseHas('movie_descriptions', [
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'context_tag' => ContextTag::MODERN->value,
        ]);

        $this->assertDatabaseHas('movie_descriptions', [
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'context_tag' => ContextTag::HUMOROUS->value,
        ]);

        // Verify both descriptions belong to the same movie
        $this->assertEquals($movie->id, $modernDescription->movie_id);
        $this->assertEquals($movie->id, $humorousDescription->movie_id);
        $this->assertNotEquals($modernDescription->id, $humorousDescription->id);
    }

    // Helper methods for test data setup

    /**
     * Get the slug of the first movie from the list endpoint.
     */
    private function getFirstMovieSlug(): string
    {
        $index = $this->getJson('/api/v1/movies');
        $index->assertOk();
        $slug = $index->json('data.0.slug');
        $this->assertNotNull($slug, 'Expected at least one movie in the list');

        return $slug;
    }
}
