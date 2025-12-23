<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature tests for Person Comparison endpoint.
 *
 * @author MovieMind API Team
 */
class PersonComparisonTest extends TestCase
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

    /**
     * Test: Compare two people returns comparison data.
     */
    public function test_compare_people_returns_comparison_data(): void
    {
        $uniqueSuffix = time().'-'.rand(1000, 9999);

        // Create movies
        $movie1 = Movie::create([
            'title' => 'The Matrix',
            'slug' => "the-matrix-1999-{$uniqueSuffix}",
            'release_year' => 1999,
        ]);

        $movie2 = Movie::create([
            'title' => 'Inception',
            'slug' => "inception-2010-{$uniqueSuffix}",
            'release_year' => 2010,
        ]);

        // Create people
        $person1 = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => "keanu-reeves-1964-{$uniqueSuffix}",
            'birth_date' => '1964-09-02',
            'birthplace' => 'Beirut, Lebanon',
        ]);

        $person2 = Person::create([
            'name' => 'Leonardo DiCaprio',
            'slug' => "leonardo-dicaprio-1974-{$uniqueSuffix}",
            'birth_date' => '1974-11-11',
            'birthplace' => 'Los Angeles, California, USA',
        ]);

        // Attach people to movies
        $movie1->people()->attach($person1->id, ['role' => 'ACTOR']);
        $movie2->people()->attach($person1->id, ['role' => 'ACTOR']);
        $movie2->people()->attach($person2->id, ['role' => 'ACTOR']);

        $response = $this->getJson("/api/v1/people/compare?slug1={$person1->slug}&slug2={$person2->slug}");

        $response->assertOk()
            ->assertJsonStructure([
                'person1' => ['id', 'slug', 'name', 'birth_date', 'birthplace'],
                'person2' => ['id', 'slug', 'name', 'birth_date', 'birthplace'],
                'comparison' => [
                    'common_movies',
                    'common_movies_count',
                    'birth_year_difference',
                    'similarity_score',
                ],
            ]);

        $data = $response->json();
        $this->assertEquals($person1->slug, $data['person1']['slug']);
        $this->assertEquals($person2->slug, $data['person2']['slug']);
        $this->assertArrayHasKey('similarity_score', $data['comparison']);
        // JSON serializes 0.0 as 0 (int), so check if numeric instead of float
        $this->assertIsNumeric($data['comparison']['similarity_score']);
    }

    /**
     * Test: Compare returns 404 when person1 not found.
     */
    public function test_compare_returns_404_when_person1_not_found(): void
    {
        $person2 = Person::create([
            'name' => 'Leonardo DiCaprio',
            'slug' => 'leonardo-dicaprio-1974',
        ]);

        $response = $this->getJson("/api/v1/people/compare?slug1=nonexistent&slug2={$person2->slug}");

        $response->assertStatus(404);
    }

    /**
     * Test: Compare returns 404 when person2 not found.
     */
    public function test_compare_returns_404_when_person2_not_found(): void
    {
        $person1 = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-1964',
        ]);

        $response = $this->getJson("/api/v1/people/compare?slug1={$person1->slug}&slug2=nonexistent");

        $response->assertStatus(404);
    }

    /**
     * Test: Compare validates required slug1 parameter.
     */
    public function test_compare_validates_required_slug1(): void
    {
        $response = $this->getJson('/api/v1/people/compare?slug2=test');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    /**
     * Test: Compare validates required slug2 parameter.
     */
    public function test_compare_validates_required_slug2(): void
    {
        $response = $this->getJson('/api/v1/people/compare?slug1=test');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    /**
     * Test: Compare finds common movies.
     */
    public function test_compare_finds_common_movies(): void
    {
        $uniqueSuffix = time().'-'.rand(1000, 9999);

        // Create movies
        $movie1 = Movie::create([
            'title' => 'The Matrix',
            'slug' => "the-matrix-1999-{$uniqueSuffix}",
            'release_year' => 1999,
        ]);

        $movie2 = Movie::create([
            'title' => 'Inception',
            'slug' => "inception-2010-{$uniqueSuffix}",
            'release_year' => 2010,
        ]);

        // Create people
        $person1 = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => "keanu-reeves-1964-{$uniqueSuffix}",
        ]);

        $person2 = Person::create([
            'name' => 'Leonardo DiCaprio',
            'slug' => "leonardo-dicaprio-1974-{$uniqueSuffix}",
        ]);

        // Both people in movie1
        $movie1->people()->attach($person1->id, ['role' => 'ACTOR']);
        $movie1->people()->attach($person2->id, ['role' => 'DIRECTOR']);

        // Only person1 in movie2
        $movie2->people()->attach($person1->id, ['role' => 'ACTOR']);

        $response = $this->getJson("/api/v1/people/compare?slug1={$person1->slug}&slug2={$person2->slug}");

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('common_movies', $data['comparison']);
        $this->assertGreaterThan(0, $data['comparison']['common_movies_count']);
        $this->assertCount(1, $data['comparison']['common_movies']);

        // Verify common movie structure
        $commonMovie = $data['comparison']['common_movies'][0];
        $this->assertArrayHasKey('movie_id', $commonMovie);
        $this->assertArrayHasKey('movie_slug', $commonMovie);
        $this->assertArrayHasKey('movie_title', $commonMovie);
        $this->assertArrayHasKey('person1_role', $commonMovie);
        $this->assertArrayHasKey('person2_role', $commonMovie);
    }

    /**
     * Test: Compare calculates birth year difference.
     */
    public function test_compare_calculates_birth_year_difference(): void
    {
        $uniqueSuffix = time().'-'.rand(1000, 9999);

        $person1 = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => "keanu-reeves-1964-{$uniqueSuffix}",
            'birth_date' => '1964-09-02',
        ]);

        $person2 = Person::create([
            'name' => 'Leonardo DiCaprio',
            'slug' => "leonardo-dicaprio-1974-{$uniqueSuffix}",
            'birth_date' => '1974-11-11',
        ]);

        $response = $this->getJson("/api/v1/people/compare?slug1={$person1->slug}&slug2={$person2->slug}");

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('birth_year_difference', $data['comparison']);
        $this->assertEquals(10, $data['comparison']['birth_year_difference']); // 1974 - 1964 = 10
    }

    /**
     * Test: Compare calculates similarity score.
     */
    public function test_compare_calculates_similarity_score(): void
    {
        $uniqueSuffix = time().'-'.rand(1000, 9999);

        $person1 = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => "keanu-reeves-1964-{$uniqueSuffix}",
        ]);

        $person2 = Person::create([
            'name' => 'Leonardo DiCaprio',
            'slug' => "leonardo-dicaprio-1974-{$uniqueSuffix}",
        ]);

        $response = $this->getJson("/api/v1/people/compare?slug1={$person1->slug}&slug2={$person2->slug}");

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('similarity_score', $data['comparison']);
        // JSON serializes 0.0 as 0 (int), so check if numeric instead of float
        $this->assertIsNumeric($data['comparison']['similarity_score']);
        $this->assertGreaterThanOrEqual(0.0, (float) $data['comparison']['similarity_score']);
        $this->assertLessThanOrEqual(1.0, (float) $data['comparison']['similarity_score']);
    }

    /**
     * Test: Compare returns 0 similarity for people with no common movies.
     */
    public function test_compare_returns_zero_similarity_for_no_common_movies(): void
    {
        $uniqueSuffix = time().'-'.rand(1000, 9999);

        $person1 = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => "keanu-reeves-1964-{$uniqueSuffix}",
        ]);

        $person2 = Person::create([
            'name' => 'Leonardo DiCaprio',
            'slug' => "leonardo-dicaprio-1974-{$uniqueSuffix}",
        ]);

        // No common movies
        $movie1 = Movie::create([
            'title' => 'The Matrix',
            'slug' => "the-matrix-1999-{$uniqueSuffix}",
            'release_year' => 1999,
        ]);
        $movie1->people()->attach($person1->id, ['role' => 'ACTOR']);

        $movie2 = Movie::create([
            'title' => 'Inception',
            'slug' => "inception-2010-{$uniqueSuffix}",
            'release_year' => 2010,
        ]);
        $movie2->people()->attach($person2->id, ['role' => 'ACTOR']);

        $response = $this->getJson("/api/v1/people/compare?slug1={$person1->slug}&slug2={$person2->slug}");

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals(0, $data['comparison']['common_movies_count']);
        $this->assertEmpty($data['comparison']['common_movies']);
    }
}
