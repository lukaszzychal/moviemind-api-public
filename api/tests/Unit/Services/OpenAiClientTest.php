<?php

namespace Tests\Unit\Services;

use App\Services\OpenAiClient;
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
            'services.openai.url' => 'https://api.openai.com/v1/responses',
        ]);

        $this->client = new OpenAiClient;
    }

    public function test_generate_movie_returns_error_when_api_key_missing(): void
    {
        config(['services.openai.api_key' => '']);

        $client = new OpenAiClient;
        $result = $client->generateMovie('the-matrix');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('API key not configured', $result['error']);
    }

    public function test_generate_movie_returns_success_with_valid_response(): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'json_schema',
                                'json' => [
                                    'title' => 'The Matrix',
                                    'release_year' => 1999,
                                    'director' => 'Lana Wachowski',
                                    'description' => 'A computer hacker learns about the true nature of reality.',
                                    'genres' => ['Action', 'Sci-Fi'],
                                ],
                            ],
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
            'api.openai.com/v1/responses' => Http::response([
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

        $client = new OpenAiClient;
        $result = $client->generatePerson('keanu-reeves');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('API key not configured', $result['error']);
    }

    public function test_generate_person_returns_success_with_valid_response(): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'json_schema',
                                'json' => [
                                    'name' => 'Keanu Reeves',
                                    'birth_date' => '1964-09-02',
                                    'birthplace' => 'Beirut, Lebanon',
                                    'biography' => 'Keanu Reeves is a Canadian actor known for his roles in action films.',
                                ],
                            ],
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
            'api.openai.com/v1/responses' => Http::response([
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
            'api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'json_schema',
                                'json' => [
                                    'title' => 'The Matrix',
                                    // Missing other fields
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->generateMovie('the-matrix');

        $this->assertTrue($result['success']);
        $this->assertEquals('The Matrix', $result['title']);
        $this->assertNull($result['release_year']);
        $this->assertNull($result['director']);
        $this->assertNull($result['description']);
        $this->assertEquals([], $result['genres']);
    }

    public function test_generate_movie_sends_expected_payload_for_responses_api(): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => function ($request) {
                $payload = $request->data();

                $this->assertSame('gpt-4o-mini', $payload['model']);
                $this->assertEquals('json_schema', $payload['response_format']['type']);
                $this->assertEquals('movie_generation_response', $payload['response_format']['json_schema']['name']);
                $this->assertCount(2, $payload['input']);
                $this->assertSame('system', $payload['input'][0]['role']);
                $this->assertSame('user', $payload['input'][1]['role']);
                $this->assertSame('input_text', $payload['input'][0]['content'][0]['type']);
                $this->assertSame('input_text', $payload['input'][1]['content'][0]['type']);

                return Http::response([
                    'output' => [
                        [
                            'content' => [
                                [
                                    'type' => 'json_schema',
                                    'json' => [
                                        'title' => 'Payload Movie',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            },
        ]);

        $result = $this->client->generateMovie('payload-movie');

        $this->assertTrue($result['success']);
        $this->assertSame('Payload Movie', $result['title']);
    }

    public function test_generate_movie_supports_legacy_chat_completions_payload(): void
    {
        config(['services.openai.url' => 'https://api.openai.com/v1/chat/completions']);

        $client = new OpenAiClient;

        Http::fake([
            'api.openai.com/v1/chat/completions' => function ($request) {
                $payload = $request->data();

                $this->assertArrayHasKey('messages', $payload);
                $this->assertEquals('json_object', $payload['response_format']['type']);

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
}
