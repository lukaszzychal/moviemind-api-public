<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateAiMetricsReportJob;
use App\Models\AiGenerationMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateAiMetricsReportJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_generates_report_with_metrics_data(): void
    {
        // Create test data
        AiGenerationMetric::create([
            'entity_type' => 'MOVIE',
            'entity_slug' => 'movie-1',
            'data_format' => 'JSON',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'parsing_successful' => true,
            'model' => 'gpt-4o-mini',
            'created_at' => now()->subDay(),
        ]);

        AiGenerationMetric::create([
            'entity_type' => 'MOVIE',
            'entity_slug' => 'movie-2',
            'data_format' => 'TOON',
            'prompt_tokens' => 80,
            'completion_tokens' => 40,
            'total_tokens' => 120,
            'token_savings_vs_json' => 20.0,
            'parsing_successful' => true,
            'model' => 'gpt-4o-mini',
            'created_at' => now()->subDay(),
        ]);

        $job = new GenerateAiMetricsReportJob('daily');
        $metricsService = app(\App\Services\AiMetricsService::class);
        $job->handle($metricsService);

        // Verify report was generated
        $files = Storage::files('reports/ai-metrics');
        $this->assertNotEmpty($files);

        // Verify report content
        $latestReport = collect($files)->sort()->last();
        $content = Storage::get($latestReport);
        $report = json_decode($content, true);

        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('token_usage', $report);
        $this->assertArrayHasKey('parsing_accuracy', $report);
        $this->assertArrayHasKey('comparison', $report);
    }

    public function test_handles_empty_data_gracefully(): void
    {
        $job = new GenerateAiMetricsReportJob('daily');
        $metricsService = app(\App\Services\AiMetricsService::class);
        $job->handle($metricsService);

        // Should not throw exception
        $this->assertTrue(true);
    }
}
