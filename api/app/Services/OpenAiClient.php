<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI API Client for generating movie and person data.
 *
 * Handles all communication with OpenAI API.
 * Separates API communication logic from business logic.
 */
class OpenAiClient implements OpenAiClientInterface
{
    private const DEFAULT_TIMEOUT = 60;

    private const DEFAULT_TEMPERATURE = 0.7;

    private const DEFAULT_MODEL = 'gpt-4o-mini';

    private const DEFAULT_API_URL = 'https://api.openai.com/v1/responses';

    private string $apiKey;

    private string $model;

    private string $apiUrl;

    private string $healthUrl;

    public function __construct()
    {
        $this->apiKey = (string) (config('services.openai.api_key') ?? '');
        $this->model = (string) (config('services.openai.model') ?? self::DEFAULT_MODEL);
        $this->apiUrl = (string) (config('services.openai.url') ?? self::DEFAULT_API_URL);
        $this->healthUrl = (string) (config('services.openai.health_url') ?? 'https://api.openai.com/v1/models');
    }

    /**
     * Generate movie information from a slug using AI.
     */
    public function generateMovie(string $slug): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResponse('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        $systemPrompt = 'You are a movie database assistant. Generate movie information from a slug. Return JSON with: title, release_year, director, description (movie plot), genres (array).';
        $userPrompt = "Generate movie information for slug: {$slug}. Return JSON with: title, release_year, director, description (movie plot), genres (array).";

        return $this->makeApiCall('movie', $slug, $systemPrompt, $userPrompt, function ($content) {
            return [
                'success' => true,
                'title' => $content['title'] ?? null,
                'release_year' => isset($content['release_year']) ? (int) $content['release_year'] : null,
                'director' => $content['director'] ?? null,
                'description' => $content['description'] ?? null,
                'genres' => $content['genres'] ?? [],
                'model' => $this->model,
            ];
        }, $this->movieResponseSchema());
    }

    /**
     * Generate person biography from a slug using AI.
     */
    public function generatePerson(string $slug): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResponse('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        $systemPrompt = 'You are a biography assistant. Generate person biography from a slug. Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (full text).';
        $userPrompt = "Generate biography for person with slug: {$slug}. Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (full text).";

        return $this->makeApiCall('person', $slug, $systemPrompt, $userPrompt, function ($content) {
            return [
                'success' => true,
                'name' => $content['name'] ?? null,
                'birth_date' => $content['birth_date'] ?? null,
                'birthplace' => $content['birthplace'] ?? null,
                'biography' => $content['biography'] ?? null,
                'model' => $this->model,
            ];
        }, $this->personResponseSchema());
    }

