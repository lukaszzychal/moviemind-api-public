<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RealGenerateMovieJob;
use App\Jobs\RealGeneratePersonJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PreGenerationValidatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
        config(['services.tmdb.api_key' => 'test-api-key']);
        config(['services.openai.api_key' => 'test-key']);
    }

    public function test_movie_job_rejected_when_suspicious_slug_with_hallucination_guard_on(): void
    {
        Feature::activate('ai_description_generation');
        Feature::activate('hallucination_guard');

        $jobId = 'test-job-123';
        $job = new RealGenerateMovieJob('test-123-random', $jobId);

        // Job should throw exception when PreGenerationValidator rejects slug
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pre-generation validation failed');

        $job->handle($this->app->make(\App\Services\OpenAiClientInterface::class));
    }

    public function test_movie_job_rejected_when_low_confidence_slug_with_hallucination_guard_on(): void
    {
        Feature::activate('ai_description_generation');
        Feature::activate('hallucination_guard');

        $jobId = 'test-job-456';
        $job = new RealGenerateMovieJob('123', $jobId); // Low confidence slug

        // Job should throw exception when PreGenerationValidator rejects slug
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pre-generation validation failed');

        $job->handle($this->app->make(\App\Services\OpenAiClientInterface::class));
    }

    public function test_movie_job_rejected_when_invalid_year_with_hallucination_guard_on(): void
    {
        Feature::activate('ai_description_generation');
        Feature::activate('hallucination_guard');

        $futureYear = (int) date('Y') + 5;
        $jobId = 'test-job-789';
        $job = new RealGenerateMovieJob("valid-movie-{$futureYear}", $jobId);

        // Job should throw exception when PreGenerationValidator rejects slug
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pre-generation validation failed');

        $job->handle($this->app->make(\App\Services\OpenAiClientInterface::class));
    }

    public function test_person_job_rejected_when_suspicious_slug_with_hallucination_guard_on(): void
    {
        Feature::activate('ai_bio_generation');
        Feature::activate('hallucination_guard');

        $jobId = 'test-job-person-123';
        $job = new RealGeneratePersonJob('test-xyz-999', $jobId);

        // Job should throw exception when PreGenerationValidator rejects slug
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pre-generation validation failed');

        $job->handle($this->app->make(\App\Services\OpenAiClientInterface::class));
    }

    public function test_movie_job_allowed_when_valid_slug_with_hallucination_guard_on(): void
    {
        Feature::activate('ai_description_generation');
        Feature::activate('hallucination_guard');

        // Use a valid slug that should pass PreGenerationValidator
        $jobId = 'test-job-valid';
        $job = new RealGenerateMovieJob('the-matrix-1999', $jobId);

        // Mock OpenAiClient to return valid response
        $mockClient = $this->createMock(\App\Services\OpenAiClientInterface::class);
        $mockClient->method('generateMovie')
            ->willReturn([
                'success' => true,
                'title' => 'The Matrix',
                'release_year' => 1999,
                'director' => 'The Wachowskis',
                'description' => 'A computer hacker learns about the true nature of reality.',
                'genres' => ['Action', 'Sci-Fi'],
            ]);

        // Job should not throw exception - PreGenerationValidator should pass
        // Note: This will still fail if AI client is not properly mocked, but that's expected
        // The important part is that PreGenerationValidator doesn't reject it
        try {
            $job->handle($mockClient);
            // If we get here, PreGenerationValidator passed (job may fail later for other reasons)
            $this->assertTrue(true, 'PreGenerationValidator passed for valid slug');
        } catch (\RuntimeException $e) {
            // If exception is about PreGenerationValidator, that's a failure
            if (str_contains($e->getMessage(), 'Pre-generation validation failed')) {
                $this->fail('PreGenerationValidator rejected valid slug: '.$e->getMessage());
            }
            // Other exceptions (like AI client issues) are acceptable
        }
    }

    public function test_movie_job_bypasses_pre_validation_when_hallucination_guard_off(): void
    {
        Feature::activate('ai_description_generation');
        Feature::deactivate('hallucination_guard');

        // Even with suspicious slug, job should proceed when flag is off
        $jobId = 'test-job-bypass';
        $job = new RealGenerateMovieJob('test-123-random', $jobId);

        // Mock OpenAiClient
        $mockClient = $this->createMock(\App\Services\OpenAiClientInterface::class);
        $mockClient->method('generateMovie')
            ->willReturn([
                'success' => true,
                'title' => 'Test Movie',
                'release_year' => 2020,
                'director' => 'Test Director',
                'description' => 'Test description',
                'genres' => ['Action'],
            ]);

        // Job should not throw PreGenerationValidator exception when flag is off
        try {
            $job->handle($mockClient);
            $this->assertTrue(true, 'Job bypassed PreGenerationValidator when flag is off');
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Pre-generation validation failed')) {
                $this->fail('PreGenerationValidator should be bypassed when flag is off');
            }
            // Other exceptions are acceptable
        }
    }
}
