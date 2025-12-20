<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RelationshipType;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\MovieRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for genre filtering in related movies endpoint.
 *
 * @author MovieMind API Team
 */
class MovieRelatedGenreFilteringTest extends TestCase
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
     * Test: Filter related movies by single genre.
     */
    public function test_related_movies_can_be_filtered_by_single_genre(): void
    {
        // Given: Movies with different genres
        $genreSciFi = Genre::firstOrCreate(['slug' => 'science-fiction'], ['name' => 'Science Fiction']);
        $genreAction = Genre::firstOrCreate(['slug' => 'action'], ['name' => 'Action']);

        $uniqueSuffix = time();
        $mainMovie = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.$uniqueSuffix,
            'release_year' => 1999,
        ]);
        $mainMovie->genres()->attach($genreSciFi->id);

        $relatedMovie1 = Movie::factory()->create([
            'title' => 'Inception',
            'slug' => 'inception-2010-'.$uniqueSuffix,
            'release_year' => 2010,
        ]);
        $relatedMovie1->genres()->attach($genreSciFi->id);

        $relatedMovie2 = Movie::factory()->create([
            'title' => 'Fast & Furious',
            'slug' => 'fast-furious-2001-'.$uniqueSuffix,
            'release_year' => 2001,
        ]);
        $relatedMovie2->genres()->attach($genreAction->id);

        // Create relationship
        MovieRelationship::create([
            'movie_id' => $mainMovie->id,
            'related_movie_id' => $relatedMovie1->id,
            'relationship_type' => RelationshipType::SEQUEL,
        ]);

        MovieRelationship::create([
            'movie_id' => $mainMovie->id,
            'related_movie_id' => $relatedMovie2->id,
            'relationship_type' => RelationshipType::SEQUEL,
        ]);

        // When: Request related movies filtered by science-fiction genre
        $response = $this->getJson("/api/v1/movies/{$mainMovie->slug}/related?genre=science-fiction");

        // Then: Should return only movies with science-fiction genre
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('related_movies', $data);
        $this->assertCount(1, $data['related_movies']);
        $this->assertEquals('inception-2010-'.$uniqueSuffix, $data['related_movies'][0]['slug']);
    }

    /**
     * Test: Filter related movies by multiple genres (AND logic).
     */
    public function test_related_movies_can_be_filtered_by_multiple_genres(): void
    {
        // Given: Movies with different genre combinations
        $genreSciFi = Genre::firstOrCreate(['slug' => 'science-fiction'], ['name' => 'Science Fiction']);
        $genreAction = Genre::firstOrCreate(['slug' => 'action'], ['name' => 'Action']);
        $genreDrama = Genre::firstOrCreate(['slug' => 'drama'], ['name' => 'Drama']);

        $uniqueSuffix = time();
        $mainMovie = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.$uniqueSuffix,
            'release_year' => 1999,
        ]);
        $mainMovie->genres()->attach([$genreSciFi->id, $genreAction->id]);

        // Movie with both sci-fi and action
        $relatedMovie1 = Movie::factory()->create([
            'title' => 'Blade Runner',
            'slug' => 'blade-runner-1982-'.$uniqueSuffix,
            'release_year' => 1982,
        ]);
        $relatedMovie1->genres()->attach([$genreSciFi->id, $genreAction->id]);

        // Movie with only sci-fi
        $relatedMovie2 = Movie::factory()->create([
            'title' => '2001: A Space Odyssey',
            'slug' => '2001-space-odyssey-1968-'.$uniqueSuffix,
            'release_year' => 1968,
        ]);
        $relatedMovie2->genres()->attach($genreSciFi->id);

        // Movie with sci-fi and drama (not action)
        $relatedMovie3 = Movie::factory()->create([
            'title' => 'Interstellar',
            'slug' => 'interstellar-2014-'.$uniqueSuffix,
            'release_year' => 2014,
        ]);
        $relatedMovie3->genres()->attach([$genreSciFi->id, $genreDrama->id]);

        // Create relationships
        MovieRelationship::create([
            'movie_id' => $mainMovie->id,
            'related_movie_id' => $relatedMovie1->id,
            'relationship_type' => RelationshipType::SEQUEL,
        ]);

        MovieRelationship::create([
            'movie_id' => $mainMovie->id,
            'related_movie_id' => $relatedMovie2->id,
            'relationship_type' => RelationshipType::SEQUEL,
        ]);

        MovieRelationship::create([
            'movie_id' => $mainMovie->id,
            'related_movie_id' => $relatedMovie3->id,
            'relationship_type' => RelationshipType::SEQUEL,
        ]);

        // When: Request related movies filtered by both science-fiction AND action
        $response = $this->getJson("/api/v1/movies/{$mainMovie->slug}/related?genres[]=science-fiction&genres[]=action");

        // Then: Should return only movies with both genres
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('related_movies', $data);
        $this->assertCount(1, $data['related_movies']);
        $this->assertEquals('blade-runner-1982-'.$uniqueSuffix, $data['related_movies'][0]['slug']);
    }

    /**
     * Test: Filter works with type filter (collection).
     */
    public function test_genre_filter_works_with_type_collection(): void
    {
        // Given: Movies with genres and collection relationship
        $genreSciFi = Genre::firstOrCreate(['slug' => 'science-fiction'], ['name' => 'Science Fiction']);
        $genreAction = Genre::firstOrCreate(['slug' => 'action'], ['name' => 'Action']);

        $uniqueSuffix = time();
        $mainMovie = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.$uniqueSuffix,
            'release_year' => 1999,
        ]);
        $mainMovie->genres()->attach($genreSciFi->id);

        $sequelMovie = Movie::factory()->create([
            'title' => 'The Matrix Reloaded',
            'slug' => 'the-matrix-reloaded-2003-'.$uniqueSuffix,
            'release_year' => 2003,
        ]);
        $sequelMovie->genres()->attach($genreSciFi->id);

        $actionMovie = Movie::factory()->create([
            'title' => 'Fast & Furious',
            'slug' => 'fast-furious-2001-'.$uniqueSuffix,
            'release_year' => 2001,
        ]);
        $actionMovie->genres()->attach($genreAction->id);

        // Create collection relationship (sequel)
        MovieRelationship::create([
            'movie_id' => $mainMovie->id,
            'related_movie_id' => $sequelMovie->id,
            'relationship_type' => RelationshipType::SEQUEL,
        ]);

        MovieRelationship::create([
            'movie_id' => $mainMovie->id,
            'related_movie_id' => $actionMovie->id,
            'relationship_type' => RelationshipType::SEQUEL,
        ]);

        // When: Request collection movies filtered by science-fiction
        $response = $this->getJson("/api/v1/movies/{$mainMovie->slug}/related?type=collection&genre=science-fiction");

        // Then: Should return only collection movies with science-fiction genre
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('related_movies', $data);
        $this->assertCount(1, $data['related_movies']);
        $this->assertEquals('the-matrix-reloaded-2003-'.$uniqueSuffix, $data['related_movies'][0]['slug']);
    }

    /**
     * Test: Empty result when no movies match genre filter.
     */
    public function test_genre_filter_returns_empty_when_no_matches(): void
    {
        // Given: Movies with different genres
        $genreSciFi = Genre::firstOrCreate(['slug' => 'science-fiction'], ['name' => 'Science Fiction']);
        $genreAction = Genre::firstOrCreate(['slug' => 'action'], ['name' => 'Action']);

        $uniqueSuffix = time();
        $mainMovie = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.$uniqueSuffix,
            'release_year' => 1999,
        ]);
        $mainMovie->genres()->attach($genreSciFi->id);

        $relatedMovie = Movie::factory()->create([
            'title' => 'Fast & Furious',
            'slug' => 'fast-furious-2001-'.$uniqueSuffix,
            'release_year' => 2001,
        ]);
        $relatedMovie->genres()->attach($genreAction->id);

        MovieRelationship::create([
            'movie_id' => $mainMovie->id,
            'related_movie_id' => $relatedMovie->id,
            'relationship_type' => RelationshipType::SEQUEL,
        ]);

        // When: Request related movies filtered by non-matching genre
        $response = $this->getJson("/api/v1/movies/{$mainMovie->slug}/related?genre=drama");

        // Then: Should return empty results
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('related_movies', $data);
        $this->assertCount(0, $data['related_movies']);
        $this->assertEquals(0, $data['count']);
    }

    /**
     * Test: Genre filter is case-insensitive.
     */
    public function test_genre_filter_is_case_insensitive(): void
    {
        // Given: Movie with genre
        $genreSciFi = Genre::firstOrCreate(['slug' => 'science-fiction'], ['name' => 'Science Fiction']);

        $uniqueSuffix = time();
        $mainMovie = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.$uniqueSuffix,
            'release_year' => 1999,
        ]);
        $mainMovie->genres()->attach($genreSciFi->id);

        $relatedMovie = Movie::factory()->create([
            'title' => 'Inception',
            'slug' => 'inception-2010-'.$uniqueSuffix,
            'release_year' => 2010,
        ]);
        $relatedMovie->genres()->attach($genreSciFi->id);

        MovieRelationship::create([
            'movie_id' => $mainMovie->id,
            'related_movie_id' => $relatedMovie->id,
            'relationship_type' => RelationshipType::SEQUEL,
        ]);

        // When: Request with uppercase genre slug
        $response = $this->getJson("/api/v1/movies/{$mainMovie->slug}/related?genre=SCIENCE-FICTION");

        // Then: Should still work (case-insensitive)
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('related_movies', $data);
        $this->assertCount(1, $data['related_movies']);
    }
}
