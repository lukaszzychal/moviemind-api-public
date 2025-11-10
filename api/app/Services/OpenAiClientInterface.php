<?php

namespace App\Services;

interface OpenAiClientInterface
{
    /**
     * Generate movie information from a slug using AI.
     *
     * @param  string  $slug  Movie slug
     * @return array{success: bool, title?: string, release_year?: int, director?: string, description?: string, genres?: array, model?: string, error?: string}
     */
    public function generateMovie(string $slug): array;

    /**
     * Generate person biography from a slug using AI.
     *
     * @param  string  $slug  Person slug
     * @return array{success: bool, name?: string, birth_date?: string, birthplace?: string, biography?: string, model?: string, error?: string}
     */
    public function generatePerson(string $slug): array;

    /**
     * Perform a lightweight health check against the OpenAI API.
     *
     * @return array{success: bool, message?: string, status?: int, model?: string, rate_limit?: array<string, int|string|null>, error?: string}
     */
    public function health(): array;
}
