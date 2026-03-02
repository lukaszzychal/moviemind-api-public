<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_submit_feedback(): void
    {
        $res = $this->postJson('/api/v1/feedback', [
            'message' => 'Great API! Suggestion: add more filters for search.',
            'category' => 'suggestion',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'message' => 'Feedback received. Thank you.',
            ])
            ->assertJsonStructure(['id']);

        $this->assertDatabaseHas('application_feedback', [
            'message' => 'Great API! Suggestion: add more filters for search.',
            'category' => 'suggestion',
            'status' => 'pending',
        ]);
    }

    public function test_public_can_submit_feedback_without_category(): void
    {
        $res = $this->postJson('/api/v1/feedback', [
            'message' => 'Just a short note without category.',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('application_feedback', [
            'message' => 'Just a short note without category.',
            'category' => null,
        ]);
    }

    public function test_feedback_validation_requires_message(): void
    {
        $res = $this->postJson('/api/v1/feedback', []);

        $res->assertStatus(422)->assertJsonValidationErrors(['message']);
    }

    public function test_feedback_validation_message_min_length(): void
    {
        $res = $this->postJson('/api/v1/feedback', [
            'message' => 'short',
            'category' => 'other',
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['message']);
    }

    public function test_feedback_validation_category_enum(): void
    {
        $res = $this->postJson('/api/v1/feedback', [
            'message' => 'A valid long enough message here.',
            'category' => 'invalid_category',
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['category']);
    }
}
