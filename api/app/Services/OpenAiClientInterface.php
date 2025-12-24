<?php

namespace App\Services;

interface OpenAiClientInterface
{
    /**
     * Generate movie information from a slug using AI.
     *
     * @param  string  $slug  Movie slug
     * @param  array{title: string, release_date: string, overview: string, id: int, director?: string}|null  $tmdbData  Optional TMDb data to provide context to AI
     * @return array{success: bool, title?: string, release_year?: int, director?: string, description?: string, genres?: array, model?: string, error?: string}
     */
    public function generateMovie(string $slug, ?array $tmdbData = null): array;

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
    ): array;

    /**
     * Generate person biography from a slug using AI.
     *
     * @param  string  $slug  Person slug
     * @param  array{name: string, birthday: string, place_of_birth: string, id: int, biography?: string}|null  $tmdbData  Optional TMDb data to provide context to AI
     * @return array{success: bool, name?: string, birth_date?: string, birthplace?: string, biography?: string, model?: string, error?: string}
     */
    public function generatePerson(string $slug, ?array $tmdbData = null): array;

    /**
     * Generate TV series information from a slug using AI.
     *
     * @param  string  $slug  TV Series slug
     * @param  array{name: string, first_air_date: string, overview: string, id: int}|null  $tmdbData  Optional TMDb data to provide context to AI
     * @return array{success: bool, title?: string, first_air_year?: int, description?: string, genres?: array, model?: string, error?: string}
     */
    public function generateTvSeries(string $slug, ?array $tmdbData = null): array;

    /**
     * Generate TV show information from a slug using AI.
     *
     * @param  string  $slug  TV Show slug
     * @param  array{name: string, first_air_date: string, overview: string, id: int}|null  $tmdbData  Optional TMDb data to provide context to AI
     * @return array{success: bool, title?: string, first_air_year?: int, description?: string, genres?: array, show_type?: string, model?: string, error?: string}
     */
    public function generateTvShow(string $slug, ?array $tmdbData = null): array;

    /**
     * Perform a lightweight health check against the OpenAI API.
     *
     * @return array{success: bool, message?: string, status?: int, model?: string, rate_limit?: array<string, int|string|null>, error?: string}
     */
    public function health(): array;
}
