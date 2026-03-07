<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\TvSeriesGenerationRequested;
use App\Jobs\MockGenerateTvSeriesJob;
use App\Jobs\RealGenerateTvSeriesJob;
use App\Listeners\QueueTvSeriesGenerationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueTvSeriesGenerationJobTest extends TestCase
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

        $slug = 'breaking-bad-2008';
        $jobId = 'test-job-123';
        $event = new TvSeriesGenerationRequested($slug, $jobId);

        $listener = new QueueTvSeriesGenerationJob;
        $listener->handle($event);

        Queue::assertPushed(MockGenerateTvSeriesJob::class, function ($job) use ($slug, $jobId) {
            return $job->slug === $slug && $job->jobId === $jobId;
        });

        Queue::assertNotPushed(RealGenerateTvSeriesJob::class);
    }

    public function test_listener_dispatches_real_job_when_ai_service_is_real(): void
    {
        Config::set('services.ai.service', 'real');

        $slug = 'breaking-bad-2008';
        $jobId = 'test-job-123';
        $event = new TvSeriesGenerationRequested($slug, $jobId);

        $listener = new QueueTvSeriesGenerationJob;
        $listener->handle($event);

        Queue::assertPushed(RealGenerateTvSeriesJob::class, function ($job) use ($slug, $jobId) {
            return $job->slug === $slug && $job->jobId === $jobId;
        });

        Queue::assertNotPushed(MockGenerateTvSeriesJob::class);
    }

    public function test_listener_passes_locale_and_context(): void
    {
        Config::set('services.ai.service', 'mock');

        $event = new TvSeriesGenerationRequested('breaking-bad-2008', 'job-789', locale: 'pl-PL', contextTag: 'critical');

        $listener = new QueueTvSeriesGenerationJob;
        $listener->handle($event);

        Queue::assertPushed(MockGenerateTvSeriesJob::class, function ($job) {
            return $job->locale === 'pl-PL' && $job->contextTag === 'critical';
        });
    }
}
