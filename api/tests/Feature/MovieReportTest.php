<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Models\MovieReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_user_can_report_movie(): void
    {
        $movie = Movie::first();
        $this->assertNotNull($movie, 'Movie should exist from seed');

        $response = $this->postJson("/api/v1/movies/{$movie->slug}/report", [
            'type' => 'factual_error',
            'message' => 'The release year is incorrect.',
            'suggested_fix' => 'Should be 1999, not 2000.',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'movie_id',
                    'type',
                    'message',
                    'status',
                    'priority_score',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('movie_reports', [
            'movie_id' => $movie->id,
            'type' => ReportType::FACTUAL_ERROR->value,
            'message' => 'The release year is incorrect.',
            'status' => ReportStatus::PENDING->value,
        ]);
    }

    public function test_report_automatically_calculates_priority_score(): void
    {
        $movie = Movie::first();
        $this->assertNotNull($movie, 'Movie should exist from seed');

        // Create first report
        $response1 = $this->postJson("/api/v1/movies/{$movie->slug}/report", [
            'type' => 'factual_error',
            'message' => 'First report',
        ]);

        $response1->assertStatus(201);
        $firstReport = MovieReport::where('movie_id', $movie->id)->first();
        $this->assertEquals(3.0, $firstReport->priority_score); // FACTUAL_ERROR weight = 3.0

        // Create second report of same type
        $response2 = $this->postJson("/api/v1/movies/{$movie->slug}/report", [
            'type' => 'factual_error',
            'message' => 'Second report',
        ]);

        $response2->assertStatus(201);
        $firstReport->refresh();
        $secondReport = MovieReport::where('movie_id', $movie->id)
            ->where('id', '!=', $firstReport->id)
            ->first();

        // Both should have updated priority score: 2 reports * 3.0 weight = 6.0
        $this->assertEquals(6.0, $firstReport->priority_score);
        $this->assertEquals(6.0, $secondReport->priority_score);
    }

    public function test_report_validates_required_fields(): void
    {
        $movie = Movie::first();
        $this->assertNotNull($movie, 'Movie should exist from seed');

        $response = $this->postJson("/api/v1/movies/{$movie->slug}/report", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'message']);
    }

    public function test_report_validates_type_enum(): void
    {
        $movie = Movie::first();
        $this->assertNotNull($movie, 'Movie should exist from seed');

        $response = $this->postJson("/api/v1/movies/{$movie->slug}/report", [
            'type' => 'invalid_type',
            'message' => 'Test message',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_report_can_include_description_id(): void
    {
        $movie = Movie::first();
        $description = MovieDescription::where('movie_id', $movie->id)->first();

        if ($description === null) {
            $description = MovieDescription::create([
                'movie_id' => $movie->id,
                'locale' => 'en-US',
                'text' => 'Test description',
                'context_tag' => 'DEFAULT',
                'origin' => 'GENERATED',
            ]);
        }

        $response = $this->postJson("/api/v1/movies/{$movie->slug}/report", [
            'type' => 'grammar_error',
            'message' => 'Grammar issue in description',
            'description_id' => $description->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('movie_reports', [
            'movie_id' => $movie->id,
            'description_id' => $description->id,
        ]);
    }

    public function test_report_returns_404_for_nonexistent_movie(): void
    {
        $response = $this->postJson('/api/v1/movies/nonexistent-movie/report', [
            'type' => 'factual_error',
            'message' => 'Test message',
        ]);

        $response->assertStatus(404);
    }
}
