<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Example: Integration of TOON converter with OpenAiClient
 *
 * This is an example of how to integrate TOON format with OpenAiClient.
 * It shows how to use ToonConverter for sending tabular data to OpenAI API.
 *
 * ⚠️ NOTE: This is example code. Actual implementation should:
 * 1. Add feature flag `ai_use_toon_format`
 * 2. Test with real API (gpt-4o-mini)
 * 3. Measure actual token savings
 * 4. Validate parsing accuracy
 */
class OpenAiClientIntegrationExample
{
    private ToonConverter $toonConverter;
    private string $apiKey;
    private string $model;
    
    public function __construct(ToonConverter $toonConverter)
    {
        $this->toonConverter = $toonConverter;
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
    }
    
    /**
     * Example: Send list of movies to AI in TOON format.
     *
     * @param  array<int, array<string, mixed>>  $movies  List of movies
     * @return array<string, mixed>
     */
    public function generateDescriptionsForMovies(array $movies): array
    {
        // Convert movies to TOON format
        $toonData = $this->toonConverter->convert($movies);
        
        // Build prompts
        $systemPrompt = "You are a movie database assistant. Generate descriptions for movies.\n\n".
            "Data is provided in TOON format. TOON uses tabular arrays like [N]{keys}: followed by rows of comma-separated values.\n\n".
            "Return JSON with: title, release_year, director, description, genres (array).";
        
        $userPrompt = "Generate descriptions for these movies:\n\n{$toonData}\n\n".
            "Return JSON array with descriptions for each movie.";
        
        // Send to OpenAI API
        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.7,
        ];
        
        // ... actual API call implementation ...
        
        return [];
    }
    
    /**
     * Example: Compare token usage between JSON and TOON.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return array<string, int> Token counts for each format
     */
    public function compareTokenUsage(array $data): array
    {
        // JSON format
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $jsonTokens = $this->countTokens($json);
        
        // TOON format
        $toon = $this->toonConverter->convert($data);
        $toonTokens = $this->countTokens($toon);
        
        return [
            'json_tokens' => $jsonTokens,
            'toon_tokens' => $toonTokens,
            'savings' => $jsonTokens - $toonTokens,
            'savings_percent' => round((($jsonTokens - $toonTokens) / $jsonTokens) * 100, 2),
        ];
    }
    
    /**
     * Count tokens using tiktoken (for GPT-4) or similar tokenizer.
     *
     * @param  string  $text
     * @return int
     */
    private function countTokens(string $text): int
    {
        // Example: Use tiktoken for GPT-4
        // In production, use actual tokenizer for your model
        // For GPT-4: use tiktoken library
        // For Claude: use Claude's tokenizer
        
        // This is a placeholder - actual implementation should use real tokenizer
        return (int) (strlen($text) / 4); // Rough estimate: ~4 chars per token
    }
}

