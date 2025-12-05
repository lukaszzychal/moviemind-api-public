<?php

namespace Tests\Unit\Services;

use App\Services\OpenAiClient;
use App\Services\PromptSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OpenAiClientTest extends TestCase
{
    use RefreshDatabase;

    private OpenAiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Set required config values for tests
        config([
            'services.openai.api_key' => 'test-api-key',
            'services.openai.model' => 'gpt-4o-mini',
            'services.openai.url' => 'https://api.openai.com/v1/chat/completions',
        ]);

        $this->client = new OpenAiClient(new PromptSanitizer);
    }

    public function test_generate_movie_returns_error_when_api_key_missing(): void
    {
        config(['services.openai.api_key' => '']);

        $client = new OpenAiClient(new PromptSanitizer);
        $result = $client->generateMovie('the-matrix');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('API key not configured', $result['error']);
    }

    public function test_generate_movie_returns_success_with_valid_response(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'title' => 'The Matrix',
                                'release_year' => 1999,
                                'director' => 'Lana Wachowski',
                                'description' => 'A computer hacker learns about the true nature of reality.',
                                'genres' => ['Action', 'Sci-Fi'],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->generateMovie('the-matrix');

        $this->assertTrue($result['success']);
        $this->assertEquals('The Matrix', $result['title']);
        $this->assertEquals(1999, $result['release_year']);
        $this->assertEquals('Lana Wachowski', $result['director']);
        $this->assertStringContainsString('computer hacker', $result['description']);
        $this->assertContains('Action', $result['genres']);
        $this->assertContains('Sci-Fi', $result['genres']);
        $this->assertArrayHasKey('model', $result);
    }

    public function test_generate_movie_handles_api_error(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'error' => 'Invalid API key',
            ], 401),
        ]);

        Log::shouldReceive('error')->once();

        $result = $this->client->generateMovie('the-matrix');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('API returned status 401', $result['error']);
    }

    public function test_generate_movie_handles_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('Network error');
        });

        Log::shouldReceive('error')->once();

        $result = $this->client->generateMovie('the-matrix');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Network error', $result['error']);
    }

    public function test_generate_person_returns_error_when_api_key_missing(): void
    {
        config(['services.openai.api_key' => '']);

        $client = new OpenAiClient(new PromptSanitizer);
        $result = $client->generatePerson('keanu-reeves');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('API key not configured', $result['error']);
    }

    public function test_generate_person_returns_success_with_valid_response(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'name' => 'Keanu Reeves',
                                'birth_date' => '1964-09-02',
                                'birthplace' => 'Beirut, Lebanon',
                                'biography' => 'Keanu Reeves is a Canadian actor known for his roles in action films.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->generatePerson('keanu-reeves');

        $this->assertTrue($result['success']);
        $this->assertEquals('Keanu Reeves', $result['name']);
        $this->assertEquals('1964-09-02', $result['birth_date']);
        $this->assertEquals('Beirut, Lebanon', $result['birthplace']);
        $this->assertStringContainsString('Canadian actor', $result['biography']);
        $this->assertArrayHasKey('model', $result);
    }

    public function test_generate_person_handles_api_error(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'error' => 'Rate limit exceeded',
            ], 429),
        ]);

        Log::shouldReceive('error')->once();

        $result = $this->client->generatePerson('keanu-reeves');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('API returned status 429', $result['error']);
    }

    public function test_generate_person_handles_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('Timeout error');
        });

        Log::shouldReceive('error')->once();

        $result = $this->client->generatePerson('keanu-reeves');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Timeout error', $result['error']);
    }

    public function test_generate_movie_handles_missing_fields(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'title' => 'The Matrix',
                                // Missing other fields
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->generateMovie('the-matrix');

        $this->assertTrue($result['success']);
        $this->assertEquals('The Matrix', $result['title']);
        // Missing fields should not be present or be null
        if (array_key_exists('release_year', $result)) {
            $this->assertNull($result['release_year']);
        }
        if (array_key_exists('director', $result)) {
            $this->assertNull($result['director']);
        }
        if (array_key_exists('description', $result)) {
            $this->assertNull($result['description']);
        }
        $this->assertEquals([], $result['genres'] ?? []);
    }

    public function test_generate_movie_sends_expected_payload_for_responses_api(): void
    {
        // Test Responses API format (when explicitly configured)
        config(['services.openai.url' => 'https://api.openai.com/v1/responses']);
        $client = new OpenAiClient(new PromptSanitizer);

        Http::fake([
            'api.openai.com/v1/responses' => function ($request) {
                $payload = $request->data();

                $this->assertSame('gpt-4o-mini', $payload['model']);
                $this->assertCount(2, $payload['input']);
                $this->assertSame('system', $payload['input'][0]['role']);
                $this->assertSame('user', $payload['input'][1]['role']);
                $this->assertSame('input_text', $payload['input'][0]['content'][0]['type']);
                $this->assertSame('input_text', $payload['input'][1]['content'][0]['type']);
                // Responses API doesn't support format parameter in input_text
                // So we send without format and rely on prompt instructions

                return Http::response([
                    'output' => [
                        [
                            'content' => [
                                [
                                    'type' => 'output_text',
                                    'text' => json_encode([
                                        'title' => 'Payload Movie',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ], 200);
            },
        ]);

        $result = $client->generateMovie('payload-movie');

        $this->assertTrue($result['success']);
        $this->assertSame('Payload Movie', $result['title']);
    }

    public function test_generate_movie_supports_legacy_chat_completions_payload(): void
    {
        // Chat Completions API is now default, supports json_schema properly
        $client = new OpenAiClient(new PromptSanitizer);

        Http::fake([
            'api.openai.com/v1/chat/completions' => function ($request) {
                $payload = $request->data();

                $this->assertArrayHasKey('messages', $payload);
                $this->assertEquals('json_schema', $payload['response_format']['type']);
                $this->assertEquals('movie_generation_response', $payload['response_format']['json_schema']['name']);

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'title' => 'Legacy Movie',
                                ]),
                            ],
                        ],
                    ],
                ], 200);
            },
        ]);

        $result = $client->generateMovie('legacy-movie');

        $this->assertTrue($result['success']);
        $this->assertSame('Legacy Movie', $result['title']);
    }

    public function test_generate_movie_handles_not_found_error_from_ai(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'error' => 'Movie not found',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->generateMovie('non-existent-movie-9999');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Movie not found', $result['error']);
    }

    public function test_generate_person_handles_not_found_error_from_ai(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'error' => 'Person not found',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->generatePerson('non-existent-person-xyz');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Person not found', $result['error']);
    }

    public function test_generate_movie_prompt_includes_verification_instruction(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => function ($request) {
                $payload = $request->data();
                $systemMessage = $payload['messages'][0]['content'];
                $userMessage = $payload['messages'][1]['content'];

                // Check that prompts include verification instruction
                $this->assertStringContainsString('verify if the movie exists', $systemMessage);
                $this->assertStringContainsString('verify if this movie exists', $userMessage);
                $this->assertStringContainsString('Movie not found', $systemMessage);

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'title' => 'Test Movie',
                                    'release_year' => 2020,
                                ]),
                            ],
                        ],
                    ],
                ], 200);
            },
        ]);

        $result = $this->client->generateMovie('test-movie');
        $this->assertTrue($result['success']);
    }

    public function test_generate_person_prompt_includes_verification_instruction(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => function ($request) {
                $payload = $request->data();
                $systemMessage = $payload['messages'][0]['content'];
                $userMessage = $payload['messages'][1]['content'];

                // Check that prompts include verification instruction
                $this->assertStringContainsString('verify if the person exists', $systemMessage);
                $this->assertStringContainsString('verify if this person exists', $userMessage);
                $this->assertStringContainsString('Person not found', $systemMessage);

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'name' => 'Test Person',
                                    'birth_date' => '1990-01-01',
                                ]),
                            ],
                        ],
                    ],
                ], 200);
            },
        ]);

        $result = $this->client->generatePerson('test-person');
        $this->assertTrue($result['success']);
    }
}
