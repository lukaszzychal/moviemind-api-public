<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeopleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_list_people_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/people');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name',
                        '_links',
                    ],
                ],
            ]);
    }

    public function test_list_people_with_query_parameter(): void
    {
        $response = $this->getJson('/api/v1/people?q=christopher');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name',
                        '_links',
                    ],
                ],
            ]);

        // Verify search works (if any results)
        $data = $response->json('data');
        if (count($data) > 0) {
            $this->assertStringContainsStringIgnoringCase('christopher', $data[0]['name']);
        }
    }

    public function test_list_people_with_role_filter(): void
    {
        $response = $this->getJson('/api/v1/people?role=DIRECTOR');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name',
                        '_links',
                    ],
                ],
            ]);
    }

    public function test_list_people_with_role_and_query(): void
    {
        $response = $this->getJson('/api/v1/people?q=christopher&role=DIRECTOR');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name',
                        '_links',
                    ],
                ],
            ]);
    }

    public function test_show_person_returns_payload(): void
    {
        $movies = $this->getJson('/api/v1/movies');
        $movies->assertOk();

        $personSlug = null;
        foreach ($movies->json('data') as $m) {
            // First try director (most common, always created when director name is valid)
            if (! empty($m['director']['slug'])) {
                $personSlug = $m['director']['slug'];
                break;
            }
            // Fallback: try people (actors)
            if (! empty($m['people'][0]['slug'])) {
                $personSlug = $m['people'][0]['slug'];
                break;
            }
        }
        $this->assertNotNull($personSlug, 'Expected at least one person linked to movies (director or actor)');

        $res = $this->getJson('/api/v1/people/'.$personSlug);
        $res->assertOk()->assertJsonStructure(['id', 'slug', 'name']);
    }
}
