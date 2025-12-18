<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmdbIdHiddenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        // Don't run seeders - they may have invalid enum values
    }

    /**
     * Test that Movie API responses do not contain tmdb_id.
     */
    public function test_movie_api_responses_do_not_contain_tmdb_id(): void
    {
        // Arrange: Create movie with tmdb_id
        $movie = Movie::create([
            'title' => 'Test Movie',
            'slug' => 'test-movie-2000',
            'release_year' => 2000,
            'director' => 'Test Director',
            'tmdb_id' => 12345,
        ]);

        // Act: Get movie via API
        $response = $this->getJson("/api/v1/movies/{$movie->slug}");

        // Assert: Response does not contain tmdb_id
        $response->assertStatus(200)
            ->assertJsonMissing(['tmdb_id'])
            ->assertJsonStructure([
                'id',
                'title',
                'slug',
                'release_year',
                'director',
            ]);

        // Verify tmdb_id exists in database but not in response
        $this->assertDatabaseHas('movies', [
            'id' => $movie->id,
            'tmdb_id' => 12345,
        ]);

        $responseData = $response->json();
        $this->assertArrayNotHasKey('tmdb_id', $responseData);
    }

    /**
     * Test that Person API responses do not contain tmdb_id.
     */
    public function test_person_api_responses_do_not_contain_tmdb_id(): void
    {
        // Arrange: Create person with tmdb_id
        $person = Person::create([
            'name' => 'Test Person',
            'slug' => 'test-person',
            'tmdb_id' => 67890,
        ]);

        // Act: Get person via API
        $response = $this->getJson("/api/v1/people/{$person->slug}");

        // Assert: Response does not contain tmdb_id
        $response->assertStatus(200)
            ->assertJsonMissing(['tmdb_id'])
            ->assertJsonStructure([
                'id',
                'name',
                'slug',
            ]);

        // Verify tmdb_id exists in database but not in response
        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'tmdb_id' => 67890,
        ]);

        $responseData = $response->json();
        $this->assertArrayNotHasKey('tmdb_id', $responseData);
    }

    /**
     * Test that Movie list API responses do not contain tmdb_id.
     */
    public function test_movie_list_api_responses_do_not_contain_tmdb_id(): void
    {
        // Arrange: Create movies with tmdb_id
        Movie::create([
            'title' => 'Movie 1',
            'slug' => 'movie-1-2000',
            'release_year' => 2000,
            'tmdb_id' => 11111,
        ]);

        Movie::create([
            'title' => 'Movie 2',
            'slug' => 'movie-2-2001',
            'release_year' => 2001,
            'tmdb_id' => 22222,
        ]);

        // Act: Get movies list via API
        $response = $this->getJson('/api/v1/movies');

        // Assert: Response does not contain tmdb_id in any movie
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                    ],
                ],
            ]);

        $movies = $response->json('data');
        foreach ($movies as $movie) {
            $this->assertArrayNotHasKey('tmdb_id', $movie);
        }
    }

    /**
     * Test that Person list API responses do not contain tmdb_id.
     */
    public function test_person_list_api_responses_do_not_contain_tmdb_id(): void
    {
        // Arrange: Create people with tmdb_id
        Person::create([
            'name' => 'Person 1',
            'slug' => 'person-1',
            'tmdb_id' => 33333,
        ]);

        Person::create([
            'name' => 'Person 2',
            'slug' => 'person-2',
            'tmdb_id' => 44444,
        ]);

        // Act: Get people list via API
        $response = $this->getJson('/api/v1/people');

        // Assert: Response does not contain tmdb_id in any person
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                    ],
                ],
            ]);

        $people = $response->json('data');
        foreach ($people as $person) {
            $this->assertArrayNotHasKey('tmdb_id', $person);
        }
    }

    /**
     * Test that Movie search API responses do not contain tmdb_id.
     */
    public function test_movie_search_api_responses_do_not_contain_tmdb_id(): void
    {
        // Arrange: Create movie with tmdb_id
        $movie = Movie::create([
            'title' => 'Searchable Movie',
            'slug' => 'searchable-movie-2000',
            'release_year' => 2000,
            'tmdb_id' => 55555,
        ]);

        // Act: Search for movie via API
        $response = $this->getJson('/api/v1/movies/search?q=Searchable');

        // Assert: Response does not contain tmdb_id
        $response->assertStatus(200)
            ->assertJsonStructure([
                'results' => [
                    '*' => [
                        'title',
                        'slug',
                    ],
                ],
            ]);

        $results = $response->json('results');
        foreach ($results as $result) {
            $this->assertArrayNotHasKey('tmdb_id', $result);
            // Check nested movie object if exists
            if (isset($result['movie']) && is_array($result['movie'])) {
                $this->assertArrayNotHasKey('tmdb_id', $result['movie']);
            }
        }
    }

    /**
     * Test that Movie with people relation does not expose tmdb_id in people.
     */
    public function test_movie_with_people_relation_does_not_expose_tmdb_id_in_people(): void
    {
        // Arrange: Create movie and person with tmdb_id
        $movie = Movie::create([
            'title' => 'Movie With People',
            'slug' => 'movie-with-people-2000',
            'release_year' => 2000,
            'tmdb_id' => 66666,
        ]);

        $person = Person::create([
            'name' => 'Actor Person',
            'slug' => 'actor-person',
            'tmdb_id' => 77777,
        ]);

        $movie->people()->attach($person->id, [
            'role' => 'ACTOR',
            'character_name' => 'Test Character',
        ]);

        // Act: Get movie with people relation via API
        $response = $this->getJson("/api/v1/movies/{$movie->slug}");

        // Assert: Response does not contain tmdb_id in movie or people
        $response->assertStatus(200)
            ->assertJsonMissing(['tmdb_id']);

        $responseData = $response->json();
        $this->assertArrayNotHasKey('tmdb_id', $responseData);

        // Check people array if present
        if (isset($responseData['people']) && is_array($responseData['people'])) {
            foreach ($responseData['people'] as $personData) {
                $this->assertArrayNotHasKey('tmdb_id', $personData, 'Person in movie relation should not have tmdb_id');
            }
        }
    }

    /**
     * Test that tmdb_id exists in database.
     */
    public function test_tmdb_id_exists_in_database(): void
    {
        // Arrange: Create movie and person with tmdb_id
        $movie = Movie::create([
            'title' => 'Test Movie DB',
            'slug' => 'test-movie-db-2000',
            'release_year' => 2000,
            'tmdb_id' => 88888,
        ]);

        $person = Person::create([
            'name' => 'Test Person DB',
            'slug' => 'test-person-db',
            'tmdb_id' => 99999,
        ]);

        // Assert: tmdb_id exists in database
        $this->assertDatabaseHas('movies', [
            'id' => $movie->id,
            'tmdb_id' => 88888,
        ]);

        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'tmdb_id' => 99999,
        ]);

        // Verify via model
        $movieFromDb = Movie::find($movie->id);
        $this->assertEquals(88888, $movieFromDb->tmdb_id);

        $personFromDb = Person::find($person->id);
        $this->assertEquals(99999, $personFromDb->tmdb_id);
    }
}
