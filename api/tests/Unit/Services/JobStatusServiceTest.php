<?php

namespace Tests\Unit\Services;

use App\Services\JobStatusService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class JobStatusServiceTest extends TestCase
{
    private JobStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::clear();
        $this->service = app(JobStatusService::class);
    }

    public function test_acquire_generation_slot_blocks_second_request_until_release(): void
    {
        $slug = 'unit-test-slug';
        $jobA = 'job-A';
        $jobB = 'job-B';

        $first = $this->service->acquireGenerationSlot('MOVIE', $slug, $jobA, 'en-US', null);
        $second = $this->service->acquireGenerationSlot('MOVIE', $slug, $jobB, 'en-US', null);

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertSame($jobA, $this->service->currentGenerationSlotJobId('MOVIE', $slug, 'en-US', null));

        $this->service->releaseGenerationSlot('MOVIE', $slug, 'en-US', null);

        $third = $this->service->acquireGenerationSlot('MOVIE', $slug, $jobB, 'en-US', null);
        $this->assertTrue($third);
        $this->assertSame($jobB, $this->service->currentGenerationSlotJobId('MOVIE', $slug, 'en-US', null));
    }

    public function test_release_generation_slot_clears_slot_even_without_prior_acquire(): void
    {
        $slug = 'unit-test-slug-2';

        $this->service->releaseGenerationSlot('MOVIE', $slug, null, null);

        $acquired = $this->service->acquireGenerationSlot('MOVIE', $slug, 'job-C', null, null);
        $this->assertTrue($acquired);
        $this->assertSame('job-C', $this->service->currentGenerationSlotJobId('MOVIE', $slug, null, null));
    }
}
