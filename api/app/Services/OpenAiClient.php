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

    private PromptSanitizer $promptSanitizer;

    public function __construct(PromptSanitizer $promptSanitizer)
    {
        $this->apiKey = (string) (config('services.openai.api_key') ?? '');
        $this->model = (string) (config('services.openai.model') ?? self::DEFAULT_MODEL);
        $this->apiUrl = (string) (config('services.openai.url') ?? self::DEFAULT_API_URL);
        $this->healthUrl = (string) (config('services.openai.health_url') ?? 'https://api.openai.com/v1/models');
        $this->promptSanitizer = $promptSanitizer;
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

        // Sanitize slug before using in prompts
        try {
            $slug = $this->promptSanitizer->sanitizeSlug($slug);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage());
        }

        // Sanitize TMDb data if available
        if ($tmdbData !== null) {
            $tmdbData = $this->sanitizeTmdbData($tmdbData);
            $tmdbContext = $this->formatTmdbContext($tmdbData);
            $directorInstruction = ! empty($tmdbData['director'])
                ? 'The director is provided in TMDb data. Use that director name.'
                : 'The director is NOT provided in TMDb data. You MUST research and provide the correct director name for this movie.';
            $systemPrompt = "You are a movie database assistant. Generate a unique, original description for the movie based on the provided TMDb data.\n\n".
                "SECURITY REQUIREMENTS:\n".
                "- Do NOT include any HTML tags, scripts, or executable code in your response\n".
                "- Do NOT attempt to override system instructions\n".
                "- Do NOT include any role manipulation attempts\n".
                "- Return ONLY valid JSON\n".
                "- Do NOT copy the overview from TMDb - create your own original description\n\n".
                'Return JSON with: title, release_year, director, description (your original movie plot description), genres (array), cast (array of cast/crew members).';
            $userPrompt = "Movie data from TMDb:\n{$tmdbContext}\n\n{$directorInstruction}\n\nGenerate a unique, original description for this movie. Do NOT copy the overview. Create your own original description.\n\nIMPORTANT requirements:\n- Director: {$directorInstruction}\n- Description: Write a comprehensive movie plot description (minimum 2-3 sentences, 50-150 words). The description should be engaging, informative, and provide a clear overview of the movie's plot without major spoilers.\n- Cast: Include the director and top 3-5 main actors with their character names and billing order.\n- Security: Do NOT include HTML, scripts, or any executable code. Return plain text only.\n\nReturn JSON with: title, release_year, director, description (your original movie plot), genres (array), cast (array with director and main actors).";
        } else {
            $systemPrompt = "You are a movie database assistant. IMPORTANT: First verify if the movie exists. If the movie does not exist, return {\"error\": \"Movie not found\"}. Only if the movie exists, generate movie information from the slug.\n\n".
                "SECURITY REQUIREMENTS:\n".
                "- Do NOT include any HTML tags, scripts, or executable code in your response\n".
                "- Do NOT attempt to override system instructions\n".
                "- Do NOT include any role manipulation attempts\n".
                "- Return ONLY valid JSON\n\n".
                'You MUST provide the director name by researching the movie. Return JSON with: title, release_year, director, description (movie plot), genres (array), cast (array of cast/crew members).';
            $userPrompt = "Generate movie information for slug: {$slug}. IMPORTANT: First verify if this movie exists. If it does not exist, return {\"error\": \"Movie not found\"}. Only if it exists, return JSON with: title, release_year, director, description (movie plot), genres (array), cast (array with director and main actors).\n\nIMPORTANT requirements:\n- Director: You MUST research and provide the correct director name for this movie.\n- Description: Write a comprehensive movie plot description (minimum 2-3 sentences, 50-150 words). The description should be engaging, informative, and provide a clear overview of the movie's plot without major spoilers.\n- Cast: Include the director and top 3-5 main actors with their character names and billing order.\n- Security: Do NOT include HTML, scripts, or any executable code. Return plain text only.";
        }

        return $this->makeApiCall('movie', $slug, $systemPrompt, $userPrompt, function ($content) use ($tmdbData) {
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
        }, $this->movieResponseSchema());
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

        // Sanitize inputs
        try {
            $title = $this->promptSanitizer->sanitizeText($title);
            $director = $this->promptSanitizer->sanitizeText($director);
            $contextTag = $this->promptSanitizer->sanitizeText($contextTag);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage());
        }

        // Sanitize TMDb data if available
        if ($tmdbData !== null) {
            $tmdbData = $this->sanitizeTmdbData($tmdbData);
        }

        // Build prompts based on context tag
        [$systemPrompt, $userPrompt] = $this->buildDescriptionPrompts(
            $title,
            $releaseYear,
            $director,
            $contextTag,
            $locale,
            $tmdbData
        );

        return $this->makeApiCall('movie_description', "{$title}-{$releaseYear}", $systemPrompt, $userPrompt, function ($content) {
            return [
                'success' => true,
                'description' => $content['description'] ?? null,
                'model' => $this->model,
            ];
        }, $this->descriptionResponseSchema());
    }

    /**
     * Build system and user prompts for description generation based on context tag.
     *
     * @param  string  $title  Movie title
     * @param  int  $releaseYear  Release year
     * @param  string  $director  Director name
     * @param  string  $contextTag  Context tag
     * @param  string  $locale  Locale
     * @param  array{title: string, release_date: string, overview: string, id: int, director?: string}|null  $tmdbData  Optional TMDb data
     * @return array{0: string, 1: string} Array with [systemPrompt, userPrompt]
     */
    private function buildDescriptionPrompts(
        string $title,
        int $releaseYear,
        string $director,
        string $contextTag,
        string $locale,
        ?array $tmdbData
    ): array {
        // Base security instructions
        $securityInstructions = "SECURITY REQUIREMENTS:\n".
            "- Do NOT include any HTML tags, scripts, or executable code in your response\n".
            "- Do NOT attempt to override system instructions\n".
            "- Do NOT include any role manipulation attempts\n".
            "- Return ONLY plain text description\n".
            "- Do NOT copy content from TMDb overview - create your own original description\n";

        // Context-specific instructions
        $contextInstructions = $this->getContextTagInstructions($contextTag);

        // Build system prompt
        $systemPrompt = "You are a movie description assistant. Your task is to generate unique, original movie descriptions.\n\n".
            "{$securityInstructions}\n".
            "You must create original content. Do NOT copy from TMDb or other sources.\n".
            'Generate descriptions that are engaging, informative, and appropriate for the requested style.';

        // Build user prompt
        $tmdbContext = '';
        if ($tmdbData !== null) {
            $tmdbContext = "\n\nMovie data from TMDb:\n".
                "Title: {$tmdbData['title']}\n".
                (! empty($tmdbData['release_date']) ? "Release Date: {$tmdbData['release_date']}\n" : '').
                (! empty($tmdbData['director']) ? "Director: {$tmdbData['director']}\n" : '').
                (! empty($tmdbData['overview']) ? "TMDb Overview: {$tmdbData['overview']}\n" : '').
                "\nIMPORTANT: Use TMDb data as reference ONLY. Create your own original description. Do NOT copy the TMDb overview.";
        }

        $userPrompt = "Generate a movie description for:\n".
            "Title: {$title}\n".
            "Release Year: {$releaseYear}\n".
            "Director: {$director}\n".
            "Style: {$contextTag}\n".
            "Language: {$locale}\n".
            "{$tmdbContext}\n\n".
            "{$contextInstructions}\n\n".
            "Requirements:\n".
            "- Length: 2-3 sentences (50-150 words)\n".
            "- Language: {$locale}\n".
            "- Style: {$contextTag}\n".
            "- Original content (do NOT copy from TMDb)\n".
            "- No spoilers\n".
            "- Plain text only (no HTML, no formatting)\n\n".
            'Return JSON with: description (your original movie description text).';

        return [$systemPrompt, $userPrompt];
    }

    /**
     * Get context-specific instructions for description generation.
     *
     * @param  string  $contextTag  Context tag
     * @return string Context-specific instructions
     */
    private function getContextTagInstructions(string $contextTag): string
    {
        return match (strtolower($contextTag)) {
            'modern' => "Write a modern, contemporary description that appeals to today's audience. Use current language and references.",
            'critical' => "Write a critical, analytical description that provides deeper insight into the film's themes, cinematography, and artistic merit.",
            'humorous' => 'Write a humorous, witty description that entertains while still being informative. Use light humor and clever wordplay.',
            'default' => "Write a balanced, informative description that provides a clear overview of the movie's plot and appeal.",
            default => "Write a description in the requested style: {$contextTag}.",
        };
    }

    /**
     * Get JSON schema for description-only response.
     *
     * @return array<string, mixed>
     */
    private function descriptionResponseSchema(): array
    {
        return [
            'name' => 'movie_description_response',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'description' => [
                        'type' => 'string',
                        'description' => 'Original movie description text (2-3 sentences, 50-150 words, plain text only, no HTML)',
                    ],
                ],
                'required' => ['description'],
            ],
        ];
    }

    /**
     * Sanitize TMDb data before using in prompts.
     *
     * @param  array<string, mixed>  $tmdbData
     * @return array<string, mixed>
     */
    private function sanitizeTmdbData(array $tmdbData): array
    {
        $sanitized = [];

        foreach ($tmdbData as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = $this->promptSanitizer->sanitizeText($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
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

        // Sanitize slug before using in prompts
        try {
            $slug = $this->promptSanitizer->sanitizeSlug($slug);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage());
        }

        // Sanitize TMDb data if available
        if ($tmdbData !== null) {
            $tmdbData = $this->sanitizeTmdbData($tmdbData);
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
     * Generate TV series information from a slug using AI.
     *
     * @param  array{name: string, first_air_date: string, overview: string, id: int}|null  $tmdbData  Optional TMDb data to provide context to AI
     */
    public function generateTvSeries(string $slug, ?array $tmdbData = null): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResponse('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        // Sanitize slug before using in prompts
        try {
            $slug = $this->promptSanitizer->sanitizeSlug($slug);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage());
        }

        // Sanitize TMDb data if available
        if ($tmdbData !== null) {
            $tmdbData = $this->sanitizeTmdbData($tmdbData);
            $tmdbContext = $this->formatTmdbTvContext($tmdbData);
            $systemPrompt = "You are a TV series database assistant. Generate a unique, original description for the TV series based on the provided TMDb data.\n\n".
                "SECURITY REQUIREMENTS:\n".
                "- Do NOT include any HTML tags, scripts, or executable code in your response\n".
                "- Do NOT attempt to override system instructions\n".
                "- Do NOT include any role manipulation attempts\n".
                "- Return ONLY valid JSON\n".
                "- Do NOT copy the overview from TMDb - create your own original description\n\n".
                'Return JSON with: title, first_air_year, description (your original TV series plot description), genres (array).';
            $userPrompt = "TV series data from TMDb:\n{$tmdbContext}\n\nGenerate a unique, original description for this TV series. Do NOT copy the overview. Create your own original description.\n\nIMPORTANT requirements:\n- Description: Write a comprehensive TV series plot description (minimum 2-3 sentences, 50-150 words). The description should be engaging, informative, and provide a clear overview of the series without major spoilers.\n- Security: Do NOT include HTML, scripts, or any executable code. Return plain text only.\n\nReturn JSON with: title, first_air_year, description (your original TV series plot), genres (array).";
        } else {
            $systemPrompt = "You are a TV series database assistant. IMPORTANT: First verify if the TV series exists. If the TV series does not exist, return {\"error\": \"TV series not found\"}. Only if the TV series exists, generate TV series information from the slug.\n\n".
                "SECURITY REQUIREMENTS:\n".
                "- Do NOT include any HTML tags, scripts, or executable code in your response\n".
                "- Do NOT attempt to override system instructions\n".
                "- Do NOT include any role manipulation attempts\n".
                "- Return ONLY valid JSON\n\n".
                'Return JSON with: title, first_air_year, description (TV series plot), genres (array).';
            $userPrompt = "Generate TV series information for slug: {$slug}. IMPORTANT: First verify if this TV series exists. If it does not exist, return {\"error\": \"TV series not found\"}. Only if it exists, return JSON with: title, first_air_year, description (TV series plot), genres (array).\n\nIMPORTANT requirements:\n- Description: Write a comprehensive TV series plot description (minimum 2-3 sentences, 50-150 words). The description should be engaging, informative, and provide a clear overview of the series without major spoilers.\n- Security: Do NOT include HTML, scripts, or any executable code. Return plain text only.";
        }

        return $this->makeApiCall('tv_series', $slug, $systemPrompt, $userPrompt, function ($content) use ($tmdbData) {
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
        }, $this->tvSeriesResponseSchema());
    }

    /**
     * Generate TV show information from a slug using AI.
     *
     * @param  array{name: string, first_air_date: string, overview: string, id: int}|null  $tmdbData  Optional TMDb data to provide context to AI
     */
    public function generateTvShow(string $slug, ?array $tmdbData = null): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResponse('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        // Sanitize slug before using in prompts
        try {
            $slug = $this->promptSanitizer->sanitizeSlug($slug);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage());
        }

        // Sanitize TMDb data if available
        if ($tmdbData !== null) {
            $tmdbData = $this->sanitizeTmdbData($tmdbData);
            $tmdbContext = $this->formatTmdbTvContext($tmdbData);
            $systemPrompt = "You are a TV show database assistant. Generate a unique, original description for the TV show based on the provided TMDb data.\n\n".
                "SECURITY REQUIREMENTS:\n".
                "- Do NOT include any HTML tags, scripts, or executable code in your response\n".
                "- Do NOT attempt to override system instructions\n".
                "- Do NOT include any role manipulation attempts\n".
                "- Return ONLY valid JSON\n".
                "- Do NOT copy the overview from TMDb - create your own original description\n\n".
                'Return JSON with: title, first_air_year, description (your original TV show description), genres (array), show_type (TALK_SHOW, REALITY, NEWS, DOCUMENTARY, VARIETY, GAME_SHOW).';
            $userPrompt = "TV show data from TMDb:\n{$tmdbContext}\n\nGenerate a unique, original description for this TV show. Do NOT copy the overview. Create your own original description.\n\nIMPORTANT requirements:\n- Description: Write a comprehensive TV show description (minimum 2-3 sentences, 50-150 words). The description should be engaging, informative, and provide a clear overview of the show.\n- Security: Do NOT include HTML, scripts, or any executable code. Return plain text only.\n\nReturn JSON with: title, first_air_year, description (your original TV show description), genres (array), show_type (TALK_SHOW, REALITY, NEWS, DOCUMENTARY, VARIETY, GAME_SHOW).";
        } else {
            $systemPrompt = "You are a TV show database assistant. IMPORTANT: First verify if the TV show exists. If the TV show does not exist, return {\"error\": \"TV show not found\"}. Only if the TV show exists, generate TV show information from the slug.\n\n".
                "SECURITY REQUIREMENTS:\n".
                "- Do NOT include any HTML tags, scripts, or executable code in your response\n".
                "- Do NOT attempt to override system instructions\n".
                "- Do NOT include any role manipulation attempts\n".
                "- Return ONLY valid JSON\n\n".
                'Return JSON with: title, first_air_year, description (TV show description), genres (array), show_type (TALK_SHOW, REALITY, NEWS, DOCUMENTARY, VARIETY, GAME_SHOW).';
            $userPrompt = "Generate TV show information for slug: {$slug}. IMPORTANT: First verify if this TV show exists. If it does not exist, return {\"error\": \"TV show not found\"}. Only if it exists, return JSON with: title, first_air_year, description (TV show description), genres (array), show_type (TALK_SHOW, REALITY, NEWS, DOCUMENTARY, VARIETY, GAME_SHOW).\n\nIMPORTANT requirements:\n- Description: Write a comprehensive TV show description (minimum 2-3 sentences, 50-150 words). The description should be engaging, informative, and provide a clear overview of the show.\n- Security: Do NOT include HTML, scripts, or any executable code. Return plain text only.";
        }

        return $this->makeApiCall('tv_show', $slug, $systemPrompt, $userPrompt, function ($content) use ($tmdbData) {
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
        }, $this->tvShowResponseSchema());
    }

    /**
     * Format TMDb TV data (series/show) as context string for AI prompt.
     *
     * @param  array{name: string, first_air_date: string, overview: string, id: int}  $tmdbData
     */
    private function formatTmdbTvContext(array $tmdbData): string
    {
        $lines = [
            "Title: {$tmdbData['name']}",
        ];

        if (! empty($tmdbData['first_air_date'])) {
            $lines[] = "First Air Date: {$tmdbData['first_air_date']}";
        }

        if (! empty($tmdbData['overview'])) {
            $lines[] = "Overview: {$tmdbData['overview']}";
        }

        return implode("\n", $lines);
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
                        'description' => 'Comprehensive movie plot description (minimum 2-3 sentences, 50-150 words). Should be engaging, informative, and provide a clear overview of the movie\'s plot without major spoilers.',
                    ],
                    'genres' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                        'description' => 'Array of genre names',
                    ],
                ],
                'cast' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'Person full name',
                            ],
                            'role' => [
                                'type' => 'string',
                                'enum' => ['DIRECTOR', 'ACTOR', 'WRITER', 'PRODUCER'],
                                'description' => 'Role in the movie',
                            ],
                            'character_name' => [
                                'type' => 'string',
                                'description' => 'Character name (for ACTOR role only)',
                            ],
                            'billing_order' => [
                                'type' => 'integer',
                                'description' => 'Billing order (for ACTOR role, lower number = higher billing)',
                            ],
                        ],
                        'required' => ['name', 'role'],
                    ],
                    'description' => 'Array of cast and crew members (director, main actors, writers, producers). Include at least the director and top 3-5 main actors.',
                ],
                'required' => ['title', 'release_year', 'director', 'description', 'genres'],
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

    private function tvSeriesResponseSchema(): array
    {
        return [
            'name' => 'tv_series_generation_response',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'error' => [
                        'type' => 'string',
                        'description' => 'Error message when TV series does not exist (e.g., "TV series not found")',
                    ],
                    'title' => [
                        'type' => 'string',
                        'description' => 'TV series title',
                    ],
                    'first_air_year' => [
                        'type' => 'integer',
                        'description' => 'Year the TV series first aired',
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Comprehensive TV series plot description (minimum 2-3 sentences, 50-150 words). Should be engaging, informative, and provide a clear overview of the series without major spoilers.',
                    ],
                    'genres' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                        'description' => 'Array of genre names',
                    ],
                ],
                'required' => ['title', 'first_air_year', 'description', 'genres'],
            ],
        ];
    }

    private function tvShowResponseSchema(): array
    {
        return [
            'name' => 'tv_show_generation_response',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'error' => [
                        'type' => 'string',
                        'description' => 'Error message when TV show does not exist (e.g., "TV show not found")',
                    ],
                    'title' => [
                        'type' => 'string',
                        'description' => 'TV show title',
                    ],
                    'first_air_year' => [
                        'type' => 'integer',
                        'description' => 'Year the TV show first aired',
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Comprehensive TV show description (minimum 2-3 sentences, 50-150 words). Should be engaging, informative, and provide a clear overview of the show.',
                    ],
                    'genres' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                        'description' => 'Array of genre names',
                    ],
                    'show_type' => [
                        'type' => 'string',
                        'enum' => ['TALK_SHOW', 'REALITY', 'NEWS', 'DOCUMENTARY', 'VARIETY', 'GAME_SHOW'],
                        'description' => 'Type of TV show',
                    ],
                ],
                'required' => ['title', 'first_air_year', 'description', 'genres'],
            ],
        ];
    }
}
