<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SearchPeopleTest extends TestCase
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

    public function test_search_people_returns_ok_with_query(): void
    {
        $response = $this->getJson('/api/v1/people/search?q=Keanu');

        $response->assertOk()
            ->assertJsonStructure([
                'results',
                'total',
                'local_count',
                'external_count',
                'match_type',
                'confidence',
            ]);
    }

    public function test_search_people_with_birth_year_filter(): void
    {
        $response = $this->getJson('/api/v1/people/search?q=Keanu&birth_year=1964');

        $response->assertOk()
            ->assertJsonStructure([
                'results',
                'total',
                'local_count',
                'external_count',
                'match_type',
            ]);

        // All results should match the birth year
        $results = $response->json('results');
        foreach ($results as $result) {
            if (isset($result['birth_year'])) {
                $this->assertEquals(1964, $result['birth_year']);
            }
        }
    }

    public function test_search_people_with_birthplace_filter(): void
    {
        // Create a person with specific birthplace for testing
        Person::firstOrCreate(
            ['slug' => 'keanu-reeves-1964-test'],
            [
                'name' => 'Keanu Reeves',
                'birth_date' => '1964-09-02',
                'birthplace' => 'Beirut, Lebanon',
            ]
        );

        $response = $this->getJson('/api/v1/people/search?q=Keanu&birthplace=Beirut, Lebanon');

        $response->assertOk()
            ->assertJsonStructure([
                'results',
                'total',
            ]);
    }

    public function test_search_people_caches_results(): void
    {
        // First request
        $response1 = $this->getJson('/api/v1/people/search?q=Keanu');
        $response1->assertOk();

        // Second request (should use cache)
        $response2 = $this->getJson('/api/v1/people/search?q=Keanu');
        $response2->assertOk();

        // Both should return same results
        $this->assertEquals($response1->json('total'), $response2->json('total'));
    }

    public function test_search_people_with_pagination(): void
    {
        $response = $this->getJson('/api/v1/people/search?q=John&page=1&per_page=10');

        $response->assertOk()
            ->assertJsonStructure([
                'results',
                'total',
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total_pages',
                    'total',
                    'has_next_page',
                    'has_previous_page',
                ],
            ]);

        $pagination = $response->json('pagination');
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(10, $pagination['per_page']);
    }

    public function test_search_people_with_sorting(): void
    {
        $response = $this->getJson('/api/v1/people/search?q=John&sort=name&order=asc');

        $response->assertOk()
            ->assertJsonStructure([
                'results',
                'total',
            ]);

        $results = $response->json('results');
        if (count($results) > 1) {
            // API sorts with strcasecmp; assert non-descending order (do not assert exact order vs PHP sort)
            $names = array_column($results, 'name');
            for ($i = 0; $i < count($names) - 1; $i++) {
                $this->assertLessThanOrEqual(
                    0,
                    strcasecmp($names[$i], $names[$i + 1]),
                    sprintf('Results should be sorted by name asc: "%s" should come before or equal to "%s"', $names[$i], $names[$i + 1])
                );
            }
        }
    }
}
