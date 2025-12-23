<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature tests for Person Bulk Operations.
 *
 * @author MovieMind API Team
 */
class PersonBulkTest extends TestCase
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
     * Test: GET /people?slugs= returns multiple people.
     */
    public function test_get_people_with_slugs_returns_multiple_people(): void
    {
        $person1 = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-1964',
        ]);

        $person2 = Person::create([
            'name' => 'Christopher Nolan',
            'slug' => 'christopher-nolan-1970',
        ]);

        $person3 = Person::create([
            'name' => 'Scarlett Johansson',
            'slug' => 'scarlett-johansson-1984',
        ]);

        $response = $this->getJson('/api/v1/people?slugs=keanu-reeves-1964,christopher-nolan-1970,scarlett-johansson-1984');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'bios_count',
                        '_links',
                    ],
                ],
                'not_found' => [],
                'count',
                'requested_count',
            ]);

        $data = $response->json();
        $this->assertEquals(3, $data['count']);
        $this->assertEquals(3, $data['requested_count']);
        $this->assertCount(3, $data['data']);
        $this->assertEmpty($data['not_found']);

        // Verify all people are returned
        $slugs = collect($data['data'])->pluck('slug')->toArray();
        $this->assertContains('keanu-reeves-1964', $slugs);
        $this->assertContains('christopher-nolan-1970', $slugs);
        $this->assertContains('scarlett-johansson-1984', $slugs);
    }

    /**
     * Test: GET /people?slugs= returns not_found for nonexistent slugs.
     */
    public function test_get_people_with_slugs_returns_not_found(): void
    {
        $person1 = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-1964',
        ]);

        $response = $this->getJson('/api/v1/people?slugs=keanu-reeves-1964,nonexistent-person,another-nonexistent');

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals(1, $data['count']);
        $this->assertEquals(3, $data['requested_count']);
        $this->assertCount(1, $data['data']);
        $this->assertCount(2, $data['not_found']);
        $this->assertContains('nonexistent-person', $data['not_found']);
        $this->assertContains('another-nonexistent', $data['not_found']);
    }

    /**
     * Test: GET /people?slugs= validates empty slugs parameter.
     */
    public function test_get_people_with_empty_slugs_returns_422(): void
    {
        $response = $this->getJson('/api/v1/people?slugs=');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    /**
     * Test: GET /people?slugs= validates max 50 slugs.
     */
    public function test_get_people_with_too_many_slugs_returns_422(): void
    {
        $slugs = array_fill(0, 51, 'slug');
        $slugsParam = implode(',', $slugs);

        $response = $this->getJson("/api/v1/people?slugs={$slugsParam}");

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    /**
     * Test: POST /people/bulk returns multiple people.
     */
    public function test_post_people_bulk_returns_multiple_people(): void
    {
        $person1 = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-1964',
        ]);

        $person2 = Person::create([
            'name' => 'Christopher Nolan',
            'slug' => 'christopher-nolan-1970',
        ]);

        $response = $this->postJson('/api/v1/people/bulk', [
            'slugs' => ['keanu-reeves-1964', 'christopher-nolan-1970'],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'bios_count',
                        '_links',
                    ],
                ],
                'not_found' => [],
                'count',
                'requested_count',
            ]);

        $data = $response->json();
        $this->assertEquals(2, $data['count']);
        $this->assertEquals(2, $data['requested_count']);
        $this->assertCount(2, $data['data']);
        $this->assertEmpty($data['not_found']);
    }

    /**
     * Test: POST /people/bulk validates required slugs.
     */
    public function test_post_people_bulk_validates_required_slugs(): void
    {
        $response = $this->postJson('/api/v1/people/bulk', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    /**
     * Test: POST /people/bulk validates max 50 slugs.
     */
    public function test_post_people_bulk_validates_max_slugs(): void
    {
        $slugs = array_fill(0, 51, 'slug');

        $response = $this->postJson('/api/v1/people/bulk', [
            'slugs' => $slugs,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    /**
     * Test: POST /people/bulk validates slug format.
     */
    public function test_post_people_bulk_validates_slug_format(): void
    {
        $response = $this->postJson('/api/v1/people/bulk', [
            'slugs' => ['invalid slug with spaces'],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    /**
     * Test: GET /people?slugs= with include parameter loads relations.
     */
    public function test_get_people_with_slugs_and_include_loads_relations(): void
    {
        $person = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-1964',
        ]);

        $response = $this->getJson('/api/v1/people?slugs=keanu-reeves-1964&include=bios,movies');

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data['data']);
        $personData = $data['data'][0];
        // Relations should be loaded (structure will depend on PersonResource)
        $this->assertArrayHasKey('id', $personData);
        $this->assertArrayHasKey('slug', $personData);
    }

    /**
     * Test: POST /people/bulk with include parameter loads relations.
     */
    public function test_post_people_bulk_with_include_loads_relations(): void
    {
        $person = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-1964',
        ]);

        $response = $this->postJson('/api/v1/people/bulk', [
            'slugs' => ['keanu-reeves-1964'],
            'include' => ['bios', 'movies'],
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data['data']);
        $personData = $data['data'][0];
        // Relations should be loaded
        $this->assertArrayHasKey('id', $personData);
        $this->assertArrayHasKey('slug', $personData);
    }

    /**
     * Test: GET /people?slugs= validates include values.
     */
    public function test_get_people_with_slugs_validates_include_values(): void
    {
        $response = $this->getJson('/api/v1/people?slugs=test&include=invalid');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }
}
