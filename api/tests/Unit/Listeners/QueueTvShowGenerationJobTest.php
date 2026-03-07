<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\TvShowGenerationRequested;
use App\Jobs\MockGenerateTvShowJob;
use App\Jobs\RealGenerateTvShowJob;
use App\Listeners\QueueTvShowGenerationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueTvShowGenerationJobTest extends TestCase
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

        $slug = 'the-tonight-show-1954';
        $jobId = 'test-job-123';
        $event = new TvShowGenerationRequested($slug, $jobId);

        $listener = new QueueTvShowGenerationJob;
        $listener->handle($event);

        Queue::assertPushed(MockGenerateTvShowJob::class, function ($job) use ($slug, $jobId) {
            return $job->slug === $slug && $job->jobId === $jobId;
        });

        Queue::assertNotPushed(RealGenerateTvShowJob::class);
    }

    public function test_listener_dispatches_real_job_when_ai_service_is_real(): void
    {
        Config::set('services.ai.service', 'real');

        $slug = 'the-tonight-show-1954';
        $jobId = 'test-job-123';
        $event = new TvShowGenerationRequested($slug, $jobId);

        $listener = new QueueTvShowGenerationJob;
        $listener->handle($event);

        Queue::assertPushed(RealGenerateTvShowJob::class, function ($job) use ($slug, $jobId) {
            return $job->slug === $slug && $job->jobId === $jobId;
        });

        Queue::assertNotPushed(MockGenerateTvShowJob::class);
    }

    public function test_listener_passes_locale_and_context(): void
    {
        Config::set('services.ai.service', 'mock');

        $event = new TvShowGenerationRequested('the-tonight-show-1954', 'job-789', locale: 'pl-PL', contextTag: 'modern');

        $listener = new QueueTvShowGenerationJob;
        $listener->handle($event);

        Queue::assertPushed(MockGenerateTvShowJob::class, function ($job) {
            return $job->locale === 'pl-PL' && $job->contextTag === 'modern';
        });
    }
}
