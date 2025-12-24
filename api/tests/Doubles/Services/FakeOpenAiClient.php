<?php

declare(strict_types=1);

namespace Tests\Doubles\Services;

use App\Services\OpenAiClientInterface;

/**
 * Fake implementation of OpenAiClientInterface for testing.
 *
 * Allows configuring responses that will be returned by AI generation methods.
 * Framework-agnostic test double that implements the interface directly.
 */
class FakeOpenAiClient implements OpenAiClientInterface
{
    /**
     * @var array<string, array{success: bool, title?: string, release_year?: int, director?: string, description?: string, genres?: array, cast?: array, model?: string, error?: string}>
     */
    private array $movieResponses = [];

    /**
     * @var array<string, array{success: bool, name?: string, birth_date?: string, birthplace?: string, biography?: string, model?: string, error?: string}>
     */
    private array $personResponses = [];

    /**
     * @var array<string, array{success: bool, title?: string, first_air_year?: int, description?: string, genres?: array, model?: string, error?: string}>
     */
    private array $tvSeriesResponses = [];

    /**
     * @var array<string, array{success: bool, title?: string, first_air_year?: int, description?: string, genres?: array, show_type?: string, model?: string, error?: string}>
     */
    private array $tvShowResponses = [];

    /**
     * @var array{success: bool, message?: string, status?: int, model?: string, rate_limit?: array<string, int|string|null>, error?: string}|null
     */
    private ?array $healthResponse = null;

    /**
     * Set movie generation response.
     *
     * @param  string  $slug  Movie slug
     * @param  array{success: bool, title?: string, release_year?: int, director?: string, description?: string, genres?: array, model?: string, error?: string}  $response  Response data
     */
    public function setMovieResponse(string $slug, array $response): void
    {
        $this->movieResponses[$slug] = $response;
    }

    /**
     * Set person generation response.
     *
     * @param  string  $slug  Person slug
     * @param  array{success: bool, name?: string, birth_date?: string, birthplace?: string, biography?: string, model?: string, error?: string}  $response  Response data
     */
    public function setPersonResponse(string $slug, array $response): void
    {
        $this->personResponses[$slug] = $response;
    }

    /**
     * Set TV series generation response.
     *
     * @param  string  $slug  TV Series slug
     * @param  array{success: bool, title?: string, first_air_year?: int, description?: string, genres?: array, model?: string, error?: string}  $response  Response data
     */
    public function setTvSeriesResponse(string $slug, array $response): void
    {
        $this->tvSeriesResponses[$slug] = $response;
    }

    /**
     * Set TV show generation response.
     *
     * @param  string  $slug  TV Show slug
     * @param  array{success: bool, title?: string, first_air_year?: int, description?: string, genres?: array, show_type?: string, model?: string, error?: string}  $response  Response data
     */
    public function setTvShowResponse(string $slug, array $response): void
    {
        $this->tvShowResponses[$slug] = $response;
    }

    /**
     * Set health check response.
     *
     * @param  array{success: bool, message?: string, status?: int, model?: string, rate_limit?: array<string, int|string|null>, error?: string}  $response  Health response
     */
    public function setHealthResponse(array $response): void
    {
        $this->healthResponse = $response;
    }

    /**
     * Clear all configured responses.
     */
    public function clear(): void
    {
        $this->movieResponses = [];
        $this->personResponses = [];
        $this->tvSeriesResponses = [];
        $this->tvShowResponses = [];
        $this->healthResponse = null;
    }

    /**
     * {@inheritDoc}
     */
    public function generateMovie(string $slug, ?array $tmdbData = null): array
    {
        if (isset($this->movieResponses[$slug])) {
            return $this->movieResponses[$slug];
        }

        // Default response if not configured
        return [
            'success' => false,
            'error' => 'Movie response not configured in fake',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function generateMovieDescription(
        string $title,
        int $releaseYear,
        string $director,
        string $contextTag,
        string $locale,
        ?array $tmdbData = null
    ): array {
        // For fake, return a simple description
        return [
            'success' => true,
            'description' => "Generated description for {$title} ({$contextTag}, {$locale})",
            'model' => 'fake-model',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function generatePerson(string $slug, ?array $tmdbData = null): array
    {
        if (isset($this->personResponses[$slug])) {
            return $this->personResponses[$slug];
        }

        // Default response if not configured
        return [
            'success' => false,
            'error' => 'Person response not configured in fake',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function generateTvSeries(string $slug, ?array $tmdbData = null): array
    {
        if (isset($this->tvSeriesResponses[$slug])) {
            return $this->tvSeriesResponses[$slug];
        }

        // Default response if not configured
        return [
            'success' => false,
            'error' => 'TV Series response not configured in fake',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function generateTvShow(string $slug, ?array $tmdbData = null): array
    {
        if (isset($this->tvShowResponses[$slug])) {
            return $this->tvShowResponses[$slug];
        }

        // Default response if not configured
        return [
            'success' => false,
            'error' => 'TV Show response not configured in fake',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function health(): array
    {
        if ($this->healthResponse !== null) {
            return $this->healthResponse;
        }

        // Default health response
        return [
            'success' => true,
            'message' => 'Fake OpenAI client is healthy',
            'model' => 'fake-model',
        ];
    }
}
