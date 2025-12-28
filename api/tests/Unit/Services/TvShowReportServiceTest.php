<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\TvShow;
use App\Models\TvShowReport;
use App\Services\TvShowReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvShowReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private TvShowReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TvShowReportService;
    }

    public function test_calculate_priority_score_for_single_report(): void
    {
        // GIVEN: A TV show and a report
        $tvShow = TvShow::factory()->create();
        $report = TvShowReport::factory()->create([
            'tv_show_id' => $tvShow->id,
            'type' => ReportType::FACTUAL_ERROR,
        ]);

        // WHEN: Calculating priority score
        $score = $this->service->calculatePriorityScore($report);

        // THEN: Score should be FACTUAL_ERROR weight (3.0) * count (1) = 3.0
        $this->assertEquals(3.0, $score);
    }

    public function test_calculate_priority_score_multiplies_by_count(): void
    {
        // GIVEN: A TV show with 3 pending reports of same type
        $tvShow = TvShow::factory()->create();
        TvShowReport::factory()->count(3)->create([
            'tv_show_id' => $tvShow->id,
            'type' => ReportType::GRAMMAR_ERROR,
            'status' => ReportStatus::PENDING,
        ]);

        $report = TvShowReport::where('tv_show_id', $tvShow->id)->first();

        // WHEN: Calculating priority score
        $score = $this->service->calculatePriorityScore($report);

        // THEN: Score should be GRAMMAR_ERROR weight (1.5) * count (3) = 4.5
        $this->assertEquals(4.5, $score);
    }

    public function test_calculate_priority_score_only_counts_pending_reports(): void
    {
        // GIVEN: A TV show with mixed report statuses
        $tvShow = TvShow::factory()->create();
        TvShowReport::factory()->create([
            'tv_show_id' => $tvShow->id,
            'type' => ReportType::FACTUAL_ERROR,
            'status' => ReportStatus::PENDING,
        ]);
        TvShowReport::factory()->create([
            'tv_show_id' => $tvShow->id,
            'type' => ReportType::FACTUAL_ERROR,
            'status' => ReportStatus::RESOLVED,
        ]);
        TvShowReport::factory()->create([
            'tv_show_id' => $tvShow->id,
            'type' => ReportType::FACTUAL_ERROR,
            'status' => ReportStatus::VERIFIED,
        ]);

        $report = TvShowReport::where('tv_show_id', $tvShow->id)
            ->where('status', ReportStatus::PENDING)
            ->first();

        // WHEN: Calculating priority score
        $score = $this->service->calculatePriorityScore($report);

        // THEN: Score should only count pending reports (1 pending)
        $this->assertEquals(3.0, $score);
    }

    public function test_calculate_priority_score_uses_different_weights(): void
    {
        // GIVEN: A TV show with inappropriate content report
        $tvShow = TvShow::factory()->create();
        $report = TvShowReport::factory()->create([
            'tv_show_id' => $tvShow->id,
            'type' => ReportType::INAPPROPRIATE,
        ]);

        // WHEN: Calculating priority score
        $score = $this->service->calculatePriorityScore($report);

        // THEN: Score should use INAPPROPRIATE weight (2.5)
        $this->assertEquals(2.5, $score);
    }

    public function test_calculate_priority_score_handles_zero_reports(): void
    {
        // GIVEN: A TV show with a single report
        $tvShow = TvShow::factory()->create();
        $report = TvShowReport::factory()->create([
            'tv_show_id' => $tvShow->id,
            'type' => ReportType::OTHER,
        ]);

        // Delete all other reports for this TV show
        TvShowReport::where('tv_show_id', $tvShow->id)
            ->where('id', '!=', $report->id)
            ->delete();

        // WHEN: Calculating priority score
        $score = $this->service->calculatePriorityScore($report);

        // THEN: Score should be OTHER weight (0.5) * count (1) = 0.5
        $this->assertEquals(0.5, $score);
    }
}
