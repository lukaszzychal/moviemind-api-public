<?php

namespace Tests\Feature;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class GenerateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Queue::fake();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_generate_movie_blocked_when_flag_off(): void
    {
        Feature::deactivate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'the-matrix',
        ]);

        $resp->assertStatus(403)
            ->assertJson(['error' => 'Feature not available']);
    }

    public function test_generate_movie_allowed_when_flag_on(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'the-matrix',
        ]);

        $resp->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'message',
                'slug',
            ])
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'the-matrix',
                'locale' => 'en-US',
            ]);

        // Verify Event was dispatched
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'the-matrix'
                && $event->locale === 'en-US';
        });

        // Note: Queue::fake() prevents Listener from executing, so we only check Event
        // If Event is dispatched and Listener is registered, Job will be queued
    }

    public function test_generate_movie_respects_locale_and_context(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'the-matrix',
            'locale' => 'pl-PL',
            'context_tag' => 'modern',
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'the-matrix',
                'locale' => 'pl-PL',
                'context_tag' => 'modern',
            ]);

        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'the-matrix'
                && $event->locale === 'pl-PL'
                && $event->contextTag === 'modern';
        });
    }

    public function test_generate_movie_existing_slug_triggers_regeneration_flow(): void
    {
        Feature::activate('ai_description_generation');
        $movie = Movie::firstOrFail();

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->slug,
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => $movie->slug,
                'existing_id' => $movie->id,
                'description_id' => $movie->default_description_id,
                'locale' => 'en-US',
            ]);

        Event::assertDispatched(MovieGenerationRequested::class, function ($event) use ($movie) {
            return $event->slug === $movie->slug
                && $event->locale === 'en-US';
        });
    }

    public function test_generate_person_blocked_when_flag_off(): void
    {
        Feature::deactivate('ai_bio_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => 'keanu-reeves',
        ]);

        $resp->assertStatus(403)
            ->assertJson(['error' => 'Feature not available']);
    }

    public function test_generate_person_allowed_when_flag_on(): void
    {
        Feature::activate('ai_bio_generation');

        // Use a slug that doesn't exist in seeders
        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => 'new-person-slug',
        ]);

        $resp->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'message',
                'slug',
            ])
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'new-person-slug',
                'locale' => 'en-US',
            ]);

        // Verify Event was dispatched
        Event::assertDispatched(PersonGenerationRequested::class, function ($event) {
            return $event->slug === 'new-person-slug'
                && $event->locale === 'en-US';
        });

        // Note: Queue::fake() prevents Listener from executing, so we only check Event
        // If Event is dispatched and Listener is registered, Job will be queued
    }

    public function test_generate_person_respects_locale_and_context(): void
    {
        Feature::activate('ai_bio_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => 'new-person-slug',
            'locale' => 'pl-PL',
            'context_tag' => 'critical',
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'new-person-slug',
                'locale' => 'pl-PL',
                'context_tag' => 'critical',
            ]);

        Event::assertDispatched(PersonGenerationRequested::class, function ($event) {
            return $event->slug === 'new-person-slug'
                && $event->locale === 'pl-PL'
                && $event->contextTag === 'critical';
        });
    }

    public function test_generate_person_existing_slug_triggers_regeneration_flow(): void
    {
        Feature::activate('ai_bio_generation');
        $person = Person::firstOrFail();

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => $person->slug,
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => $person->slug,
                'existing_id' => $person->id,
                'bio_id' => $person->default_bio_id,
                'locale' => 'en-US',
            ]);

        Event::assertDispatched(PersonGenerationRequested::class, function ($event) use ($person) {
            return $event->slug === $person->slug
                && $event->locale === 'en-US';
        });
    }

    public function test_generate_actor_allowed_when_flag_on(): void
    {
        Feature::activate('ai_bio_generation');

        // Use a slug that doesn't exist in seeders
        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'ACTOR',
            'entity_id' => 'new-actor-slug',
        ]);

        $resp->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'message',
                'slug',
            ])
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'new-actor-slug',
                'locale' => 'en-US',
            ]);

        // Verify Event was dispatched (ACTOR treated same as PERSON)
        Event::assertDispatched(PersonGenerationRequested::class, function ($event) {
            return $event->slug === 'new-actor-slug'
                && $event->locale === 'en-US';
        });

        // Note: Queue::fake() prevents Listener from executing, so we only check Event
        // If Event is dispatched and Listener is registered, Job will be queued
    }

    public function test_generate_requires_string_entity_id(): void
    {
        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 123,
        ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['entity_id']);
    }

    public function test_generate_movie_with_default_context_tag(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'default-context-movie',
            // No context_tag provided - should use default
        ]);

        $resp->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'slug',
                'locale',
            ]);

        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'default-context-movie'
                && $event->locale === 'en-US'
                && ($event->contextTag === null || $event->contextTag === 'DEFAULT');
        });
    }

    public function test_generate_movie_with_humorous_context_tag(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'humorous-context-movie',
            'context_tag' => 'humorous',
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'humorous-context-movie',
                'locale' => 'en-US',
                'context_tag' => 'humorous',
            ]);

        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'humorous-context-movie'
                && $event->locale === 'en-US'
                && $event->contextTag === 'humorous';
        });
    }

    public function test_generate_movie_context_tag_null(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'null-context-movie',
            'context_tag' => null,
        ]);

        $resp->assertStatus(202);

        // When context_tag is explicitly null, should fallback to default
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'null-context-movie';
        });
    }

    public function test_generate_movie_with_invalid_context_tag(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'invalid-context-movie',
            'context_tag' => 'invalid-tag',
        ]);

        // Should still accept the request, but context_tag should be normalized/fallback to default
        $resp->assertStatus(202);

        // The event should be dispatched, but context_tag handling depends on implementation
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'invalid-context-movie';
        });
    }
}
