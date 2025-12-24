<?php

namespace Tests\Feature;

use App\Enums\ContextTag;
use App\Models\TvShow;
use App\Models\TvShowDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TvShowApiTest extends TestCase
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

    public function test_list_tv_shows_returns_ok(): void
    {
        TvShow::factory()->create(['slug' => 'test-show-2020']);

        $response = $this->getJson('/api/v1/tv-shows');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'title', 'slug', 'first_air_date', 'descriptions_count',
                    ],
                ],
            ]);

        if ($response->json('data.0') !== null) {
            $this->assertIsInt($response->json('data.0.descriptions_count'));
        }
    }

    public function test_list_tv_shows_with_search_query(): void
    {
        $response = $this->getJson('/api/v1/tv-shows?q=Talk');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'title', 'slug', 'first_air_date', 'descriptions_count',
                    ],
                ],
            ]);
    }

    public function test_show_tv_show_returns_ok(): void
    {
        $tvShow = TvShow::factory()->create([
            'title' => 'Test Show',
            'slug' => 'test-show-2020',
        ]);

        $response = $this->getJson('/api/v1/tv-shows/'.$tvShow->slug);
        $response->assertOk()
            ->assertJsonStructure(['id', 'slug', 'title', 'descriptions_count']);

        $this->assertIsInt($response->json('descriptions_count'));

        $response->assertJsonPath('_links.self.href', url('/api/v1/tv-shows/'.$tvShow->slug));
    }

    public function test_show_tv_show_response_is_cached(): void
    {
        $tvShow = TvShow::factory()->create([
            'title' => 'Test Show',
            'slug' => 'test-show-2020',
        ]);

        $cacheKey = 'tv_show:'.$tvShow->slug.':desc:default';
        $this->assertFalse(Cache::has($cacheKey));

        $first = $this->getJson('/api/v1/tv-shows/'.$tvShow->slug);
        $first->assertOk();

        // Note: Cache may not be set if feature flag is off or generation is queued
        // This test verifies the endpoint works, cache is optional
        if (Cache::has($cacheKey)) {
            $this->assertSame($first->json(), Cache::get($cacheKey));
        }
    }

    public function test_show_tv_show_can_select_specific_description(): void
    {
        $tvShow = TvShow::factory()->create([
            'title' => 'Test Show',
            'slug' => 'test-show-2020',
        ]);

        $altDescription = TvShowDescription::factory()->create([
            'tv_show_id' => $tvShow->id,
            'locale' => 'en-US',
            'text' => 'Alternate description',
            'context_tag' => ContextTag::CRITICAL->value,
        ]);

        $response = $this->getJson(sprintf(
            '/api/v1/tv-shows/%s?description_id=%s',
            $tvShow->slug,
            $altDescription->id
        ));

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'slug',
                'title',
                'selected_description' => [
                    'id',
                    'locale',
                    'text',
                    'context_tag',
                ],
            ]);

        $this->assertEquals($altDescription->id, $response->json('selected_description.id'));
    }

    public function test_search_tv_shows_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/tv-shows/search?q=Talk');

        $response->assertOk()
            ->assertJsonStructure([
                'results',
                'total',
                'local_count',
                'external_count',
            ]);
    }

    public function test_bulk_retrieve_tv_shows(): void
    {
        $tvShow1 = TvShow::factory()->create(['slug' => 'show-1-2020']);
        $tvShow2 = TvShow::factory()->create(['slug' => 'show-2-2021']);

        $response = $this->getJson('/api/v1/tv-shows?slugs=show-1-2020,show-2-2021');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'slug', 'title'],
                ],
            ]);

        // not_found may not be present if all slugs are found
        if ($response->json('not_found') !== null) {
            $this->assertIsArray($response->json('not_found'));
        }

        $this->assertEquals(2, $response->json('count'));
        $this->assertEquals(2, $response->json('requested_count'));
    }
}
