<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        // Bypass Admin API auth for tests
        config(['app.env' => 'local']);
        putenv('ADMIN_AUTH_BYPASS_ENVS=local,staging');
    }

    public function test_overview_returns_queue_statistics(): void
    {
        // ARRANGE: Create some jobs
        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
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
        ]);

        // ACT: Get overview
        $response = $this->getJson('/api/v1/admin/jobs-dashboard/overview');

        // ASSERT: Returns statistics
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_pending',
            'total_processing',
            'total_completed',
            'total_failed',
            'queues',
            'ai_jobs',
        ]);
    }

    public function test_by_queue_returns_statistics_per_queue(): void
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
        $response = $this->getJson('/api/v1/admin/jobs-dashboard/by-queue');

        // ASSERT: Returns statistics per queue
        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'queue',
                'pending',
                'processing',
                'failed',
            ],
        ]);
    }

    public function test_recent_returns_paginated_jobs(): void
    {
        // ARRANGE: Create multiple jobs
        for ($i = 0; $i < 15; $i++) {
            DB::table('jobs')->insert([
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test', 'id' => $i]),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp - $i,
            ]);
        }

        // ACT: Get recent jobs
        $response = $this->getJson('/api/v1/admin/jobs-dashboard/recent?per_page=10&page=1');

        // ASSERT: Returns paginated results
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'total',
            'per_page',
            'current_page',
            'last_page',
        ]);
        $this->assertCount(10, $response->json('data'));
    }

    public function test_failed_returns_paginated_failed_jobs(): void
    {
        // ARRANGE: Create failed jobs
        for ($i = 0; $i < 5; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'exception' => "Exception {$i}",
                'failed_at' => now()->subMinutes($i),
            ]);
        }

        // ACT: Get failed jobs
        $response = $this->getJson('/api/v1/admin/jobs-dashboard/failed?per_page=10&page=1');

        // ASSERT: Returns paginated results
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'total',
            'per_page',
            'current_page',
            'last_page',
        ]);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_failed_stats_returns_failure_statistics(): void
    {
        // ARRANGE: Create failed jobs
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
        ]);

        // ACT: Get failed stats
        $response = $this->getJson('/api/v1/admin/jobs-dashboard/failed/stats');

        // ASSERT: Returns statistics
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_failed',
            'by_queue',
            'by_hour',
        ]);
    }

    public function test_processing_times_returns_processing_time_statistics(): void
    {
        // ACT: Get processing times
        $response = $this->getJson('/api/v1/admin/jobs-dashboard/processing-times');

        // ASSERT: Returns processing time statistics
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'by_queue',
            'overall_avg',
        ]);
    }
}
