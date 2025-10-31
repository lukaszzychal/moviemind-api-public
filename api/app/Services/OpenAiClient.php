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

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY') ?? '';
        $this->model = config('services.openai.model') ?? env('OPENAI_MODEL', self::DEFAULT_MODEL);
        $this->apiUrl = config('services.openai.url') ?? env('OPENAI_URL', self::DEFAULT_API_URL);
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
        });
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
        });
    }

    /**
     * Make an API call to OpenAI.
     * 
     * @param  string  $entityType  Type of entity ('movie' or 'person')
     * @param  string  $slug  Entity slug
     * @param  string  $systemPrompt  System prompt for AI
     * @param  string  $userPrompt  User prompt for AI
     * @param  callable  $successMapper  Callback to map successful response to array
     * @return array
     */
    private function makeApiCall(string $entityType, string $slug, string $systemPrompt, string $userPrompt, callable $successMapper): array
    {
        try {
            $response = $this->sendRequest($systemPrompt, $userPrompt);

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
    private function sendRequest(string $systemPrompt, string $userPrompt)
    {
        return Http::timeout(self::DEFAULT_TIMEOUT)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => self::DEFAULT_TEMPERATURE,
            ]);
    }

    /**
     * Extract and parse JSON content from API response.
     */
    private function extractContent($response): array
    {
        $responseData = $response->json();
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
}
