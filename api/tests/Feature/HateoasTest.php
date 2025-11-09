<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HateoasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_movies_list_contains_links(): void
    {
        $res = $this->getJson('/api/v1/movies');
        $res->assertOk();
        $movies = collect($res->json('data'));
        $this->assertNotEmpty($movies, 'Expected at least one movie in the listing');

        $first = $movies->first();
        $this->assertArrayHasKey('_links', $first);
        $this->assertArrayHasKey('self', $first['_links']);
        $this->assertArrayHasKey('people', $first['_links']);
        $this->assertIsArray($first['_links']['people']);

        $movieWithPeopleLinks = $movies->first(fn (array $movie): bool => ! empty($movie['_links']['people']));
        $this->assertNotNull($movieWithPeopleLinks, 'Expected at least one movie with people links');

        foreach ($movieWithPeopleLinks['_links']['people'] as $link) {
            $this->assertIsArray($link);
            $this->assertArrayHasKey('href', $link);
            $this->assertArrayHasKey('title', $link);
            $this->assertStringContainsString('/api/v1/people/', $link['href']);
            $this->assertNotEmpty($link['title']);
        }
    }

    public function test_movie_show_contains_links(): void
    {
        $list = $this->getJson('/api/v1/movies');
        $list->assertOk();
        $slug = collect($list->json('data'))
            ->first(fn (array $movie): bool => ! empty($movie['_links']['people']))['slug'] ?? null;
        $this->assertNotNull($slug, 'Expected at least one movie with related people to test HATEOAS links');

        $res = $this->getJson("/api/v1/movies/{$slug}");
        $res->assertOk();
        $body = $res->json();
        $this->assertArrayHasKey('_links', $body);
        $this->assertArrayHasKey('self', $body['_links']);
        $this->assertArrayHasKey('generate', $body['_links']);
        $this->assertArrayHasKey('people', $body['_links']);
        $this->assertIsArray($body['_links']['people']);
        $this->assertNotEmpty($body['_links']['people']);

        foreach ($body['_links']['people'] as $link) {
            $this->assertArrayHasKey('href', $link);
            $this->assertArrayHasKey('title', $link);
            $this->assertStringContainsString('/api/v1/people/', $link['href']);
            $this->assertNotEmpty($link['title']);
        }
    }

    public function test_person_show_contains_links(): void
    {
        // Find any person via movies listing
        $movies = $this->getJson('/api/v1/movies');
        $movies->assertOk();
        $slug = null;
        foreach ($movies->json('data') as $m) {
            if (! empty($m['people'][0]['slug'])) {
                $slug = $m['people'][0]['slug'];
                break;
            }
        }
        $this->assertNotNull($slug, 'Expected at least one linked person');

        $res = $this->getJson('/api/v1/people/'.$slug);
        $res->assertOk();
        $body = $res->json();
        $this->assertArrayHasKey('_links', $body);
        $this->assertArrayHasKey('self', $body['_links']);
        $this->assertArrayHasKey('movies', $body['_links']);
    }
}
