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
        // GIVEN: People exist in database (from seeders)

        // WHEN: Requesting list of people
        $response = $this->getJson('/api/v1/people');

        // THEN: Should return OK with correct structure
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'slug', 'bios_count',
                    ],
                ],
            ]);

        // THEN: Bios count should be an integer
        $this->assertIsInt($response->json('data.0.bios_count'));
    }

    public function test_list_people_with_search_query(): void
    {
        // GIVEN: People exist in database (from seeders)

        // WHEN: Searching for people with query parameter
        $response = $this->getJson('/api/v1/people?q=Christopher');

        // THEN: Should return OK with correct structure
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
        // GIVEN: Person exists and has at least one bio
        $personSlug = $this->getFirstPersonSlugFromMovies();
        $this->ensurePersonHasBio($personSlug);

        // WHEN: Requesting a specific person
        $res = $this->getJson('/api/v1/people/'.$personSlug);

        // THEN: Should return OK with correct structure
        $res->assertOk()->assertJsonStructure(['id', 'slug', 'name', 'bios_count']);

        // THEN: Bios count should be an integer
        $this->assertIsInt($res->json('bios_count'));

        // THEN: Should contain HATEOAS links
        $res->assertJsonPath('_links.self.href', url('/api/v1/people/'.$personSlug));

        $movieLinks = $res->json('_links.movies');
        $this->assertIsArray($movieLinks);
        $this->assertNotEmpty($movieLinks, 'Expected person links to include movies entries');
        $this->assertArrayHasKey('href', $movieLinks[0]);
        $this->assertStringStartsWith(url('/api/v1/movies/'), $movieLinks[0]['href']);
    }

    public function test_show_person_response_is_cached(): void
    {
        // GIVEN: Person exists, has bio, and cache is empty
        $personSlug = $this->getFirstPersonSlugFromMovies();
        $this->ensurePersonHasBio($personSlug);
        $this->assertFalse(Cache::has('person:'.$personSlug.':bio:default'));

        // WHEN: Requesting a person for the first time
        $first = $this->getJson('/api/v1/people/'.$personSlug);
        $first->assertOk();

        // THEN: Response should be cached
        $this->assertTrue(Cache::has('person:'.$personSlug.':bio:default'));
        $this->assertSame($first->json(), Cache::get('person:'.$personSlug.':bio:default'));

        // WHEN: Person data is updated but cache is not invalidated
        $personId = $first->json('id');
        Person::where('id', $personId)->update(['name' => 'Changed Name']);

        // WHEN: Requesting the same person again
        $second = $this->getJson('/api/v1/people/'.$personSlug);
        $second->assertOk();

        // THEN: Should return cached response (not updated data)
        $this->assertSame($first->json(), $second->json());
    }

    public function test_show_person_can_select_specific_bio(): void
    {
        // GIVEN: Person with multiple bios
        $personSlug = $this->getFirstPersonSlugFromMovies();
        $person = Person::with('bios')->where('slug', $personSlug)->firstOrFail();
        $baselineBioId = $person->default_bio_id;

        $alternateBio = $person->bios()->create([
            'locale' => 'en-US',
            'text' => 'Alternate biography generated for testing.',
            'context_tag' => 'critical',
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);

        // WHEN: GET request with bio_id parameter
        $response = $this->getJson(sprintf(
            '/api/v1/people/%s?bio_id=%s',
            $personSlug,
            $alternateBio->id
        ));

        // THEN: Response contains selected and default bios
        $response->assertOk()
            ->assertJsonPath('selected_bio.id', $alternateBio->id)
            ->assertJsonPath('default_bio.id', $baselineBioId);

        // THEN: Response is cached
        $cacheKey = $personSlug.':bio:'.$alternateBio->id;
        $this->assertTrue(Cache::has('person:'.$cacheKey));
        $this->assertSame($response->json(), Cache::get('person:'.$cacheKey));
    }

    // Helper methods for test data setup

    /**
     * Get the slug of the first person linked to movies.
     */
    private function getFirstPersonSlugFromMovies(): string
    {
        $movies = $this->getJson('/api/v1/movies');
        $movies->assertOk();

        foreach ($movies->json('data') as $m) {
            if (! empty($m['people'][0]['slug'])) {
                return $m['people'][0]['slug'];
            }
        }

        $this->fail('Expected at least one person linked to movies');
    }

    /**
     * Ensure person has at least one bio (person without bio returns 202, not 200).
     */
    private function ensurePersonHasBio(string $personSlug, string $text = 'Test bio'): void
    {
        $person = Person::where('slug', $personSlug)->first();
        if ($person && ! $person->bios()->exists()) {
            $person->bios()->create([
                'locale' => \App\Enums\Locale::EN_US,
                'text' => $text,
                'context_tag' => \App\Enums\ContextTag::DEFAULT,
                'origin' => \App\Enums\DescriptionOrigin::GENERATED,
            ]);
        }
    }
}
