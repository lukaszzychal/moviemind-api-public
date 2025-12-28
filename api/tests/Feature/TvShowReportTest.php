<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\TvShow;
use App\Models\TvShowReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvShowReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_user_can_report_tv_show(): void
    {
        $tvShow = TvShow::factory()->create([
            'title' => 'The Tonight Show',
            'slug' => 'the-tonight-show-1954',
        ]);

        $response = $this->postJson("/api/v1/tv-shows/{$tvShow->slug}/report", [
            'type' => 'factual_error',
            'message' => 'The first air date is incorrect.',
            'suggested_fix' => 'Should be 1954-09-27, not 1954-09-28.',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'tv_show_id',
                    'type',
                    'message',
                    'status',
                    'priority_score',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('tv_show_reports', [
            'tv_show_id' => $tvShow->id,
            'type' => ReportType::FACTUAL_ERROR->value,
            'status' => ReportStatus::PENDING->value,
        ]);
    }

    public function test_report_automatically_calculates_priority_score(): void
    {
        $tvShow = TvShow::factory()->create();

        $response1 = $this->postJson("/api/v1/tv-shows/{$tvShow->slug}/report", [
            'type' => 'factual_error',
            'message' => 'First report',
        ]);

        $response1->assertStatus(201);
        $firstReport = TvShowReport::where('tv_show_id', $tvShow->id)->first();
        $this->assertEquals(3.0, $firstReport->priority_score);

        $response2 = $this->postJson("/api/v1/tv-shows/{$tvShow->slug}/report", [
            'type' => 'factual_error',
            'message' => 'Second report',
        ]);

        $response2->assertStatus(201);
        $firstReport->refresh();
        $this->assertEquals(6.0, $firstReport->priority_score);
    }

    public function test_report_validates_required_fields(): void
    {
        $tvShow = TvShow::factory()->create();
        $response = $this->postJson("/api/v1/tv-shows/{$tvShow->slug}/report", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'message']);
    }

    public function test_report_returns_404_for_nonexistent_tv_show(): void
    {
        $response = $this->postJson('/api/v1/tv-shows/nonexistent-show/report', [
            'type' => 'factual_error',
            'message' => 'Test message',
        ]);

        $response->assertStatus(404);
    }
}
