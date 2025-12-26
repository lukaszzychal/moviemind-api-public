<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AiGenerationMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiGenerationMetricTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_ai_generation_metric(): void
    {
        $metric = AiGenerationMetric::create([
            'entity_type' => 'MOVIE',
            'entity_slug' => 'the-matrix-1999',
            'data_format' => 'JSON',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'parsing_successful' => true,
            'response_time_ms' => 2000,
            'model' => 'gpt-4o-mini',
        ]);

        $this->assertDatabaseHas('ai_generation_metrics', [
            'entity_type' => 'MOVIE',
            'entity_slug' => 'the-matrix-1999',
            'data_format' => 'JSON',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'parsing_successful' => true,
            'response_time_ms' => 2000,
            'model' => 'gpt-4o-mini',
        ]);

        $this->assertInstanceOf(AiGenerationMetric::class, $metric);
    }

    public function test_can_store_parsing_errors(): void
    {
        $metric = AiGenerationMetric::create([
            'entity_type' => 'MOVIE',
            'entity_slug' => 'test-movie',
            'data_format' => 'TOON',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'parsing_successful' => false,
            'parsing_errors' => 'Missing required field: title',
            'validation_errors' => ['Missing required field: title'],
            'model' => 'gpt-4o-mini',
        ]);

        $this->assertFalse($metric->parsing_successful);
        $this->assertEquals('Missing required field: title', $metric->parsing_errors);
        $this->assertIsArray($metric->validation_errors);
        $this->assertContains('Missing required field: title', $metric->validation_errors);
    }

    public function test_can_store_token_savings(): void
    {
        $metric = AiGenerationMetric::create([
            'entity_type' => 'MOVIE',
            'entity_slug' => 'test-movie',
            'data_format' => 'TOON',
            'prompt_tokens' => 80,
            'completion_tokens' => 40,
            'total_tokens' => 120,
            'token_savings_vs_json' => 20.5,
            'parsing_successful' => true,
            'model' => 'gpt-4o-mini',
        ]);

        $this->assertEquals(20.5, $metric->token_savings_vs_json);
    }

    public function test_can_link_to_job(): void
    {
        $jobId = '550e8400-e29b-41d4-a716-446655440000';

        $metric = AiGenerationMetric::create([
            'job_id' => $jobId,
            'entity_type' => 'MOVIE',
            'entity_slug' => 'test-movie',
            'data_format' => 'JSON',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'parsing_successful' => true,
            'model' => 'gpt-4o-mini',
        ]);

        $this->assertEquals($jobId, $metric->job_id);
    }
}
