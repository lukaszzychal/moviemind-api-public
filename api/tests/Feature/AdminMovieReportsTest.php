<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Movie;
use App\Models\MovieReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMovieReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');

        // Bypass Admin API auth for tests
        config(['app.env' => 'local']);
        putenv('ADMIN_AUTH_BYPASS_ENVS=local,staging');
    }

    public function test_admin_can_list_reports(): void
    {
        $movie = Movie::first();
        MovieReport::factory()->count(3)->create([
            'movie_id' => $movie->id,
            'status' => ReportStatus::PENDING,
        ]);

        $response = $this->getJson('/api/v1/admin/reports');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'movie_id',
                        'description_id',
                        'type',
                        'message',
                        'suggested_fix',
                        'status',
                        'priority_score',
                        'verified_by',
                        'verified_at',
                        'resolved_at',
                        'created_at',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_reports_sorted_by_priority_score_desc(): void
    {
        $movie = Movie::first();
        $report1 = MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'type' => ReportType::FACTUAL_ERROR,
            'priority_score' => 3.0,
            'status' => ReportStatus::PENDING,
        ]);
        $report2 = MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'type' => ReportType::GRAMMAR_ERROR,
            'priority_score' => 1.5,
            'status' => ReportStatus::PENDING,
        ]);
        $report3 = MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'type' => ReportType::OTHER,
            'priority_score' => 0.5,
            'status' => ReportStatus::PENDING,
        ]);

        $response = $this->getJson('/api/v1/admin/reports');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertEquals($report1->id, $data[0]['id']);
        $this->assertEquals($report2->id, $data[1]['id']);
        $this->assertEquals($report3->id, $data[2]['id']);
    }

    public function test_admin_can_filter_reports_by_status(): void
    {
        $movie = Movie::first();
        MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'status' => ReportStatus::PENDING,
        ]);
        MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'status' => ReportStatus::VERIFIED,
        ]);
        MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'status' => ReportStatus::RESOLVED,
        ]);

        $response = $this->getJson('/api/v1/admin/reports?status=pending');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(ReportStatus::PENDING->value, $data[0]['status']);
    }

    public function test_admin_can_filter_reports_by_priority(): void
    {
        $movie = Movie::first();
        MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'priority_score' => 5.0,
            'status' => ReportStatus::PENDING,
        ]);
        MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'priority_score' => 1.0,
            'status' => ReportStatus::PENDING,
        ]);

        $response = $this->getJson('/api/v1/admin/reports?priority=high');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
        // All returned reports should have priority_score >= threshold for "high"
        foreach ($data as $report) {
            $this->assertGreaterThanOrEqual(3.0, (float) $report['priority_score']);
        }
    }

    public function test_admin_reports_include_priority_score(): void
    {
        $movie = Movie::first();
        $report = MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'priority_score' => 4.5,
            'status' => ReportStatus::PENDING,
        ]);

        $response = $this->getJson('/api/v1/admin/reports');

        $response->assertOk();
        $data = $response->json('data');
        $foundReport = collect($data)->firstWhere('id', $report->id);
        $this->assertNotNull($foundReport);
        $this->assertEquals(4.5, (float) $foundReport['priority_score']);
    }
}
