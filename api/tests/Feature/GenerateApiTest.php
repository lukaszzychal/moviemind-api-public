<?php

namespace Tests\Feature;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
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
            ]);

        // Verify Event was dispatched
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'the-matrix';
        });

        // Note: Queue::fake() prevents Listener from executing, so we only check Event
        // If Event is dispatched and Listener is registered, Job will be queued
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
            ]);

        // Verify Event was dispatched
        Event::assertDispatched(PersonGenerationRequested::class, function ($event) {
            return $event->slug === 'new-person-slug';
        });

        // Note: Queue::fake() prevents Listener from executing, so we only check Event
        // If Event is dispatched and Listener is registered, Job will be queued
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
            ]);

        // Verify Event was dispatched (ACTOR treated same as PERSON)
        Event::assertDispatched(PersonGenerationRequested::class, function ($event) {
            return $event->slug === 'new-actor-slug';
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
