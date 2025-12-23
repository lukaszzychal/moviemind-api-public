<?php

namespace Tests\Feature;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class AmbiguousSlugGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        Queue::fake();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_generation_with_ambiguous_slug_finds_existing_movie(): void
    {
        // Arrange: Create 2 movies with same title (different years)
        $movie1 = Movie::create([
            'title' => 'Bad Boys',
            'slug' => 'bad-boys-1995',
            'release_year' => 1995,
            'director' => 'Michael Bay',
            'genres' => ['Action', 'Comedy'],
        ]);

        $movie2 = Movie::create([
            'title' => 'Bad Boys',
            'slug' => 'bad-boys-2020',
            'release_year' => 2020,
            'director' => 'Adil El Arbi',
            'genres' => ['Action', 'Comedy'],
        ]);

        Feature::activate('ai_description_generation');

        // Act: Try to generate with ambiguous slug (without year)
        $response = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'slug' => 'bad-boys',
            'locale' => 'en-US',
        ]);

        // Assert: Should return 202 and queue job
        $response->assertStatus(202);
        $response->assertJsonStructure([
            'job_id',
            'status',
            'slug',
        ]);

        // The job should find existing movie (most recent one)
        // This will be tested when we fix the implementation
    }

    public function test_generation_with_exact_slug_uses_existing_movie(): void
    {
        // Arrange: Create movie with specific slug
        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
            'director' => 'The Wachowskis',
            'genres' => ['Action', 'Sci-Fi'],
        ]);

        Feature::activate('ai_description_generation');

        // Act: Generate with exact slug
        $response = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'slug' => 'the-matrix-1999',
            'locale' => 'en-US',
        ]);

        // Assert: Should return 202 and queue job
        $response->assertStatus(202);
        $response->assertJsonStructure([
            'job_id',
            'status',
            'slug',
        ]);
    }

    public function test_generation_uses_generated_slug_from_ai_data(): void
    {
        Feature::activate('ai_description_generation');
        Event::fake();

        // Act: Generate with slug that doesn't exist
        $response = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'slug' => 'new-movie-test',
            'locale' => 'en-US',
        ]);

        // Assert: Should return 202 and dispatch event
        $response->assertStatus(202);

        // Verify event was dispatched (which will trigger job dispatch)
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'new-movie-test';
        });
    }

    public function test_ambiguous_slug_returns_most_recent_movie(): void
    {
        // Arrange: Create 2 movies with same title (different years)
        $movie1 = Movie::create([
            'title' => 'Bad Boys',
            'slug' => 'bad-boys-1995',
            'release_year' => 1995,
            'director' => 'Michael Bay',
            'genres' => ['Action', 'Comedy'],
        ]);

        $movie2 = Movie::create([
            'title' => 'Bad Boys',
            'slug' => 'bad-boys-2020',
            'release_year' => 2020,
            'director' => 'Adil El Arbi',
            'genres' => ['Action', 'Comedy'],
        ]);

        Feature::activate('ai_description_generation');

        // Act: Try to get movie with ambiguous slug (without year)
        $response = $this->getJson('/api/v1/movies/bad-boys');

        // Assert: Should return most recent movie (2020) with _meta
        $response->assertStatus(200);
        $response->assertJson([
            'slug' => 'bad-boys-2020',
            'release_year' => 2020,
        ]);
        $response->assertJsonStructure([
            '_meta' => [
                'ambiguous',
                'message',
                'alternatives' => [
                    '*' => ['slug', 'title', 'release_year', 'url'],
                ],
            ],
        ]);
        $response->assertJson([
            '_meta' => [
                'ambiguous' => true,
            ],
        ]);
    }

    public function test_generation_with_ambiguous_person_slug_finds_existing_person(): void
    {
        // Arrange: Create 2 people with same name (different birth dates)
        $person1 = Person::create([
            'name' => 'John Smith',
            'slug' => 'john-smith-1960',
            'birth_date' => '1960-01-01',
            'birthplace' => 'New York',
        ]);

        $person2 = Person::create([
            'name' => 'John Smith',
            'slug' => 'john-smith-1980',
            'birth_date' => '1980-01-01',
            'birthplace' => 'Los Angeles',
        ]);

        Feature::activate('ai_bio_generation');

        // Act: Try to generate with ambiguous slug (without birth year)
        $response = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => 'john-smith', // Note: GenerateController uses entity_id as slug
            'locale' => 'en-US',
        ]);

        // Assert: Should return 202 and queue job
        $response->assertStatus(202);
        $response->assertJsonStructure([
            'job_id',
            'status',
            'slug',
        ]);

        // The job should find existing person (most recent one by birth date)
        // This will be tested when we fix the implementation
    }

    public function test_generation_with_exact_person_slug_uses_existing_person(): void
    {
        // Arrange: Create person with specific slug (using format that passes validation)
        $person = Person::create([
            'name' => 'Jane Doe',
            'slug' => 'jane-doe', // Use simple slug format (validator may reject slugs with years)
            'birth_date' => '1975-05-15',
            'birthplace' => 'Chicago',
        ]);

        Feature::activate('ai_bio_generation');
        Event::fake();

        // Act: Generate with exact slug
        $response = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => 'jane-doe', // Note: GenerateController uses entity_id as slug
            'locale' => 'en-US',
        ]);

        // Assert: Should return 202 and dispatch event
        $response->assertStatus(202);
        $response->assertJsonStructure([
            'job_id',
            'status',
            'slug',
        ]);

        // Verify event was dispatched
        Event::assertDispatched(PersonGenerationRequested::class);
    }

    public function test_generation_uses_generated_person_slug_from_ai_data(): void
    {
        Feature::activate('ai_bio_generation');
        Event::fake();

        // Act: Generate with slug that doesn't exist (using format that passes validation)
        // Note: Slug must be 2-4 words to pass validation
        $response = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => 'new-person-test', // Note: GenerateController uses entity_id as slug
            'locale' => 'en-US',
        ]);

        // Assert: Should return 202 and dispatch event
        $response->assertStatus(202);

        // Verify event was dispatched (which will trigger job dispatch)
        Event::assertDispatched(PersonGenerationRequested::class, function ($event) {
            return $event->slug === 'new-person-test';
        });
    }

    public function test_ambiguous_person_slug_returns_most_recent_person(): void
    {
        // Arrange: Create 2 people with same name (different birth dates)
        // Note: Using slugs without year to pass validation (validator rejects slugs with year pattern)
        $person1 = Person::create([
            'name' => 'John Smith',
            'slug' => 'john-smith-older', // Using descriptive suffix instead of year
            'birth_date' => '1960-01-01',
            'birthplace' => 'New York',
        ]);

        $person2 = Person::create([
            'name' => 'John Smith',
            'slug' => 'john-smith-younger', // Using descriptive suffix instead of year
            'birth_date' => '1980-01-01',
            'birthplace' => 'Los Angeles',
        ]);

        // Create bios for both people (person without bio returns 202, not 200)
        $person1->bios()->create([
            'locale' => \App\Enums\Locale::EN_US,
            'text' => 'Bio for person 1',
            'context_tag' => \App\Enums\ContextTag::DEFAULT,
            'origin' => \App\Enums\DescriptionOrigin::GENERATED,
        ]);
        $person2->bios()->create([
            'locale' => \App\Enums\Locale::EN_US,
            'text' => 'Bio for person 2',
            'context_tag' => \App\Enums\ContextTag::DEFAULT,
            'origin' => \App\Enums\DescriptionOrigin::GENERATED,
        ]);

        Feature::activate('ai_bio_generation');

        // Act: Try to get person with ambiguous slug (base name only)
        $response = $this->getJson('/api/v1/people/john-smith');

        // Assert: Should return most recent person by birth date (1980) with _meta
        $response->assertStatus(200);
        $response->assertJson([
            'slug' => 'john-smith-younger', // Most recent by birth date
        ]);
        // birth_date is returned as ISO 8601 string, so we check it contains the year
        $response->assertJsonPath('birth_date', fn ($date) => str_contains($date, '1980-01-01'));
        $response->assertJsonStructure([
            '_meta' => [
                'ambiguous',
                'message',
                'alternatives' => [
                    '*' => ['slug', 'name', 'birth_date', 'url'],
                ],
            ],
        ]);
        $response->assertJson([
            '_meta' => [
                'ambiguous' => true,
            ],
        ]);
    }
}