    /**
     * Perform a lightweight health check request against OpenAI.
     */
    public function health(): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResponse('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->get($this->healthUrl.'?limit=1');

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'status' => $response->status(),
                    'error' => "API returned status {$response->status()}",
                ];
            }

            return [
                'success' => true,
                'message' => 'OpenAI API reachable',
                'status' => $response->status(),
                'model' => $this->model,
                'rate_limit' => $this->extractRateLimitHeaders($response),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Make an API call to OpenAI.
     *
     * @param  string  $entityType  Type of entity ('movie' or 'person')
     * @param  string  $slug  Entity slug
     * @param  string  $systemPrompt  System prompt for AI
     * @param  string  $userPrompt  User prompt for AI
     * @param  callable  $successMapper  Callback to map successful response to array
     */
    private function makeApiCall(
        string $entityType,
        string $slug,
        string $systemPrompt,
        string $userPrompt,
        callable $successMapper,
        array $jsonSchema
    ): array {
        try {
            $response = $this->sendRequest($systemPrompt, $userPrompt, $jsonSchema);

            if (! $response->successful()) {
                $this->logApiError($entityType, $slug, $response);

                return $this->errorResponse("API returned status {$response->status()}");
            }

            $content = $this->extractContent($response);

            return $successMapper($content);
        } catch (\Throwable $e) {
            $this->logException($entityType, $slug, $e);

            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Send HTTP request to OpenAI API.
     */
    private function sendRequest(string $systemPrompt, string $userPrompt, array $jsonSchema)
    {
        $request = Http::timeout(self::DEFAULT_TIMEOUT)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ]);

        if ($this->usesResponsesApi()) {
            $payload = [
                'model' => $this->model,
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $systemPrompt,
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $userPrompt,
                            ],
                        ],
                    ],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => $jsonSchema,
                ],
                'temperature' => self::DEFAULT_TEMPERATURE,
            ];
        } else {
            $payload = [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => self::DEFAULT_TEMPERATURE,
            ];
        }

        return $request->post($this->apiUrl, $payload);
    }

    /**
     * Extract and parse JSON content from API response.
     */
    private function extractContent($response): array
    {
        $responseData = $response->json();

        $content = $this->extractFromResponsesPayload($responseData);
        if ($content !== null) {
            return $content;
        }

        $rawContent = $responseData['choices'][0]['message']['content'] ?? '{}';

        return json_decode($rawContent, true) ?? [];
    }

    /**
     * Create an error response array.
     */
    private function errorResponse(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
        ];
    }

    /**
     * Log API error response.
     */
    private function logApiError(string $entityType, string $slug, $response): void
    {
        Log::error("OpenAI API call failed for {$entityType}", [
            'slug' => $slug,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }

    /**
     * Log exception during API call.
     */
    private function logException(string $entityType, string $slug, \Throwable $e): void
    {
        Log::error("OpenAI API exception for {$entityType}", [
            'slug' => $slug,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * Extract selected rate limit headers.
     */
    private function extractRateLimitHeaders($response): array
    {
        $headers = [
            'requests_remaining' => $response->header('x-ratelimit-remaining-requests') ?? $response->header('x-ratelimit-remaining-requests-1m'),
            'tokens_remaining' => $response->header('x-ratelimit-remaining-tokens') ?? $response->header('x-ratelimit-remaining-tokens-1m'),
            'reset_at' => $response->header('x-ratelimit-reset-requests') ?? $response->header('x-ratelimit-reset-tokens'),
        ];

        return array_filter($headers, static fn ($value) => $value !== null && $value !== '');
    }

    private function usesResponsesApi(): bool
    {
        return str_contains($this->apiUrl, '/responses');
    }

    private function extractFromResponsesPayload(array $responseData): ?array
    {
        $outputBlocks = $responseData['output'] ?? [];

        foreach ($outputBlocks as $block) {
            $contents = $block['content'] ?? [];
            foreach ($contents as $content) {
                $type = $content['type'] ?? null;

                if ($type === 'json_schema') {
                    $json = $content['json'] ?? null;

                    if (is_array($json)) {
                        return $json;
                    }

                    if (is_string($json)) {
                        $decoded = json_decode($json, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            return $decoded;
                        }
                    }
                }

                if (isset($content['text']) && in_array($type, ['output_text', 'text', 'tool_result'], true)) {
                    $decoded = json_decode((string) $content['text'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return $decoded;
                    }
                }
            }
        }

        return null;
    }

    private function movieResponseSchema(): array
    {
        return [
            'name' => 'movie_generation_response',
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'title' => ['type' => ['string', 'null']],
                    'release_year' => ['type' => ['integer', 'null']],
                    'director' => ['type' => ['string', 'null']],
                    'description' => ['type' => ['string', 'null']],
                    'genres' => [
                        'type' => ['array', 'null'],
                        'items' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }

    private function personResponseSchema(): array
    {
        return [
            'name' => 'person_generation_response',
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'name' => ['type' => ['string', 'null']],
                    'birth_date' => ['type' => ['string', 'null']],
                    'birthplace' => ['type' => ['string', 'null']],
                    'biography' => ['type' => ['string', 'null']],
                ],
            ],
        ];
    }
}
