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
        $response = $this->getJson('/api/v1/movies');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'title', 'release_year', 'director', 'descriptions_count',
                    ],
                ],
            ]);

        $this->assertIsInt($response->json('data.0.descriptions_count'));
    }

    public function test_list_movies_with_search_query(): void
    {
        $response = $this->getJson('/api/v1/movies?q=Matrix');

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
        // Test that search works regardless of case
        $response1 = $this->getJson('/api/v1/movies?q=matrix');
        $response2 = $this->getJson('/api/v1/movies?q=MATRIX');
        $response3 = $this->getJson('/api/v1/movies?q=Matrix');

        $response1->assertOk();
        $response2->assertOk();
        $response3->assertOk();

        // All should return the same results (case-insensitive)
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
        $index = $this->getJson('/api/v1/movies');
        $slug = $index->json('data.0.slug');

        $response = $this->getJson('/api/v1/movies/'.$slug);
        $response->assertOk()
            ->assertJsonStructure(['id', 'slug', 'title', 'descriptions_count']);

        $this->assertIsInt($response->json('descriptions_count'));

        $response->assertJsonPath('_links.self.href', url('/api/v1/movies/'.$slug));

        $peopleLinks = $response->json('_links.people');
        $this->assertIsArray($peopleLinks);
        $this->assertNotEmpty($peopleLinks, 'Expected movie links to include people entries');
        $this->assertArrayHasKey('href', $peopleLinks[0]);
        $this->assertStringStartsWith(url('/api/v1/people/'), $peopleLinks[0]['href']);
    }

    public function test_show_movie_response_is_cached(): void
    {
        $index = $this->getJson('/api/v1/movies');
        $slug = $index->json('data.0.slug');

        $this->assertFalse(Cache::has('movie:'.$slug.':desc:default'));

        $first = $this->getJson('/api/v1/movies/'.$slug);
        $first->assertOk();

        $this->assertTrue(Cache::has('movie:'.$slug.':desc:default'));
        $this->assertSame($first->json(), Cache::get('movie:'.$slug.':desc:default'));

        $movieId = $first->json('id');
        Movie::where('id', $movieId)->update(['title' => 'Changed Title']);

        $second = $this->getJson('/api/v1/movies/'.$slug);
        $second->assertOk();
        $this->assertSame($first->json(), $second->json());
    }

    public function test_show_movie_can_select_specific_description(): void
    {
        $movie = Movie::with('descriptions')->firstOrFail();
        $baselineDescriptionId = $movie->default_description_id;

        $altDescription = $movie->descriptions()->create([
            'locale' => 'en-US',
            'text' => 'Alternate historical description generated for testing.',
            'context_tag' => ContextTag::CRITICAL->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);

        $response = $this->getJson(sprintf(
            '/api/v1/movies/%s?description_id=%d',
            $movie->slug,
            $altDescription->id
        ));

        $response->assertOk()
            ->assertJsonPath('selected_description.id', $altDescription->id)
            ->assertJsonPath('default_description.id', $baselineDescriptionId);

        $cacheKey = $movie->slug.':desc:'.$altDescription->id;
        $this->assertTrue(Cache::has('movie:'.$cacheKey));
        $this->assertSame($response->json(), Cache::get('movie:'.$cacheKey));
    }

    public function test_unique_constraint_prevents_duplicate_same_context_tag(): void
    {
        $movie = Movie::firstOrFail();

        // Delete any existing descriptions for this movie to avoid conflicts
        MovieDescription::where('movie_id', $movie->id)->delete();

        // Create first description with modern context_tag
        $firstDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'First description with modern context tag.',
            'context_tag' => ContextTag::MODERN->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);

        // Try to create duplicate with same (movie_id, locale, context_tag)
        // This should fail with unique constraint violation
        $this->expectException(\Illuminate\Database\QueryException::class);

        MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'Duplicate description with modern context tag.',
            'context_tag' => ContextTag::MODERN->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);
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
}
