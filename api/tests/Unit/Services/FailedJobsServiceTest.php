<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\FailedJobsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FailedJobsServiceTest extends TestCase
{
    use RefreshDatabase;

    private FailedJobsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->service = app(FailedJobsService::class);
    }

    public function test_get_failed_jobs_returns_paginated_results(): void
    {
        // ARRANGE: Create failed jobs
        for ($i = 0; $i < 15; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test', 'id' => $i]),
                'exception' => "Exception message {$i}",
                'failed_at' => now()->subMinutes($i),
            ]);
        }

        // ACT: Get failed jobs
        $result = $this->service->getFailedJobs(10, 1);

        // ASSERT: Returns paginated results
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertCount(10, $result['data']);
        $this->assertEquals(15, $result['total']);
    }

    public function test_get_failed_jobs_by_queue_filters_by_queue(): void
    {
        // ARRANGE: Create failed jobs in different queues
        DB::table('failed_jobs')->insert([
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'exception' => 'Exception 1',
                'failed_at' => now(),
            ],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'high',
                'payload' => json_encode(['job' => 'test']),
                'exception' => 'Exception 2',
                'failed_at' => now(),
            ],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'exception' => 'Exception 3',
                'failed_at' => now(),
            ],
        ]);

        // ACT: Get failed jobs by queue
        $result = $this->service->getFailedJobsByQueue('default', 10, 1);

        // ASSERT: Returns only default queue jobs
        $this->assertIsArray($result);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(2, $result['total']);
        foreach ($result['data'] as $job) {
            $this->assertEquals('default', $job['queue']);
        }
    }

    public function test_get_failed_jobs_by_date_range_filters_by_dates(): void
    {
        // ARRANGE: Create failed jobs at different times
        DB::table('failed_jobs')->insert([
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'exception' => 'Exception 1',
                'failed_at' => now()->subDays(5),
            ],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'exception' => 'Exception 2',
                'failed_at' => now()->subDays(2),
            ],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'exception' => 'Exception 3',
                'failed_at' => now()->subDays(10),
            ],
        ]);

        // ACT: Get failed jobs in date range (last 3 days)
        $startDate = now()->subDays(3)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');
        $result = $this->service->getFailedJobsByDateRange($startDate, $endDate, 10, 1);

        // ASSERT: Returns only jobs in date range
        $this->assertIsArray($result);
        $this->assertCount(1, $result['data']);
        $this->assertEquals(1, $result['total']);
    }

    public function test_get_failure_statistics_returns_aggregated_stats(): void
    {
        // ARRANGE: Create failed jobs
        DB::table('failed_jobs')->insert([
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'exception' => 'Exception 1',
                'failed_at' => now()->subHours(1),
            ],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'high',
                'payload' => json_encode(['job' => 'test']),
                'exception' => 'Exception 2',
                'failed_at' => now()->subHours(2),
            ],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'exception' => 'Exception 3',
                'failed_at' => now()->subDays(1),
            ],
        ]);

        // ACT: Get failure statistics
        $result = $this->service->getFailureStatistics();

        // ASSERT: Returns aggregated statistics
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_failed', $result);
        $this->assertArrayHasKey('by_queue', $result);
        $this->assertArrayHasKey('by_hour', $result);
        $this->assertEquals(3, $result['total_failed']);
        $this->assertArrayHasKey('default', $result['by_queue']);
        $this->assertArrayHasKey('high', $result['by_queue']);
        $this->assertEquals(2, $result['by_queue']['default']);
        $this->assertEquals(1, $result['by_queue']['high']);
    }

    public function test_get_failure_rate_calculates_rate_correctly(): void
    {
        // ARRANGE: Create failed and successful jobs (tracked via ai_jobs)
        DB::table('failed_jobs')->insert([
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'exception' => 'Exception',
                'failed_at' => now(),
            ],
        ]);

        DB::table('ai_jobs')->insert([
            [
                'entity_type' => 'MOVIE',
                'entity_id' => 1,
                'status' => 'DONE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'entity_type' => 'MOVIE',
                'entity_id' => 2,
                'status' => 'DONE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'entity_type' => 'MOVIE',
                'entity_id' => 3,
                'status' => 'FAILED',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // ACT: Get failure rate
        $result = $this->service->getFailureRate();

        // ASSERT: Returns failure rate
        $this->assertIsArray($result);
        $this->assertArrayHasKey('failure_rate_percent', $result);
        $this->assertArrayHasKey('total_jobs', $result);
        $this->assertArrayHasKey('failed_jobs', $result);
        $this->assertIsFloat($result['failure_rate_percent']);
    }
}
