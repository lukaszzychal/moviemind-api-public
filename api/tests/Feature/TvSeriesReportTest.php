<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use App\Models\TvSeriesReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvSeriesReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_user_can_report_tv_series(): void
    {
        $tvSeries = TvSeries::factory()->create([
            'title' => 'Breaking Bad',
            'slug' => 'breaking-bad-2008',
        ]);

        $response = $this->postJson("/api/v1/tv-series/{$tvSeries->slug}/report", [
            'type' => 'factual_error',
            'message' => 'The first air date is incorrect.',
            'suggested_fix' => 'Should be 2008-01-20, not 2008-01-21.',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'tv_series_id',
                    'type',
                    'message',
                    'status',
                    'priority_score',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('tv_series_reports', [
            'tv_series_id' => $tvSeries->id,
            'type' => ReportType::FACTUAL_ERROR->value,
            'message' => 'The first air date is incorrect.',
            'status' => ReportStatus::PENDING->value,
        ]);
    }

    public function test_report_automatically_calculates_priority_score(): void
    {
        $tvSeries = TvSeries::factory()->create();

        // Create first report
        $response1 = $this->postJson("/api/v1/tv-series/{$tvSeries->slug}/report", [
            'type' => 'factual_error',
            'message' => 'First report',
        ]);

        $response1->assertStatus(201);
        $firstReport = TvSeriesReport::where('tv_series_id', $tvSeries->id)->first();
        $this->assertEquals(3.0, $firstReport->priority_score);

        // Create second report of same type
        $response2 = $this->postJson("/api/v1/tv-series/{$tvSeries->slug}/report", [
            'type' => 'factual_error',
            'message' => 'Second report',
        ]);

        $response2->assertStatus(201);
        $firstReport->refresh();
        $secondReport = TvSeriesReport::where('tv_series_id', $tvSeries->id)
            ->where('id', '!=', $firstReport->id)
            ->first();

        // Both should have updated priority score: 2 reports * 3.0 weight = 6.0
        $this->assertEquals(6.0, $firstReport->priority_score);
        $this->assertEquals(6.0, $secondReport->priority_score);
    }

    public function test_report_validates_required_fields(): void
    {
        $tvSeries = TvSeries::factory()->create();

        $response = $this->postJson("/api/v1/tv-series/{$tvSeries->slug}/report", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'message']);
    }

    public function test_report_can_include_description_id(): void
    {
        $tvSeries = TvSeries::factory()->create();
        $description = TvSeriesDescription::factory()->create([
            'tv_series_id' => $tvSeries->id,
        ]);

        $response = $this->postJson("/api/v1/tv-series/{$tvSeries->slug}/report", [
            'type' => 'grammar_error',
            'message' => 'Grammar issue in description',
            'description_id' => $description->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tv_series_reports', [
            'tv_series_id' => $tvSeries->id,
            'description_id' => $description->id,
        ]);
    }

    public function test_report_returns_404_for_nonexistent_tv_series(): void
    {
        $response = $this->postJson('/api/v1/tv-series/nonexistent-series/report', [
            'type' => 'factual_error',
            'message' => 'Test message',
        ]);

        $response->assertStatus(404);
    }
}
