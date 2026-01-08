<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\QueueJobsDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueJobsDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private QueueJobsDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->service = app(QueueJobsDashboardService::class);
    }

    public function test_get_overview_returns_queue_statistics(): void
    {
        // ARRANGE: Create some jobs in database
        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
            [
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'attempts' => 1,
                'reserved_at' => now()->timestamp,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
        ]);

        // ACT: Get overview
        $result = $this->service->getOverview();

        // ASSERT: Returns statistics
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_pending', $result);
        $this->assertArrayHasKey('total_processing', $result);
        $this->assertArrayHasKey('total_completed', $result);
        $this->assertArrayHasKey('total_failed', $result);
        $this->assertArrayHasKey('queues', $result);
    }

    public function test_get_by_queue_returns_statistics_per_queue(): void
    {
        // ARRANGE: Create jobs in different queues
        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
            [
                'queue' => 'high',
                'payload' => json_encode(['job' => 'test']),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
        ]);

        // ACT: Get by queue
        $result = $this->service->getByQueue();

        // ASSERT: Returns statistics per queue
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result));

        $defaultQueue = collect($result)->firstWhere('queue', 'default');
        $this->assertNotNull($defaultQueue);
        $this->assertArrayHasKey('queue', $defaultQueue);
        $this->assertArrayHasKey('pending', $defaultQueue);
        $this->assertArrayHasKey('processing', $defaultQueue);
    }

    public function test_get_recent_jobs_returns_paginated_results(): void
    {
        // ARRANGE: Create multiple jobs
        for ($i = 0; $i < 15; $i++) {
            DB::table('jobs')->insert([
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test', 'id' => $i]),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp - $i, // Different timestamps
            ]);
        }

        // ACT: Get recent jobs
        $result = $this->service->getRecentJobs(10, 1);

        // ASSERT: Returns paginated results
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertCount(10, $result['data']);
    }

    public function test_get_processing_times_returns_average_times(): void
    {
        // ARRANGE: This will need Horizon API or database tracking
        // For now, test structure

        // ACT: Get processing times
        $result = $this->service->getProcessingTimes();

        // ASSERT: Returns processing time statistics
        $this->assertIsArray($result);
        $this->assertArrayHasKey('by_queue', $result);
    }

    public function test_get_ai_jobs_statistics_aggregates_ai_jobs_data(): void
    {
        // ARRANGE: Create ai_jobs records
        DB::table('ai_jobs')->insert([
            [
                'entity_type' => 'MOVIE',
                'entity_id' => 1,
                'locale' => 'pl-PL',
                'status' => 'PENDING',
                'payload_json' => json_encode(['slug' => 'test-movie']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'entity_type' => 'MOVIE',
                'entity_id' => 2,
                'locale' => 'en-US',
                'status' => 'DONE',
                'payload_json' => json_encode(['slug' => 'test-movie-2']),
                'created_at' => now()->subHour(),
                'updated_at' => now(),
            ],
            [
                'entity_type' => 'PERSON',
                'entity_id' => 1,
                'locale' => 'pl-PL',
                'status' => 'FAILED',
                'payload_json' => json_encode(['slug' => 'test-person']),
                'created_at' => now()->subHours(2),
                'updated_at' => now(),
            ],
        ]);

        // ACT: Get AI jobs statistics
        $result = $this->service->getAiJobsStatistics();

        // ASSERT: Returns aggregated statistics
        $this->assertIsArray($result);
        $this->assertArrayHasKey('by_status', $result);
        $this->assertArrayHasKey('by_entity_type', $result);

        $byStatus = $result['by_status'];
        $this->assertArrayHasKey('PENDING', $byStatus);
        $this->assertArrayHasKey('DONE', $byStatus);
        $this->assertArrayHasKey('FAILED', $byStatus);
        $this->assertEquals(1, $byStatus['PENDING']);
        $this->assertEquals(1, $byStatus['DONE']);
        $this->assertEquals(1, $byStatus['FAILED']);
    }
}
