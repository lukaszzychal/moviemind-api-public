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
}
