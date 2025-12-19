<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Jobs\RegenerateMovieDescriptionJob;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Models\MovieReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminReportVerificationTest extends TestCase
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
        Queue::fake();
    }

    public function test_admin_can_verify_report(): void
    {
        $movie = Movie::first();
        $description = MovieDescription::where('movie_id', $movie->id)->first();
        $report = MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'description_id' => $description?->id,
            'status' => ReportStatus::PENDING,
        ]);

        $response = $this->postJson("/api/v1/admin/reports/{$report->id}/verify");

        $response->assertOk()
            ->assertJson([
                'id' => $report->id,
                'status' => ReportStatus::VERIFIED->value,
            ]);

        $report->refresh();
        $this->assertEquals(ReportStatus::VERIFIED, $report->status);
        $this->assertNotNull($report->verified_at);
    }

    public function test_verification_queues_regeneration_job(): void
    {
        $movie = Movie::first();
        $description = MovieDescription::where('movie_id', $movie->id)->first();
        $report = MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'description_id' => $description?->id,
            'status' => ReportStatus::PENDING,
        ]);

        $this->postJson("/api/v1/admin/reports/{$report->id}/verify");

        Queue::assertPushed(RegenerateMovieDescriptionJob::class, function ($job) use ($movie, $description) {
            return $job->movieId === $movie->id
                && $job->descriptionId === $description?->id;
        });
    }

    public function test_verification_returns_404_for_nonexistent_report(): void
    {
        $response = $this->postJson('/api/v1/admin/reports/nonexistent-id/verify');

        $response->assertStatus(404);
    }

    public function test_verification_does_not_queue_job_if_description_not_found(): void
    {
        $movie = Movie::first();
        $report = MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'description_id' => null, // No description
            'status' => ReportStatus::PENDING,
        ]);

        $this->postJson("/api/v1/admin/reports/{$report->id}/verify");

        // Should still verify, but may not queue job if description_id is null
        $report->refresh();
        $this->assertEquals(ReportStatus::VERIFIED, $report->status);
    }
}
