<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Movie;
use App\Models\MovieReport;
use App\Services\MovieReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private MovieReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MovieReportService;
    }

    public function test_calculate_priority_score_for_single_report(): void
    {
        $movie = Movie::factory()->create();
        $report = MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'type' => ReportType::FACTUAL_ERROR,
        ]);

        $score = $this->service->calculatePriorityScore($report);

        // FACTUAL_ERROR weight = 3.0, count = 1
        $this->assertEquals(3.0, $score);
    }

    public function test_calculate_priority_score_multiplies_by_count(): void
    {
        $movie = Movie::factory()->create();
        MovieReport::factory()->count(3)->create([
            'movie_id' => $movie->id,
            'type' => ReportType::GRAMMAR_ERROR,
            'status' => ReportStatus::PENDING,
        ]);

        $report = MovieReport::where('movie_id', $movie->id)->first();

        $score = $this->service->calculatePriorityScore($report);

        // GRAMMAR_ERROR weight = 1.5, count = 3
        $this->assertEquals(4.5, $score);
    }

    public function test_calculate_priority_score_only_counts_pending_reports(): void
    {
        $movie = Movie::factory()->create();
        MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'type' => ReportType::FACTUAL_ERROR,
            'status' => ReportStatus::PENDING,
        ]);
        MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'type' => ReportType::FACTUAL_ERROR,
            'status' => ReportStatus::RESOLVED,
        ]);
        MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'type' => ReportType::FACTUAL_ERROR,
            'status' => ReportStatus::VERIFIED,
        ]);

        $report = MovieReport::where('movie_id', $movie->id)
            ->where('status', ReportStatus::PENDING)
            ->first();

        $score = $this->service->calculatePriorityScore($report);

        // FACTUAL_ERROR weight = 3.0, only 1 pending report
        $this->assertEquals(3.0, $score);
    }

    public function test_calculate_priority_score_uses_different_weights(): void
    {
        $movie = Movie::factory()->create();
        $report = MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'type' => ReportType::INAPPROPRIATE,
        ]);

        $score = $this->service->calculatePriorityScore($report);

        // INAPPROPRIATE weight = 2.5
        $this->assertEquals(2.5, $score);
    }

    public function test_calculate_priority_score_handles_zero_reports(): void
    {
        $movie = Movie::factory()->create();
        $report = MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'type' => ReportType::OTHER,
        ]);

        // Delete all other reports for this movie
        MovieReport::where('movie_id', $movie->id)
            ->where('id', '!=', $report->id)
            ->delete();

        $score = $this->service->calculatePriorityScore($report);

        // OTHER weight = 0.5, count = 1
        $this->assertEquals(0.5, $score);
    }
}
