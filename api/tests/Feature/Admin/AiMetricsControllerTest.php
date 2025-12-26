<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AiGenerationMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiMetricsControllerTest extends TestCase
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
    }

    public function test_token_usage_endpoint_returns_statistics(): void
    {
        AiGenerationMetric::create([
            'entity_type' => 'MOVIE',
            'entity_slug' => 'test-movie',
            'data_format' => 'JSON',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'parsing_successful' => true,
            'model' => 'gpt-4o-mini',
        ]);

        $response = $this->getJson('/api/v1/admin/ai-metrics/token-usage');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['data_format', 'total_requests', 'avg_tokens', 'total_tokens'],
                ],
            ]);
    }

    public function test_parsing_accuracy_endpoint_returns_statistics(): void
    {
        AiGenerationMetric::create([
            'entity_type' => 'MOVIE',
            'entity_slug' => 'test-movie',
            'data_format' => 'JSON',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'parsing_successful' => true,
            'model' => 'gpt-4o-mini',
        ]);

        $response = $this->getJson('/api/v1/admin/ai-metrics/parsing-accuracy');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['data_format', 'total_requests', 'successful', 'failed', 'accuracy_percent'],
                ],
            ]);
    }

    public function test_error_statistics_endpoint_returns_failed_requests(): void
    {
        AiGenerationMetric::create([
            'entity_type' => 'MOVIE',
            'entity_slug' => 'test-movie',
            'data_format' => 'JSON',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'parsing_successful' => false,
            'parsing_errors' => 'Missing field: title',
            'model' => 'gpt-4o-mini',
        ]);

        $response = $this->getJson('/api/v1/admin/ai-metrics/errors');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['data_format', 'error_count'],
                ],
            ]);
    }

    public function test_format_comparison_endpoint_returns_toon_vs_json(): void
    {
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
            'token_savings_vs_json' => 20.0,
            'parsing_successful' => true,
            'model' => 'gpt-4o-mini',
        ]);

        $response = $this->getJson('/api/v1/admin/ai-metrics/comparison');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token_savings',
                    'accuracy',
                    'avg_tokens',
                ],
            ]);
    }
}
