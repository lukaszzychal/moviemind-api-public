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

    private const DEFAULT_API_URL = 'https://api.openai.com/v1/chat/completions';

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
     *
     * @param  array{title: string, release_date: string, overview: string, id: int, director?: string}|null  $tmdbData  Optional TMDb data to provide context to AI
     */
    public function generateMovie(string $slug, ?array $tmdbData = null): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResponse('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        // Build prompt with TMDb context if available
        if ($tmdbData !== null) {
            $tmdbContext = $this->formatTmdbContext($tmdbData);
            $systemPrompt = 'You are a movie database assistant. Generate a unique, original description for the movie based on the provided TMDb data. Do NOT copy the overview from TMDb. Create your own original description. Return JSON with: title, release_year, director, description (your original movie plot description), genres (array).';
            $userPrompt = "Movie data from TMDb:\n{$tmdbContext}\n\nGenerate a unique, original description for this movie. Do NOT copy the overview. Create your own original description. Return JSON with: title, release_year, director, description (your original movie plot), genres (array).";
        } else {
            $systemPrompt = 'You are a movie database assistant. IMPORTANT: First verify if the movie exists. If the movie does not exist, return {"error": "Movie not found"}. Only if the movie exists, generate movie information from the slug. Return JSON with: title, release_year, director, description (movie plot), genres (array).';
            $userPrompt = "Generate movie information for slug: {$slug}. IMPORTANT: First verify if this movie exists. If it does not exist, return {\"error\": \"Movie not found\"}. Only if it exists, return JSON with: title, release_year, director, description (movie plot), genres (array).";
        }

        return $this->makeApiCall('movie', $slug, $systemPrompt, $userPrompt, function ($content) use ($tmdbData) {
            $result = [
                'success' => true,
                'title' => $content['title'] ?? null,
                'release_year' => isset($content['release_year']) ? (int) $content['release_year'] : null,
                'director' => $content['director'] ?? null,
                'description' => $content['description'] ?? null,
                'genres' => $content['genres'] ?? [],
                'model' => $this->model,
            ];

            // Use TMDb data as fallback if AI response is missing fields
            if ($tmdbData !== null) {
                if (empty($result['title']) && ! empty($tmdbData['title'])) {
                    $result['title'] = $tmdbData['title'];
                }
                if (empty($result['director']) && ! empty($tmdbData['director'])) {
                    $result['director'] = $tmdbData['director'];
                }
                if ($result['release_year'] === null && ! empty($tmdbData['release_date'])) {
                    $year = (int) substr($tmdbData['release_date'], 0, 4);
                    if ($year > 0) {
                        $result['release_year'] = $year;
                    }
                }
            }

            return $result;
        }, $this->movieResponseSchema());
    }

    /**
     * Format TMDb data as context string for AI prompt.
     *
     * @param  array{title: string, release_date: string, overview: string, id: int, director?: string}  $tmdbData
     */
    private function formatTmdbContext(array $tmdbData): string
    {
        $lines = [
            "Title: {$tmdbData['title']}",
        ];

        if (! empty($tmdbData['release_date'])) {
            $lines[] = "Release Date: {$tmdbData['release_date']}";
        }

        if (! empty($tmdbData['director'])) {
            $lines[] = "Director: {$tmdbData['director']}";
        }

        if (! empty($tmdbData['overview'])) {
            $lines[] = "TMDb Overview: {$tmdbData['overview']}";
        }

        if (! empty($tmdbData['id'])) {
            $lines[] = "TMDb ID: {$tmdbData['id']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Generate person biography from a slug using AI.
     *
     * @param  array{name: string, birthday: string, place_of_birth: string, id: int, biography?: string}|null  $tmdbData  Optional TMDb data to provide context to AI
     */
    public function generatePerson(string $slug, ?array $tmdbData = null): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResponse('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        // Build prompt with TMDb context if available
        if ($tmdbData !== null) {
            $tmdbContext = $this->formatTmdbPersonContext($tmdbData);
            $systemPrompt = 'You are a biography assistant. Generate a unique, original biography for the person based on the provided TMDb data. Do NOT copy the biography from TMDb. Create your own original biography. Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (your original full text biography).';
            $userPrompt = "Person data from TMDb:\n{$tmdbContext}\n\nGenerate a unique, original biography for this person. Do NOT copy the biography. Create your own original biography. Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (your original full text biography).";
        } else {
            $systemPrompt = 'You are a biography assistant. IMPORTANT: First verify if the person exists. If the person does not exist, return {"error": "Person not found"}. Only if the person exists, generate biography from the slug. Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (full text).';
            $userPrompt = "Generate biography for person with slug: {$slug}. IMPORTANT: First verify if this person exists. If the person does not exist, return {\"error\": \"Person not found\"}. Only if the person exists, return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (full text).";
        }

        return $this->makeApiCall('person', $slug, $systemPrompt, $userPrompt, function ($content) use ($tmdbData) {
            $result = [
                'success' => true,
                'name' => $content['name'] ?? null,
                'birth_date' => $content['birth_date'] ?? null,
                'birthplace' => $content['birthplace'] ?? null,
                'biography' => $content['biography'] ?? null,
                'model' => $this->model,
            ];

            // Use TMDb data as fallback if AI response is missing fields
            if ($tmdbData !== null) {
                if (empty($result['name']) && ! empty($tmdbData['name'])) {
                    $result['name'] = $tmdbData['name'];
                }
                if (empty($result['birthplace']) && ! empty($tmdbData['place_of_birth'])) {
                    $result['birthplace'] = $tmdbData['place_of_birth'];
                }
                if (empty($result['birth_date']) && ! empty($tmdbData['birthday'])) {
                    $result['birth_date'] = $tmdbData['birthday'];
                }
            }

            return $result;
        }, $this->personResponseSchema());
    }

    /**
     * Format TMDb person data as context string for AI prompt.
     *
     * @param  array{name: string, birthday: string, place_of_birth: string, id: int, biography?: string}  $tmdbData
     */
    private function formatTmdbPersonContext(array $tmdbData): string
    {
        $lines = [
            "Name: {$tmdbData['name']}",
        ];

        if (! empty($tmdbData['birthday'])) {
            $lines[] = "Birthday: {$tmdbData['birthday']}";
        }

        if (! empty($tmdbData['place_of_birth'])) {
            $lines[] = "Place of Birth: {$tmdbData['place_of_birth']}";
        }

        if (! empty($tmdbData['biography'])) {
            $lines[] = "TMDb Biography: {$tmdbData['biography']}";
        }

        if (! empty($tmdbData['id'])) {
            $lines[] = "TMDb ID: {$tmdbData['id']}";
        }

        return implode("\n", $lines);
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

            // Check for error response from AI (e.g., "Movie not found", "Person not found")
            if (isset($content['error'])) {
                $errorMessage = $content['error'];
                Log::info("AI returned error response for {$entityType}", [
                    'slug' => $slug,
                    'error' => $errorMessage,
                ]);

                return $this->errorResponse($errorMessage);
            }

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
            // Responses API: Currently doesn't support json_schema format properly
            // Using simple text format without schema validation
            // TODO: Revisit when Responses API adds proper json_schema support
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
                                'text' => $userPrompt.' Return valid JSON only.',
                            ],
                        ],
                    ],
                ],
                'temperature' => self::DEFAULT_TEMPERATURE,
            ];
        } else {
            // Chat Completions API: Supports json_schema properly
            $payload = [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => $jsonSchema,
                ],
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
                'properties' => [
                    'error' => [
                        'type' => 'string',
                        'description' => 'Error message when movie does not exist (e.g., "Movie not found")',
                    ],
                    'title' => [
                        'type' => 'string',
                        'description' => 'Movie title',
                    ],
                    'release_year' => [
                        'type' => 'integer',
                        'description' => 'Year the movie was released',
                    ],
                    'director' => [
                        'type' => 'string',
                        'description' => 'Director name',
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Movie plot description',
                    ],
                    'genres' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                        'description' => 'Array of genre names',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    private function personResponseSchema(): array
    {
        return [
            'name' => 'person_generation_response',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'error' => [
                        'type' => 'string',
                        'description' => 'Error message when person does not exist (e.g., "Person not found")',
                    ],
                    'name' => [
                        'type' => 'string',
                        'description' => 'Person full name',
                    ],
                    'birth_date' => [
                        'type' => 'string',
                        'description' => 'Birth date in YYYY-MM-DD format',
                    ],
                    'birthplace' => [
                        'type' => 'string',
                        'description' => 'Place of birth',
                    ],
                    'biography' => [
                        'type' => 'string',
                        'description' => 'Full biography text',
                    ],
                ],
                'required' => [],
            ],
        ];
    }
}
