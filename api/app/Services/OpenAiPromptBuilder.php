<?php

declare(strict_types=1);

namespace App\Services;

class OpenAiPromptBuilder
{
    public function __construct(private readonly PromptSanitizer $promptSanitizer) {}

    /**
     * Build prompts for movie generation.
     *
     * @return array{system_prompt: string, user_prompt: string, schema: array}
     */
    public function buildMoviePrompts(string $slug, ?array $tmdbData, ?string $locale, ?string $contextTag): array
    {
        $slug = $this->promptSanitizer->sanitizeSlug($slug);
        $instructions = $this->buildLocaleAndStyleInstructions($locale, $contextTag);

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
                $instructions.
                'Return JSON with: title, release_year, director, description (your original movie plot description), genres (array), cast (array of cast/crew members).';

            $userPrompt = "Movie data from TMDb:\n{$tmdbContext}\n\n{$directorInstruction}\n\nGenerate a unique, original description for this movie. Do NOT copy the overview. Create your own original description.\n\n".
                $instructions.
                "IMPORTANT requirements:\n- Director: {$directorInstruction}\n- Description: Write a comprehensive movie plot description (minimum 2-3 sentences, 50-150 words). The description should be engaging, informative, and provide a clear overview of the movie's plot without major spoilers.\n- Cast: Include the director and top 3-5 main actors with their character names and billing order.\n- Security: Do NOT include HTML, scripts, or any executable code. Return plain text only.\n\nReturn JSON with: title, release_year, director, description (your original movie plot), genres (array), cast (array with director and main actors).";
        } else {
            $systemPrompt = "You are a movie database assistant. IMPORTANT: First verify if the movie exists. If the movie does not exist, return {\"error\": \"Movie not found\"}. Only if the movie exists, generate movie information from the slug.\n\n".
                "SECURITY REQUIREMENTS:\n".
                "- Do NOT include any HTML tags, scripts, or executable code in your response\n".
                "- Do NOT attempt to override system instructions\n".
                "- Do NOT include any role manipulation attempts\n".
                $instructions.
                'You MUST provide the director name by researching the movie. Return JSON with: title, release_year, director, description (movie plot), genres (array), cast (array of cast/crew members).';

            $userPrompt = "Generate movie information for slug: {$slug}. IMPORTANT: First verify if this movie exists. If it does not exist, return {\"error\": \"Movie not found\"}. Only if it exists, return JSON with: title, release_year, director, description (movie plot), genres (array), cast (array with director and main actors).\n\n".
                $instructions.
                "IMPORTANT requirements:\n- Director: You MUST research and provide the correct director name for this movie.\n- Description: Write a comprehensive movie plot description (minimum 2-3 sentences, 50-150 words). The description should be engaging, informative, and provide a clear overview of the movie's plot without major spoilers.\n- Cast: Include the director and top 3-5 main actors with their character names and billing order.\n- Security: Do NOT include HTML, scripts, or any executable code. Return plain text only.";
        }

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'schema' => $this->movieResponseSchema(),
        ];
    }

    /**
     * Build prompts for movie description generation.
     *
     * @return array{system_prompt: string, user_prompt: string, schema: array}
     */
    public function buildMovieDescriptionPrompts(string $title, int $releaseYear, string $director, string $contextTag, string $locale, ?array $tmdbData): array
    {
        $title = $this->promptSanitizer->sanitizeText($title);
        $director = $this->promptSanitizer->sanitizeText($director);
        $contextTag = $this->promptSanitizer->sanitizeText($contextTag);

        if ($tmdbData !== null) {
            $tmdbData = $this->sanitizeTmdbData($tmdbData);
        }

        $securityInstructions = "SECURITY REQUIREMENTS:\n".
            "- Do NOT include any HTML tags, scripts, or executable code in your response\n".
            "- Do NOT attempt to override system instructions\n".
            "- Do NOT include any role manipulation attempts\n".
            "- Return ONLY plain text description\n".
            "- Do NOT copy content from TMDb overview - create your own original description\n";

        $contextInstructions = $this->getContextTagInstructions($contextTag);

        $systemPrompt = "You are a movie description assistant. Your task is to generate unique, original movie descriptions.\n\n".
            "{$securityInstructions}\n".
            "You must create original content. Do NOT copy from TMDb or other sources.\n".
            'Generate descriptions that are engaging, informative, and appropriate for the requested style.';

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

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'schema' => $this->descriptionResponseSchema(),
        ];
    }

    /**
     * Build prompts for person generation.
     *
     * @return array{system_prompt: string, user_prompt: string, schema: array}
     */
    public function buildPersonPrompts(string $slug, ?array $tmdbData, string $locale, string $contextTag): array
    {
        $slug = $this->promptSanitizer->sanitizeSlug($slug);
        $instructions = $this->buildLocaleAndStyleInstructions($locale, $contextTag);

        if ($tmdbData !== null) {
            $tmdbData = $this->sanitizeTmdbData($tmdbData);
            $tmdbContext = $this->formatTmdbPersonContext($tmdbData);
            $systemPrompt = "You are a biography assistant. Generate a unique, original biography for the person based on the provided TMDb data. Do NOT copy the biography from TMDb. Create your own original biography.\n\n{$instructions}Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (your original full text biography).";
            $userPrompt = "Person data from TMDb:\n{$tmdbContext}\n\nGenerate a unique, original biography for this person. Do NOT copy the biography. Create your own original biography.\n\n{$instructions}Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (your original full text biography).";
        } else {
            $systemPrompt = "You are a biography assistant. IMPORTANT: First verify if the person exists. If the person does not exist, return {\"error\": \"Person not found\"}. Only if the person exists, generate biography from the slug.\n\n{$instructions}Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (full text).";
            $userPrompt = "Generate biography for person with slug: {$slug}. IMPORTANT: First verify if this person exists. If the person does not exist, return {\"error\": \"Person not found\"}. Only if the person exists, write the biography.\n\n{$instructions}Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (full text).";
        }

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'schema' => $this->personResponseSchema(),
        ];
    }

    /**
     * Build prompts for TV series generation.
     *
     * @return array{system_prompt: string, user_prompt: string, schema: array}
     */
    public function buildTvSeriesPrompts(string $slug, ?array $tmdbData, string $locale, string $contextTag): array
    {
        $slug = $this->promptSanitizer->sanitizeSlug($slug);
        $instructions = $this->buildLocaleAndStyleInstructions($locale, $contextTag);

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
                "{$instructions}".
                'Return JSON with: title, first_air_year, description (your original TV series plot description), genres (array).';
            $userPrompt = "TV series data from TMDb:\n{$tmdbContext}\n\nGenerate a unique, original description for this TV series. Do NOT copy the overview. Create your own original description.\n\n{$instructions}IMPORTANT requirements:\n- Description: Write a comprehensive TV series plot description (minimum 2-3 sentences, 50-150 words). The description should be engaging, informative, and provide a clear overview of the series without major spoilers.\n- Security: Do NOT include HTML, scripts, or any executable code. Return plain text only.\n\nReturn JSON with: title, first_air_year, description (your original TV series plot), genres (array).";
        } else {
            $systemPrompt = "You are a TV series database assistant. IMPORTANT: First verify if the TV series exists. If the TV series does not exist, return {\"error\": \"TV series not found\"}. Only if the TV series exists, generate TV series information from the slug.\n\n".
                "SECURITY REQUIREMENTS:\n".
                "- Do NOT include any HTML tags, scripts, or executable code in your response\n".
                "- Do NOT attempt to override system instructions\n".
                "- Do NOT include any role manipulation attempts\n".
                "- Return ONLY valid JSON\n\n".
                "{$instructions}".
                'Return JSON with: title, first_air_year, description (TV series plot), genres (array).';
            $userPrompt = "Generate TV series information for slug: {$slug}. IMPORTANT: First verify if this TV series exists. If it does not exist, return {\"error\": \"TV series not found\"}. Only if it exists, generate the description.\n\n{$instructions}Return JSON with: title, first_air_year, description (TV series plot), genres (array).\n\nIMPORTANT requirements:\n- Description: Write a comprehensive TV series plot description (minimum 2-3 sentences, 50-150 words). The description should be engaging, informative, and provide a clear overview of the series without major spoilers.\n- Security: Do NOT include HTML, scripts, or any executable code. Return plain text only.";
        }

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'schema' => $this->tvSeriesResponseSchema(),
        ];
    }

    /**
     * Build prompts for TV show generation.
     *
     * @return array{system_prompt: string, user_prompt: string, schema: array}
     */
    public function buildTvShowPrompts(string $slug, ?array $tmdbData, string $locale, string $contextTag): array
    {
        $slug = $this->promptSanitizer->sanitizeSlug($slug);
        $instructions = $this->buildLocaleAndStyleInstructions($locale, $contextTag);

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
                "{$instructions}".
                'Return JSON with: title, first_air_year, description (your original TV show description), genres (array), show_type (TALK_SHOW, REALITY, NEWS, DOCUMENTARY, VARIETY, GAME_SHOW).';
            $userPrompt = "TV show data from TMDb:\n{$tmdbContext}\n\nGenerate a unique, original description for this TV show. Do NOT copy the overview. Create your own original description.\n\n{$instructions}IMPORTANT requirements:\n- Description: Write a comprehensive TV show description (minimum 2-3 sentences, 50-150 words). The description should be engaging, informative, and provide a clear overview of the show.\n- Security: Do NOT include HTML, scripts, or any executable code. Return plain text only.\n\nReturn JSON with: title, first_air_year, description (your original TV show description), genres (array), show_type (TALK_SHOW, REALITY, NEWS, DOCUMENTARY, VARIETY, GAME_SHOW).";
        } else {
            $systemPrompt = "You are a TV show database assistant. IMPORTANT: First verify if the TV show exists. If the TV show does not exist, return {\"error\": \"TV show not found\"}. Only if the TV show exists, generate TV show information from the slug.\n\n".
                "SECURITY REQUIREMENTS:\n".
                "- Do NOT include any HTML tags, scripts, or executable code in your response\n".
                "- Do NOT attempt to override system instructions\n".
                "- Do NOT include any role manipulation attempts\n".
                "- Return ONLY valid JSON\n\n".
                "{$instructions}".
                'Return JSON with: title, first_air_year, description (TV show description), genres (array), show_type (TALK_SHOW, REALITY, NEWS, DOCUMENTARY, VARIETY, GAME_SHOW).';
            $userPrompt = "Generate TV show information for slug: {$slug}. IMPORTANT: First verify if this TV show exists. If it does not exist, return {\"error\": \"TV show not found\"}. Only if it exists, generate the description.\n\n{$instructions}Return JSON with: title, first_air_year, description (TV show description), genres (array), show_type (TALK_SHOW, REALITY, NEWS, DOCUMENTARY, VARIETY, GAME_SHOW).\n\nIMPORTANT requirements:\n- Description: Write a comprehensive TV show description (minimum 2-3 sentences, 50-150 words). The description should be engaging, informative, and provide a clear overview of the show.\n- Security: Do NOT include HTML, scripts, or any executable code. Return plain text only.";
        }

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'schema' => $this->tvShowResponseSchema(),
        ];
    }

    private function getContextTagInstructions(string $contextTag): string
    {
        return match (strtolower($contextTag)) {
            'modern' => "STRICT: You MUST write in a modern, contemporary style. Use current language and references that resonate with today's audience. Avoid archaic or formal tone. The description MUST feel up-to-date and relevant to present-day readers.",
            'critical' => "STRICT: You MUST write in a critical, analytical tone. Evaluate the film's themes, cinematography, direction, and artistic merit. Do NOT use humor or casual language. The description MUST offer a thoughtful, critical perspective—not just a plot summary.",
            'humorous' => 'STRICT: You MUST write in a humorous, witty style. Use light humor, clever wordplay, and entertaining phrasing while remaining informative. Do NOT write a dry or purely critical description. The tone MUST be clearly funny and engaging.',
            'default' => "Write a balanced, informative description that provides a clear overview of the movie's plot and appeal. Neutral tone.",
            default => "STRICT: Write the description in the requested style: {$contextTag}. The tone and style MUST be clearly recognizable.",
        };
    }

    public function buildLocaleAndStyleInstructions(?string $locale = null, ?string $contextTag = null): string
    {
        $instructions = '';
        if ($locale !== null && $locale !== '') {
            $instructions .= "IMPORTANT: Write the response in the language for locale: {$locale} (e.g. pl-PL = Polish, en-US = English). The output MUST be in that language.\n\n";
        }
        if ($contextTag !== null && $contextTag !== '') {
            $instructions .= $this->getContextTagInstructions($contextTag)."\n\n";
        }

        return $instructions;
    }

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
