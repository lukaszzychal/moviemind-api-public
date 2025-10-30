<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class GenerateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
                 'slug'
             ])
             ->assertJson([
                 'status' => 'PENDING',
                 'slug' => 'the-matrix',
             ]);
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

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => 'keanu-reeves',
        ]);

        $resp->assertStatus(202)
             ->assertJsonStructure([
                 'job_id',
                 'status',
                 'message',
                 'slug'
             ])
             ->assertJson([
                 'status' => 'PENDING',
                 'slug' => 'keanu-reeves',
             ]);
    }

    public function test_generate_actor_returns_invalid_entity_type(): void
    {
        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'ACTOR',
            'entity_id' => 'keanu-reeves',
        ]);

        $resp->assertStatus(400)
             ->assertJson(['error' => 'Invalid entity type']);
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


