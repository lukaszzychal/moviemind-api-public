<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use App\Models\MovieLocale;
use App\Repositories\MovieLocaleRepository;

/**
 * Service for managing movie locale metadata.
 * Handles retrieval of localized data with fallback to en-US.
 */
class MovieLocaleService
{
    private const FALLBACK_LOCALE = 'en-US';

    public function __construct(
        private readonly MovieLocaleRepository $repository
    ) {}

    /**
     * Get localized metadata for a movie with fallback to en-US.
     *
     * @param  string  $locale  Requested locale (e.g., 'pl-PL', 'en-US')
     * @return MovieLocale|null Localized metadata or null if no locale exists
     */
    public function getLocalizedMetadata(Movie $movie, string $locale): ?MovieLocale
    {
        // Try requested locale first
        $movieLocale = $this->repository->findByMovieIdAndLocale($movie->id, $locale);
        if ($movieLocale) {
            return $movieLocale;
        }

        // Fallback to en-US if requested locale is different
        if ($locale !== self::FALLBACK_LOCALE) {
            $fallbackLocale = $this->repository->findByMovieIdAndLocale($movie->id, self::FALLBACK_LOCALE);
            if ($fallbackLocale) {
                return $fallbackLocale;
            }
        }

        return null;
    }

    /**
     * Get localized title with fallback to en-US.
     *
     * @param  string  $locale  Requested locale (e.g., 'pl-PL', 'en-US')
     * @return string|null Localized title or null if no locale exists
     */
    public function getLocalizedTitle(Movie $movie, string $locale): ?string
    {
        $movieLocale = $this->getLocalizedMetadata($movie, $locale);

        return $movieLocale?->title_localized;
    }

    /**
     * Get localized director with fallback to en-US.
     *
     * @param  string  $locale  Requested locale (e.g., 'pl-PL', 'en-US')
     * @return string|null Localized director or null if no locale exists
     */
    public function getLocalizedDirector(Movie $movie, string $locale): ?string
    {
        $movieLocale = $this->getLocalizedMetadata($movie, $locale);

        return $movieLocale?->director_localized;
    }

    /**
     * Ensure a locale exists for a movie. Creates it if it doesn't exist.
     *
     * @param  string  $locale  Locale code (e.g., 'pl-PL', 'en-US')
     * @return MovieLocale The locale (existing or newly created)
     */
    public function ensureLocaleExists(Movie $movie, string $locale): MovieLocale
    {
        $existing = $this->repository->findByMovieIdAndLocale($movie->id, $locale);

        if ($existing) {
            return $existing;
        }

        return $this->repository->create([
            'movie_id' => $movie->id,
            'locale' => $locale,
        ]);
    }
}
