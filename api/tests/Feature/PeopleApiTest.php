<?php

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PeopleApiTest extends TestCase
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

    public function test_list_people_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/people');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'slug', 'bios_count',
                    ],
                ],
            ]);

        $this->assertIsInt($response->json('data.0.bios_count'));
    }

    public function test_list_people_with_search_query(): void
    {
        $response = $this->getJson('/api/v1/people?q=Christopher');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'slug', 'bios_count',
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
            if (! empty($m['people'][0]['slug'])) {
                $personSlug = $m['people'][0]['slug'];
                break;
            }
        }
        $this->assertNotNull($personSlug, 'Expected at least one person linked to movies');

        $res = $this->getJson('/api/v1/people/'.$personSlug);
        $res->assertOk()->assertJsonStructure(['id', 'slug', 'name', 'bios_count']);

        $this->assertIsInt($res->json('bios_count'));

        $res->assertJsonPath('_links.self.href', url('/api/v1/people/'.$personSlug));

        $movieLinks = $res->json('_links.movies');
        $this->assertIsArray($movieLinks);
        $this->assertNotEmpty($movieLinks, 'Expected person links to include movies entries');
        $this->assertArrayHasKey('href', $movieLinks[0]);
        $this->assertStringStartsWith(url('/api/v1/movies/'), $movieLinks[0]['href']);
    }

    public function test_show_person_response_is_cached(): void
    {
        $movies = $this->getJson('/api/v1/movies');
        $movies->assertOk();

        $personSlug = null;
        foreach ($movies->json('data') as $m) {
            if (! empty($m['people'][0]['slug'])) {
                $personSlug = $m['people'][0]['slug'];
                break;
            }
        }

        $this->assertNotNull($personSlug, 'Expected at least one person linked to movies');
        $this->assertFalse(Cache::has('person:'.$personSlug.':bio:default'));

        $first = $this->getJson('/api/v1/people/'.$personSlug);
        $first->assertOk();

        $this->assertTrue(Cache::has('person:'.$personSlug.':bio:default'));
        $this->assertSame($first->json(), Cache::get('person:'.$personSlug.':bio:default'));

        $personId = $first->json('id');
        Person::where('id', $personId)->update(['name' => 'Changed Name']);

        $second = $this->getJson('/api/v1/people/'.$personSlug);
        $second->assertOk();
        $this->assertSame($first->json(), $second->json());
    }

    public function test_show_person_can_select_specific_bio(): void
    {
        $movies = $this->getJson('/api/v1/movies');
        $movies->assertOk();

        $personSlug = null;
        foreach ($movies->json('data') as $m) {
            if (! empty($m['people'][0]['slug'])) {
                $personSlug = $m['people'][0]['slug'];
                break;
            }
        }

        $this->assertNotNull($personSlug);

        $person = Person::with('bios')->where('slug', $personSlug)->firstOrFail();
        $baselineBioId = $person->default_bio_id;

        $alternateBio = $person->bios()->create([
            'locale' => 'en-US',
            'text' => 'Alternate biography generated for testing.',
            'context_tag' => 'critical',
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);

        $response = $this->getJson(sprintf(
            '/api/v1/people/%s?bio_id=%s',
            $personSlug,
            $alternateBio->id
        ));

        $response->assertOk()
            ->assertJsonPath('selected_bio.id', $alternateBio->id)
            ->assertJsonPath('default_bio.id', $baselineBioId);

        $cacheKey = $personSlug.':bio:'.$alternateBio->id;
        $this->assertTrue(Cache::has('person:'.$cacheKey));
        $this->assertSame($response->json(), Cache::get('person:'.$cacheKey));
    }
}
