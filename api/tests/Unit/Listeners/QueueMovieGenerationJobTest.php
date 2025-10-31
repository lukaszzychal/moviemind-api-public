<?php

namespace Tests\Unit\Listeners;

use App\Events\MovieGenerationRequested;
use App\Jobs\MockGenerateMovieJob;
use App\Jobs\RealGenerateMovieJob;
use App\Listeners\QueueMovieGenerationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueMovieGenerationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_listener_dispatches_mock_job_when_ai_service_is_mock(): void
    {
        Config::set('services.ai.service', 'mock');

        $slug = 'the-matrix';
        $jobId = 'test-job-123';
        $event = new MovieGenerationRequested($slug, $jobId);

        $listener = new QueueMovieGenerationJob;
        $listener->handle($event);

        Queue::assertPushed(MockGenerateMovieJob::class, function ($job) use ($slug, $jobId) {
            return $job->slug === $slug && $job->jobId === $jobId;
        });

        Queue::assertNotPushed(RealGenerateMovieJob::class);
    }

    public function test_listener_dispatches_real_job_when_ai_service_is_real(): void
    {
        Config::set('services.ai.service', 'real');

        $slug = 'the-matrix';
        $jobId = 'test-job-123';
        $event = new MovieGenerationRequested($slug, $jobId);

        $listener = new QueueMovieGenerationJob;
        $listener->handle($event);

        Queue::assertPushed(RealGenerateMovieJob::class, function ($job) use ($slug, $jobId) {
            return $job->slug === $slug && $job->jobId === $jobId;
        });

        Queue::assertNotPushed(MockGenerateMovieJob::class);
    }
}
