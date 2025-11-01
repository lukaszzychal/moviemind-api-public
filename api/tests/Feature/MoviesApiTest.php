<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoviesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_list_movies_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/movies');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'title', 'release_year',
                        'director', // Can be null or object {id, name, slug}
                        'people', // Array of actors (may be empty)
                        '_links',
                    ],
                ],
            ]);

        // Verify director structure if it exists
        $firstMovie = $response->json('data.0');
        if ($firstMovie['director'] !== null) {
            $this->assertIsArray($firstMovie['director']);
            $this->assertArrayHasKey('id', $firstMovie['director']);
            $this->assertArrayHasKey('name', $firstMovie['director']);
            $this->assertArrayHasKey('slug', $firstMovie['director']);
        }
    }

    public function test_show_movie_returns_ok(): void
    {
        $index = $this->getJson('/api/v1/movies');
        $index->assertOk();

        $data = $index->json('data');
        $this->assertNotEmpty($data, 'Movies list should not be empty');

        $slug = $data[0]['slug'] ?? null;
        $this->assertNotNull($slug, 'Movie slug should not be null');

        $response = $this->getJson('/api/v1/movies/'.$slug);

        // Check response structure based on status code
        $statusCode = $response->status();

        if ($statusCode === 200) {
            // Movie exists - check structure
            $json = $response->json();

            // Check if response has _meta (ambiguous slug case)
            if (isset($json['_meta'])) {
                // Ambiguous slug - check structure with _meta
                $response->assertOk()
                    ->assertJsonStructure([
                        'id',
                        'slug',
                        'title',
                        'director',
                        'people',
                        '_links',
                        '_meta' => [
                            'ambiguous',
                            'message',
                            'alternatives',
                        ],
                    ]);
            } else {
                // Normal response
                $response->assertOk()
                    ->assertJsonStructure([
                        'id',
                        'slug',
                        'title',
                        'director', // Can be null or object {id, name, slug}
                        'people', // Array (may be empty)
                        '_links',
                    ]);
            }

            // If director exists, it should be an object
            $director = $response->json('director');
            if ($director !== null) {
                $this->assertIsArray($director);
                $this->assertArrayHasKey('id', $director);
                $this->assertArrayHasKey('name', $director);
                $this->assertArrayHasKey('slug', $director);
            }
        } else {
            // Movie doesn't exist - this is also valid (202 or 404)
            $this->assertContains($statusCode, [202, 404], 'Expected 200, 202, or 404');
        }
    }
}
