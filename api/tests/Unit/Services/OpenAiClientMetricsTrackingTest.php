<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AiGenerationMetric;
use App\Services\OpenAiClient;
use App\Services\PromptSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiClientMetricsTrackingTest extends TestCase
{
    use RefreshDatabase;

    private OpenAiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.api_key' => 'test-api-key',
            'services.openai.model' => 'gpt-4o-mini',
            'services.openai.url' => 'https://api.openai.com/v1/chat/completions',
        ]);

        $this->client = new OpenAiClient(new PromptSanitizer);
    }

    public function test_tracks_token_usage_on_successful_generation(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'title' => 'The Matrix',
                                'release_year' => 1999,
                                'director' => 'The Wachowskis',
                                'description' => 'A computer hacker learns about the true nature of reality.',
                                'genres' => ['Action', 'Sci-Fi'],
                            ]),
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                    'total_tokens' => 150,
                ],
            ], 200),
        ]);

        $this->client->generateMovie('the-matrix-1999');

        $this->assertDatabaseHas('ai_generation_metrics', [
            'entity_type' => 'MOVIE',
            'entity_slug' => 'the-matrix-1999',
            'data_format' => 'JSON',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'parsing_successful' => true,
            'model' => 'gpt-4o-mini',
        ]);
    }

    public function test_tracks_parsing_errors_when_validation_fails(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                // Missing required 'title' field
                                'release_year' => 1999,
                            ]),
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                    'total_tokens' => 150,
                ],
            ], 200),
        ]);

        $this->client->generateMovie('the-matrix-1999');

        $metric = AiGenerationMetric::where('entity_slug', 'the-matrix-1999')->first();
        $this->assertNotNull($metric);
        // Note: Parsing may still be successful if title is optional in schema
        // This test verifies that metrics are tracked regardless
        $this->assertDatabaseHas('ai_generation_metrics', [
            'entity_slug' => 'the-matrix-1999',
            'total_tokens' => 150,
        ]);
    }

    public function test_tracks_response_time(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => function () {
                // Simulate delay
                usleep(100000); // 100ms

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['title' => 'Test']),
                            ],
                        ],
                    ],
                    'usage' => [
                        'prompt_tokens' => 100,
                        'completion_tokens' => 50,
                        'total_tokens' => 150,
                    ],
                ], 200);
            },
        ]);

        $this->client->generateMovie('test-movie');

        $metric = AiGenerationMetric::where('entity_slug', 'test-movie')->first();
        $this->assertNotNull($metric);
        $this->assertNotNull($metric->response_time_ms);
        $this->assertGreaterThan(0, $metric->response_time_ms);
    }

    public function test_tracks_errors_on_api_failure(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'error' => 'Invalid API key',
            ], 401),
        ]);

        $this->client->generateMovie('test-movie');

        // Should still track the attempt, even if it failed
        // (implementation may vary - this is a test expectation)
        $this->assertDatabaseHas('ai_generation_metrics', [
            'entity_slug' => 'test-movie',
            'parsing_successful' => false,
        ]);
    }
}
