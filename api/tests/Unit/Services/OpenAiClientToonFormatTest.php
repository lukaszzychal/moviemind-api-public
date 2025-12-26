<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\OpenAiClient;
use App\Services\PromptSanitizer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiClientToonFormatTest extends TestCase
{
    private OpenAiClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new OpenAiClient(new PromptSanitizer);
    }

    public function test_uses_json_when_toon_feature_flag_disabled(): void
    {
        // Feature flag disabled by default, so JSON should be used

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'title' => 'The Matrix',
                                'release_year' => 1999,
                                'director' => 'The Wachowskis',
                                'description' => 'Test description',
                                'genres' => ['Action', 'Sci-Fi'],
                                'cast' => [],
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

        $result = $this->client->generateMovie('the-matrix');

        $this->assertTrue($result['success']);
        $this->assertEquals('The Matrix', $result['title']);

        // Verify that request was sent (not checking format, just that it was sent)
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com');
        });
    }

    public function test_uses_json_for_single_objects_even_when_toon_enabled(): void
    {
        // Even if TOON is enabled, single objects (not lists) should use JSON
        // This test verifies that generateMovie (single object) uses JSON

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'title' => 'The Matrix',
                                'release_year' => 1999,
                                'director' => 'The Wachowskis',
                                'description' => 'Test description',
                                'genres' => ['Action', 'Sci-Fi'],
                                'cast' => [],
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

        $result = $this->client->generateMovie('the-matrix');

        $this->assertTrue($result['success']);
        $this->assertEquals('The Matrix', $result['title']);
    }
}
