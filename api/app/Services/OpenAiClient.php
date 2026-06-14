<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiGenerationMetric;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

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

    private OpenAiPromptBuilder $promptBuilder;

    public function __construct(OpenAiPromptBuilder $promptBuilder)
    {
        $this->apiKey = (string) (config('services.openai.api_key') ?? '');
        $this->model = (string) (config('services.openai.model') ?? self::DEFAULT_MODEL);
        $this->apiUrl = (string) (config('services.openai.url') ?? self::DEFAULT_API_URL);
        $this->healthUrl = (string) (config('services.openai.health_url') ?? 'https://api.openai.com/v1/models');
        $this->promptBuilder = $promptBuilder;
    }

    /**
     * Generate movie information from a slug using AI.
     *
     * @param  array{title: string, release_date: string, overview: string, id: int, director?: string}|null  $tmdbData  Optional TMDb data to provide context to AI
     * @param  string|null  $locale  Optional locale (pl-PL, en-US, etc.) – language for the description
     * @param  string|null  $contextTag  Optional context tag (modern, critical, humorous, default) – style of the description
     */
    public function generateMovie(string $slug, ?array $tmdbData = null, ?string $locale = null, ?string $contextTag = null): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResponse('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        try {
            $prompts = $this->promptBuilder->buildMoviePrompts($slug, $tmdbData, $locale, $contextTag);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage());
        }

        return $this->makeApiCall('movie', $slug, $prompts['system_prompt'], $prompts['user_prompt'], function ($content) use ($tmdbData) {
            $result = [
                'success' => true,
                'title' => $content['title'] ?? null,
                'release_year' => isset($content['release_year']) ? (int) $content['release_year'] : null,
                'director' => $content['director'] ?? null,
                'description' => $content['description'] ?? null,
                'genres' => $content['genres'] ?? [],
                'cast' => $content['cast'] ?? [],
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
        }, $prompts['schema'], 'auto', null);
    }

    /**
     * Generate movie description with specific context tag and locale.
     *
     * @param  string  $title  Movie title
     * @param  int  $releaseYear  Release year
     * @param  string  $director  Director name
     * @param  string  $contextTag  Context tag (modern, critical, humorous, default)
     * @param  string  $locale  Locale (pl-PL, en-US, etc.)
     * @param  array{title: string, release_date: string, overview: string, id: int, director?: string}|null  $tmdbData  Optional TMDb data to provide context to AI
     * @return array{success: bool, description?: string, model?: string, error?: string}
     */
    public function generateMovieDescription(
        string $title,
        int $releaseYear,
        string $director,
        string $contextTag,
        string $locale,
        ?array $tmdbData = null
    ): array {
        if (empty($this->apiKey)) {
            return $this->errorResponse('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        try {
            $prompts = $this->promptBuilder->buildMovieDescriptionPrompts($title, $releaseYear, $director, $contextTag, $locale, $tmdbData);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage());
        }

        return $this->makeApiCall('movie_description', "{$title}-{$releaseYear}", $prompts['system_prompt'], $prompts['user_prompt'], function ($content) {
            return [
                'success' => true,
                'description' => $content['description'] ?? null,
                'model' => $this->model,
            ];
        }, $prompts['schema'], 'auto', null);
    }

    /**
     * Generate person biography from a slug using AI.
     *
     * @param  array{name: string, birthday: string, place_of_birth: string, id: int, biography?: string}|null  $tmdbData  Optional TMDb data to provide context to AI
     * @param  string  $locale  Locale (pl-PL, en-US, etc.) – language for the biography
     * @param  string  $contextTag  Context tag (modern, critical, humorous, default) – style of the biography
     */
    public function generatePerson(string $slug, ?array $tmdbData = null, string $locale = 'en-US', string $contextTag = 'default'): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResponse('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        try {
            $prompts = $this->promptBuilder->buildPersonPrompts($slug, $tmdbData, $locale, $contextTag);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage());
        }

        return $this->makeApiCall('person', $slug, $prompts['system_prompt'], $prompts['user_prompt'], function ($content) use ($tmdbData) {
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
        }, $prompts['schema'], 'auto', null);
    }

    /**
     * Generate TV series information from a slug using AI.
     *
     * @param  array{name: string, first_air_date: string, overview: string, id: int}|null  $tmdbData  Optional TMDb data to provide context to AI
     * @param  string  $locale  Locale (pl-PL, en-US, etc.) – language for the description
     * @param  string  $contextTag  Context tag (modern, critical, humorous, default) – style of the description
     */
    public function generateTvSeries(string $slug, ?array $tmdbData = null, string $locale = 'en-US', string $contextTag = 'default'): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResponse('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        try {
            $prompts = $this->promptBuilder->buildTvSeriesPrompts($slug, $tmdbData, $locale, $contextTag);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage());
        }

        return $this->makeApiCall('tv_series', $slug, $prompts['system_prompt'], $prompts['user_prompt'], function ($content) use ($tmdbData) {
            $result = [
                'success' => true,
                'title' => $content['title'] ?? null,
                'first_air_year' => isset($content['first_air_year']) ? (int) $content['first_air_year'] : null,
                'description' => $content['description'] ?? null,
                'genres' => $content['genres'] ?? [],
                'model' => $this->model,
            ];

            // Use TMDb data as fallback if AI response is missing fields
            if ($tmdbData !== null) {
                if (empty($result['title']) && ! empty($tmdbData['name'])) {
                    $result['title'] = $tmdbData['name'];
                }
                if ($result['first_air_year'] === null && ! empty($tmdbData['first_air_date'])) {
                    $year = (int) substr($tmdbData['first_air_date'], 0, 4);
                    if ($year > 0) {
                        $result['first_air_year'] = $year;
                    }
                }
            }

            return $result;
        }, $prompts['schema'], 'auto', null);
    }

    /**
     * Generate TV show information from a slug using AI.
     *
     * @param  array{name: string, first_air_date: string, overview: string, id: int}|null  $tmdbData  Optional TMDb data to provide context to AI
     * @param  string  $locale  Locale (pl-PL, en-US, etc.) – language for the description
     * @param  string  $contextTag  Context tag (modern, critical, humorous, default) – style of the description
     */
    public function generateTvShow(string $slug, ?array $tmdbData = null, string $locale = 'en-US', string $contextTag = 'default'): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResponse('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        try {
            $prompts = $this->promptBuilder->buildTvShowPrompts($slug, $tmdbData, $locale, $contextTag);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage());
        }

        return $this->makeApiCall('tv_show', $slug, $prompts['system_prompt'], $prompts['user_prompt'], function ($content) use ($tmdbData) {
            $result = [
                'success' => true,
                'title' => $content['title'] ?? null,
                'first_air_year' => isset($content['first_air_year']) ? (int) $content['first_air_year'] : null,
                'description' => $content['description'] ?? null,
                'genres' => $content['genres'] ?? [],
                'show_type' => $content['show_type'] ?? null,
                'model' => $this->model,
            ];

            // Use TMDb data as fallback if AI response is missing fields
            if ($tmdbData !== null) {
                if (empty($result['title']) && ! empty($tmdbData['name'])) {
                    $result['title'] = $tmdbData['name'];
                }
                if ($result['first_air_year'] === null && ! empty($tmdbData['first_air_date'])) {
                    $year = (int) substr($tmdbData['first_air_date'], 0, 4);
                    if ($year > 0) {
                        $result['first_air_year'] = $year;
                    }
                }
            }

            return $result;
        }, $prompts['schema'], 'auto', null);
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
        array $jsonSchema,
        string $dataFormat = 'JSON',
        ?string $jobId = null
    ): array {
        // Determine actual format to use
        $actualFormat = $this->determineDataFormat($dataFormat);

        // Convert prompt data to TOON if needed
        if ($actualFormat === 'TOON') {
            [$systemPrompt, $userPrompt] = $this->convertPromptToToon($systemPrompt, $userPrompt);
        }

        $startTime = microtime(true);

        try {
            $response = $this->sendRequest($systemPrompt, $userPrompt, $jsonSchema);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if (! $response->successful()) {
                $this->logApiError($entityType, $slug, $response);
                $this->trackAiMetricsError($entityType, $slug, $actualFormat, new \Exception("API returned status {$response->status()}"), $jobId, $responseTime);

                return $this->errorResponse("API returned status {$response->status()}");
            }

            $content = $this->extractContent($response);
            $usage = $this->extractTokenUsage($response);
            $parsingResult = $this->validateParsing($content, $jsonSchema);

            // Track metrics
            $this->trackAiMetrics(
                entityType: $entityType,
                slug: $slug,
                dataFormat: $actualFormat,
                usage: $usage,
                parsingResult: $parsingResult,
                responseTime: $responseTime,
                jobId: $jobId
            );

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
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
            $this->logException($entityType, $slug, $e);
            $this->trackAiMetricsError($entityType, $slug, $actualFormat, $e, $jobId, $responseTime);

            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Determine which data format to use (JSON or TOON).
     *
     * @param  string  $requestedFormat  Requested format (JSON, TOON, or 'auto')
     * @return string Actual format to use ('JSON' or 'TOON')
     */
    private function determineDataFormat(string $requestedFormat): string
    {
        // If explicitly requested TOON, check feature flag
        if ($requestedFormat === 'TOON') {
            return Feature::active('ai_use_toon_format') ? 'TOON' : 'JSON';
        }

        // For 'auto', use TOON only if feature flag is enabled and data is tabular
        if ($requestedFormat === 'auto') {
            return Feature::active('ai_use_toon_format') ? 'TOON' : 'JSON';
        }

        // Default: always JSON for single objects
        return 'JSON';
    }

    /**
     * Convert prompt data to TOON format if needed.
     *
     * @return array{0: string, 1: string} [systemPrompt, userPrompt]
     */
    private function convertPromptToToon(string $systemPrompt, string $userPrompt): array
    {
        // For now, we just add TOON format instructions to prompts
        // Actual TOON conversion will be done when we have bulk operations
        $toonInstructions = "\n\nNOTE: Data may be provided in TOON format. TOON uses tabular arrays like [N]{keys}: followed by rows of comma-separated values. Parse TOON format correctly and return JSON response.";

        $systemPrompt .= $toonInstructions;

        return [$systemPrompt, $userPrompt];
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

    /**
     * Extract token usage from OpenAI API response.
     */
    private function extractTokenUsage($response): array
    {
        $responseData = $response->json();
        $usage = $responseData['usage'] ?? [];

        // Responses API uses input_tokens/output_tokens, Chat Completions uses prompt_tokens/completion_tokens
        $promptTokens = $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0;
        $totalTokens = $usage['total_tokens'] ?? 0;

        return [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
        ];
    }

    /**
     * Validate parsing accuracy by checking if response matches expected schema.
     */
    private function validateParsing(array $content, array $jsonSchema): array
    {
        $errors = [];
        $requiredFields = $jsonSchema['schema']['required'] ?? [];

        foreach ($requiredFields as $field) {
            if (! isset($content[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate types
        $properties = $jsonSchema['schema']['properties'] ?? [];
        foreach ($properties as $field => $schema) {
            if (isset($content[$field])) {
                $expectedType = $schema['type'] ?? null;
                $actualType = gettype($content[$field]);

                if ($expectedType === 'array' && ! is_array($content[$field])) {
                    $errors[] = "Field {$field} should be array, got {$actualType}";
                } elseif ($expectedType === 'string' && ! is_string($content[$field])) {
                    $errors[] = "Field {$field} should be string, got {$actualType}";
                }
            }
        }

        return [
            'successful' => empty($errors),
            'errors' => $errors,
            'error_count' => count($errors),
        ];
    }

    /**
     * Track AI generation metrics.
     */
    private function trackAiMetrics(
        string $entityType,
        string $slug,
        string $dataFormat,
        array $usage,
        array $parsingResult,
        ?int $responseTime,
        ?string $jobId = null
    ): void {
        try {
            AiGenerationMetric::create([
                'job_id' => $jobId,
                'entity_type' => strtoupper($entityType),
                'entity_slug' => $slug,
                'data_format' => $dataFormat,
                'prompt_tokens' => $usage['prompt_tokens'],
                'completion_tokens' => $usage['completion_tokens'],
                'total_tokens' => $usage['total_tokens'],
                'parsing_successful' => $parsingResult['successful'],
                'parsing_errors' => ! empty($parsingResult['errors'])
                    ? implode('; ', $parsingResult['errors'])
                    : null,
                'validation_errors' => $parsingResult['errors'],
                'response_time_ms' => $responseTime,
                'model' => $this->model,
            ]);

            Log::info('AI generation metrics tracked', [
                'entity_type' => $entityType,
                'slug' => $slug,
                'format' => $dataFormat,
                'tokens' => $usage['total_tokens'],
                'parsing_successful' => $parsingResult['successful'],
            ]);
        } catch (\Throwable $e) {
            // Don't fail the main operation if metrics tracking fails
            Log::warning('Failed to track AI metrics', [
                'entity_type' => $entityType,
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track AI metrics error.
     */
    private function trackAiMetricsError(
        string $entityType,
        string $slug,
        string $dataFormat,
        \Throwable $e,
        ?string $jobId = null,
        ?int $responseTime = null
    ): void {
        try {
            AiGenerationMetric::create([
                'job_id' => $jobId,
                'entity_type' => strtoupper($entityType),
                'entity_slug' => $slug,
                'data_format' => $dataFormat,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'parsing_successful' => false,
                'parsing_errors' => "Error: {$e->getMessage()}",
                'response_time_ms' => $responseTime,
                'model' => $this->model,
            ]);
        } catch (\Throwable $trackingError) {
            // Silently fail - don't break main flow
            Log::warning('Failed to track AI metrics error', [
                'error' => $trackingError->getMessage(),
            ]);
        }
    }
}
