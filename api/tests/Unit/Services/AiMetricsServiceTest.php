<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AiGenerationMetric;
use App\Services\AiMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiMetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiMetricsService;
    }

    public function test_get_token_usage_by_format_returns_statistics(): void
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
        ]);

        AiGenerationMetric::create([
            'entity_type' => 'MOVIE',
            'entity_slug' => 'movie-2',
            'data_format' => 'TOON',
            'prompt_tokens' => 80,
            'completion_tokens' => 40,
            'total_tokens' => 120,
            'parsing_successful' => true,
            'model' => 'gpt-4o-mini',
        ]);

        $result = $this->service->getTokenUsageByFormat();

        $this->assertCount(2, $result);
        $json = $result->firstWhere('data_format', 'JSON');
        $toon = $result->firstWhere('data_format', 'TOON');

        $this->assertNotNull($json);
        $this->assertEquals(150, $json->total_tokens);
        $this->assertNotNull($toon);
        $this->assertEquals(120, $toon->total_tokens);
    }

    public function test_get_parsing_accuracy_returns_statistics(): void
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
        ]);

        AiGenerationMetric::create([
            'entity_type' => 'MOVIE',
            'entity_slug' => 'movie-2',
            'data_format' => 'JSON',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'parsing_successful' => false,
            'model' => 'gpt-4o-mini',
        ]);

        $result = $this->service->getParsingAccuracy();

        $json = $result->firstWhere('data_format', 'JSON');
        $this->assertNotNull($json);
        $this->assertEquals(2, $json->total_requests);
        $this->assertEquals(1, $json->successful);
        $this->assertEquals(1, $json->failed);
        $this->assertEquals(50.0, $json->accuracy_percent);
    }

    public function test_get_error_statistics_returns_failed_requests(): void
    {
        AiGenerationMetric::create([
            'entity_type' => 'MOVIE',
            'entity_slug' => 'movie-1',
            'data_format' => 'JSON',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'parsing_successful' => false,
            'parsing_errors' => 'Missing field: title',
            'model' => 'gpt-4o-mini',
        ]);

        $result = $this->service->getErrorStatistics();

        $this->assertCount(1, $result);
        $json = $result->firstWhere('data_format', 'JSON');
        $this->assertNotNull($json);
        $this->assertEquals(1, $json->error_count);
    }

    public function test_get_format_comparison_returns_toon_vs_json(): void
    {
        // Create baseline JSON data
        AiGenerationMetric::create([
            'entity_type' => 'MOVIE',
            'entity_slug' => 'movie-1',
            'data_format' => 'JSON',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'parsing_successful' => true,
            'model' => 'gpt-4o-mini',
        ]);

        // Create TOON data with savings
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
        ]);

        $result = $this->service->getFormatComparison();

        $this->assertArrayHasKey('token_savings', $result);
        $this->assertArrayHasKey('avg_tokens', $result);
        $this->assertEquals(120, $result['avg_tokens']['toon']);
        $this->assertEquals(150, $result['avg_tokens']['json']);
    }
}
