<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class JobsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_get_job_status_returns_pending(): void
    {
        $jobId = 'test-job-id-123';
        Cache::put("ai_job:{$jobId}", [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'entity' => 'MOVIE',
            'slug' => 'test-movie',
            'locale' => 'en-US',
        ], now()->addMinutes(15));

        $response = $this->getJson("/api/v1/jobs/{$jobId}");

        $response->assertStatus(200)
            ->assertJson([
                'job_id' => $jobId,
                'status' => 'PENDING',
                'entity' => 'MOVIE',
                'slug' => 'test-movie',
            ]);
    }

    public function test_get_job_status_returns_done(): void
    {
        $jobId = 'test-job-id-456';
        Cache::put("ai_job:{$jobId}", [
            'job_id' => $jobId,
            'status' => 'DONE',
            'entity' => 'MOVIE',
            'entity_id' => 1,
            'slug' => 'test-movie',
            'description_id' => 10,
            'locale' => 'en-US',
        ], now()->addMinutes(15));

        $response = $this->getJson("/api/v1/jobs/{$jobId}");

        $response->assertStatus(200)
            ->assertJson([
                'job_id' => $jobId,
                'status' => 'DONE',
                'entity' => 'MOVIE',
                'entity_id' => 1,
                'slug' => 'test-movie',
                'description_id' => 10,
            ]);
    }

    public function test_get_job_status_returns_failed_with_structured_error(): void
    {
        $jobId = 'test-job-id-789';
        Cache::put("ai_job:{$jobId}", [
            'job_id' => $jobId,
            'status' => 'FAILED',
            'entity' => 'MOVIE',
            'slug' => 'test-movie-123',
            'requested_slug' => 'test-movie-123',
            'locale' => 'en-US',
            'error' => [
                'type' => 'NOT_FOUND',
                'message' => 'The requested movie was not found',
                'technical_message' => 'Movie not found: test-movie-123',
                'user_message' => 'This movie does not exist in our database',
            ],
        ], now()->addMinutes(15));

        $response = $this->getJson("/api/v1/jobs/{$jobId}");

        $response->assertStatus(200)
            ->assertJson([
                'job_id' => $jobId,
                'status' => 'FAILED',
                'entity' => 'MOVIE',
                'slug' => 'test-movie-123',
            ])
            ->assertJsonStructure([
                'error' => [
                    'type',
                    'message',
                    'technical_message',
                    'user_message',
                ],
            ])
            ->assertJson([
                'error' => [
                    'type' => 'NOT_FOUND',
                    'message' => 'The requested movie was not found',
                    'technical_message' => 'Movie not found: test-movie-123',
                    'user_message' => 'This movie does not exist in our database',
                ],
            ]);
    }

    public function test_get_job_status_returns_failed_with_ai_api_error(): void
    {
        $jobId = 'test-job-id-ai-error';
        Cache::put("ai_job:{$jobId}", [
            'job_id' => $jobId,
            'status' => 'FAILED',
            'entity' => 'MOVIE',
            'slug' => 'test-movie',
            'locale' => 'en-US',
            'error' => [
                'type' => 'AI_API_ERROR',
                'message' => 'AI API returned an error',
                'technical_message' => 'AI API returned error: Rate limit exceeded',
                'user_message' => 'AI service is temporarily unavailable. Please try again later.',
            ],
        ], now()->addMinutes(15));

        $response = $this->getJson("/api/v1/jobs/{$jobId}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => [
                    'type' => 'AI_API_ERROR',
                    'message' => 'AI API returned an error',
                ],
            ]);
    }

    public function test_get_job_status_returns_failed_with_validation_error(): void
    {
        $jobId = 'test-job-id-validation-error';
        Cache::put("ai_job:{$jobId}", [
            'job_id' => $jobId,
            'status' => 'FAILED',
            'entity' => 'MOVIE',
            'slug' => 'test-movie',
            'locale' => 'en-US',
            'error' => [
                'type' => 'VALIDATION_ERROR',
                'message' => 'AI data validation failed',
                'technical_message' => 'AI data validation failed: Title does not match slug',
                'user_message' => 'Generated data failed validation checks. Please try again.',
            ],
        ], now()->addMinutes(15));

        $response = $this->getJson("/api/v1/jobs/{$jobId}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => [
                    'type' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_get_job_status_returns_unknown_when_not_found(): void
    {
        $jobId = 'non-existent-job-id';

        $response = $this->getJson("/api/v1/jobs/{$jobId}");

        $response->assertStatus(404)
            ->assertJson([
                'job_id' => $jobId,
                'status' => 'UNKNOWN',
            ]);
    }

    public function test_get_job_status_returns_failed_for_person(): void
    {
        $jobId = 'test-person-job-id';
        Cache::put("ai_job:{$jobId}", [
            'job_id' => $jobId,
            'status' => 'FAILED',
            'entity' => 'PERSON',
            'slug' => 'test-person-123',
            'locale' => 'en-US',
            'error' => [
                'type' => 'NOT_FOUND',
                'message' => 'The requested person was not found',
                'technical_message' => 'Person not found: test-person-123',
                'user_message' => 'This person does not exist in our database',
            ],
        ], now()->addMinutes(15));

        $response = $this->getJson("/api/v1/jobs/{$jobId}");

        $response->assertStatus(200)
            ->assertJson([
                'job_id' => $jobId,
                'status' => 'FAILED',
                'entity' => 'PERSON',
                'error' => [
                    'type' => 'NOT_FOUND',
                    'user_message' => 'This person does not exist in our database',
                ],
            ]);
    }
}
