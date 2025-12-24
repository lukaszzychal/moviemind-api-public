<?php

declare(strict_types=1);

namespace Tests\Doubles\Services;

use App\Services\EntityVerificationServiceInterface;

/**
 * Fake implementation of EntityVerificationServiceInterface for testing.
 *
 * Allows configuring movie and person data that will be returned by verification methods.
 * Framework-agnostic test double that implements the interface directly.
 */
class FakeEntityVerificationService implements EntityVerificationServiceInterface
{
    /**
     * @var array<string, array{title: string, release_date: string, overview: string, id: int, director?: string}|null>
     */
    private array $movies = [];

    /**
     * @var array<string, array<int, array{title: string, release_date: string, overview: string, id: int, director?: string}>>
     */
    private array $movieSearchResults = [];

    /**
     * @var array<string, array{name: string, birthday: string, place_of_birth: string, id: int, biography?: string}|null>
     */
    private array $people = [];

    /**
     * @var array<string, array<int, array{name: string, birthday?: string, place_of_birth?: string, id: int, biography?: string}>>
     */
    private array $personSearchResults = [];

    /**
     * @var array<string, array{name: string, first_air_date: string, overview: string, id: int}|null>
     */
    private array $tvSeries = [];

    /**
     * @var array<string, array<int, array{name: string, first_air_date: string, overview: string, id: int}>>
     */
    private array $tvSeriesSearchResults = [];

    /**
     * @var array<string, array{name: string, first_air_date: string, overview: string, id: int}|null>
     */
    private array $tvShows = [];

    /**
     * @var array<string, array<int, array{name: string, first_air_date: string, overview: string, id: int}>>
     */
    private array $tvShowSearchResults = [];

    /**
     * Set movie data that will be returned by verifyMovie().
     *
     * @param  string  $slug  Movie slug
     * @param  array{title: string, release_date: string, overview: string, id: int, director?: string}|null  $data  Movie data or null for "not found"
     */
    public function setMovie(string $slug, ?array $data): void
    {
        $this->movies[$slug] = $data;
    }

    /**
     * Set search results that will be returned by searchMovies().
     *
     * @param  string  $slug  Movie slug
     * @param  array<int, array{title: string, release_date: string, overview: string, id: int, director?: string}>  $results  Search results
     */
    public function setMovieSearchResults(string $slug, array $results): void
    {
        $this->movieSearchResults[$slug] = $results;
    }

    /**
     * Set person data that will be returned by verifyPerson().
     *
     * @param  string  $slug  Person slug
     * @param  array{name: string, birthday: string, place_of_birth: string, id: int, biography?: string}|null  $data  Person data or null for "not found"
     */
    public function setPerson(string $slug, ?array $data): void
    {
        $this->people[$slug] = $data;
    }

    /**
     * Set search results that will be returned by searchPeople().
     *
     * @param  string  $slug  Person slug
     * @param  array<int, array{name: string, birthday?: string, place_of_birth?: string, id: int, biography?: string}>  $results  Search results
     */
    public function setPersonSearchResults(string $slug, array $results): void
    {
        $this->personSearchResults[$slug] = $results;
    }

    /**
     * Set TV series data that will be returned by verifyTvSeries().
     *
     * @param  string  $slug  TV Series slug
     * @param  array{name: string, first_air_date: string, overview: string, id: int}|null  $data  TV Series data or null for "not found"
     */
    public function setTvSeries(string $slug, ?array $data): void
    {
        $this->tvSeries[$slug] = $data;
    }

    /**
     * Set search results that will be returned by searchTvSeries().
     *
     * @param  string  $slug  TV Series slug
     * @param  array<int, array{name: string, first_air_date: string, overview: string, id: int}>  $results  Search results
     */
    public function setTvSeriesSearchResults(string $slug, array $results): void
    {
        $this->tvSeriesSearchResults[$slug] = $results;
    }

    /**
     * Set TV show data that will be returned by verifyTvShow().
     *
     * @param  string  $slug  TV Show slug
     * @param  array{name: string, first_air_date: string, overview: string, id: int}|null  $data  TV Show data or null for "not found"
     */
    public function setTvShow(string $slug, ?array $data): void
    {
        $this->tvShows[$slug] = $data;
    }

    /**
     * Set search results that will be returned by searchTvShows().
     *
     * @param  string  $slug  TV Show slug
     * @param  array<int, array{name: string, first_air_date: string, overview: string, id: int}>  $results  Search results
     */
    public function setTvShowSearchResults(string $slug, array $results): void
    {
        $this->tvShowSearchResults[$slug] = $results;
    }

    /**
     * Clear all configured data.
     */
    public function clear(): void
    {
        $this->movies = [];
        $this->movieSearchResults = [];
        $this->people = [];
        $this->personSearchResults = [];
        $this->tvSeries = [];
        $this->tvSeriesSearchResults = [];
        $this->tvShows = [];
        $this->tvShowSearchResults = [];
    }

    /**
     * {@inheritDoc}
     */
    public function verifyMovie(string $slug): ?array
    {
        return $this->movies[$slug] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function searchMovies(string $slug, int $limit = 5): array
    {
        $results = $this->movieSearchResults[$slug] ?? [];

        return array_slice($results, 0, $limit);
    }

    /**
     * {@inheritDoc}
     */
    public function verifyPerson(string $slug): ?array
    {
        return $this->people[$slug] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function searchPeople(string $slug, int $limit = 5): array
    {
        $results = $this->personSearchResults[$slug] ?? [];

        return array_slice($results, 0, $limit);
    }

    /**
     * {@inheritDoc}
     */
    public function verifyTvSeries(string $slug): ?array
    {
        return $this->tvSeries[$slug] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function searchTvSeries(string $slug, int $limit = 5): array
    {
        $results = $this->tvSeriesSearchResults[$slug] ?? [];

        return array_slice($results, 0, $limit);
    }

    /**
     * {@inheritDoc}
     */
    public function verifyTvShow(string $slug): ?array
    {
        return $this->tvShows[$slug] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function searchTvShows(string $slug, int $limit = 5): array
    {
        $results = $this->tvShowSearchResults[$slug] ?? [];

        return array_slice($results, 0, $limit);
    }

    /**
     * {@inheritDoc}
     */
    public function health(): array
    {
        return [
            'success' => true,
            'service' => 'fake',
            'message' => 'Fake service is always healthy',
        ];
    }
}
