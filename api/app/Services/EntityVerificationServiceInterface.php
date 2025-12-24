<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Interface for entity verification services (TMDb, etc.).
 */
interface EntityVerificationServiceInterface
{
    /**
     * Verify if movie exists in external database.
     *
     * @return array{title: string, release_date: string, overview: string, id: int, director?: string}|null
     */
    public function verifyMovie(string $slug): ?array;

    /**
     * Search for movies in external database (returns multiple results for disambiguation).
     *
     * @return array<int, array{title: string, release_date: string, overview: string, id: int, director?: string}>
     */
    public function searchMovies(string $slug, int $limit = 5): array;

    /**
     * Verify if person exists in external database.
     *
     * @return array{name: string, birthday: string, place_of_birth: string, id: int, biography?: string}|null
     */
    public function verifyPerson(string $slug): ?array;

    /**
     * Search for people in external database (returns multiple results for disambiguation).
     *
     * @return array<int, array{name: string, birthday?: string, place_of_birth?: string, id: int, biography?: string}>
     */
    public function searchPeople(string $slug, int $limit = 5): array;

    /**
     * Verify if TV series exists in external database.
     *
     * @return array{name: string, first_air_date: string, overview: string, id: int}|null
     */
    public function verifyTvSeries(string $slug): ?array;

    /**
     * Search for TV series in external database (returns multiple results for disambiguation).
     *
     * @return array<int, array{name: string, first_air_date: string, overview: string, id: int}>
     */
    public function searchTvSeries(string $slug, int $limit = 5): array;

    /**
     * Verify if TV show exists in external database.
     *
     * @return array{name: string, first_air_date: string, overview: string, id: int}|null
     */
    public function verifyTvShow(string $slug): ?array;

    /**
     * Search for TV shows in external database (returns multiple results for disambiguation).
     *
     * @return array<int, array{name: string, first_air_date: string, overview: string, id: int}>
     */
    public function searchTvShows(string $slug, int $limit = 5): array;

    /**
     * Perform a lightweight health check against the external API.
     *
     * @return array{success: bool, service: string, message?: string, status?: int, error?: string}
     */
    public function health(): array;
}
