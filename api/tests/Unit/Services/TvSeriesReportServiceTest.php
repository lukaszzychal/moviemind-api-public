<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\TvSeries;
use App\Models\TvSeriesReport;
use App\Services\TvSeriesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvSeriesReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private TvSeriesReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TvSeriesReportService;
    }

    public function test_calculate_priority_score_for_single_report(): void
    {
        // GIVEN: A TV series and a report
        $tvSeries = TvSeries::factory()->create();
        $report = TvSeriesReport::factory()->create([
            'tv_series_id' => $tvSeries->id,
            'type' => ReportType::FACTUAL_ERROR,
        ]);

        // WHEN: Calculating priority score
        $score = $this->service->calculatePriorityScore($report);

        // THEN: Score should be FACTUAL_ERROR weight (3.0) * count (1) = 3.0
        $this->assertEquals(3.0, $score);
    }

    public function test_calculate_priority_score_multiplies_by_count(): void
    {
        // GIVEN: A TV series with 3 pending reports of same type
        $tvSeries = TvSeries::factory()->create();
        TvSeriesReport::factory()->count(3)->create([
            'tv_series_id' => $tvSeries->id,
            'type' => ReportType::GRAMMAR_ERROR,
            'status' => ReportStatus::PENDING,
        ]);

        $report = TvSeriesReport::where('tv_series_id', $tvSeries->id)->first();

        // WHEN: Calculating priority score
        $score = $this->service->calculatePriorityScore($report);

        // THEN: Score should be GRAMMAR_ERROR weight (1.5) * count (3) = 4.5
        $this->assertEquals(4.5, $score);
    }

    public function test_calculate_priority_score_only_counts_pending_reports(): void
    {
        // GIVEN: A TV series with mixed report statuses
        $tvSeries = TvSeries::factory()->create();
        TvSeriesReport::factory()->create([
            'tv_series_id' => $tvSeries->id,
            'type' => ReportType::FACTUAL_ERROR,
            'status' => ReportStatus::PENDING,
        ]);
        TvSeriesReport::factory()->create([
            'tv_series_id' => $tvSeries->id,
            'type' => ReportType::FACTUAL_ERROR,
            'status' => ReportStatus::RESOLVED,
        ]);
        TvSeriesReport::factory()->create([
            'tv_series_id' => $tvSeries->id,
            'type' => ReportType::FACTUAL_ERROR,
            'status' => ReportStatus::VERIFIED,
        ]);

        $report = TvSeriesReport::where('tv_series_id', $tvSeries->id)
            ->where('status', ReportStatus::PENDING)
            ->first();

        // WHEN: Calculating priority score
        $score = $this->service->calculatePriorityScore($report);

        // THEN: Score should only count pending reports (1 pending)
        $this->assertEquals(3.0, $score);
    }

    public function test_calculate_priority_score_uses_different_weights(): void
    {
        // GIVEN: A TV series with inappropriate content report
        $tvSeries = TvSeries::factory()->create();
        $report = TvSeriesReport::factory()->create([
            'tv_series_id' => $tvSeries->id,
            'type' => ReportType::INAPPROPRIATE,
        ]);

        // WHEN: Calculating priority score
        $score = $this->service->calculatePriorityScore($report);

        // THEN: Score should use INAPPROPRIATE weight (2.5)
        $this->assertEquals(2.5, $score);
    }

    public function test_calculate_priority_score_handles_zero_reports(): void
    {
        // GIVEN: A TV series with a single report
        $tvSeries = TvSeries::factory()->create();
        $report = TvSeriesReport::factory()->create([
            'tv_series_id' => $tvSeries->id,
            'type' => ReportType::OTHER,
        ]);

        // Delete all other reports for this TV series
        TvSeriesReport::where('tv_series_id', $tvSeries->id)
            ->where('id', '!=', $report->id)
            ->delete();

        // WHEN: Calculating priority score
        $score = $this->service->calculatePriorityScore($report);

        // THEN: Score should be OTHER weight (0.5) * count (1) = 0.5
        $this->assertEquals(0.5, $score);
    }
}
