<?php

namespace Tests\Unit\Listeners;

use App\Events\PersonGenerationRequested;
use App\Jobs\MockGeneratePersonJob;
use App\Jobs\RealGeneratePersonJob;
use App\Listeners\QueuePersonGenerationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuePersonGenerationJobTest extends TestCase
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

        $slug = 'keanu-reeves';
        $jobId = 'test-job-123';
        $event = new PersonGenerationRequested($slug, $jobId);

        $listener = new QueuePersonGenerationJob;
        $listener->handle($event);

        Queue::assertPushed(MockGeneratePersonJob::class, function ($job) use ($slug, $jobId) {
            return $job->slug === $slug && $job->jobId === $jobId;
        });

        Queue::assertNotPushed(RealGeneratePersonJob::class);
    }

    public function test_listener_dispatches_real_job_when_ai_service_is_real(): void
    {
        Config::set('services.ai.service', 'real');

        $slug = 'keanu-reeves';
        $jobId = 'test-job-123';
        $event = new PersonGenerationRequested($slug, $jobId);

        $listener = new QueuePersonGenerationJob;
        $listener->handle($event);

        Queue::assertPushed(RealGeneratePersonJob::class, function ($job) use ($slug, $jobId) {
            return $job->slug === $slug && $job->jobId === $jobId;
        });

        Queue::assertNotPushed(MockGeneratePersonJob::class);
    }
}
