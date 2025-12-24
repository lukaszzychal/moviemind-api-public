<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TvShow;
use App\Repositories\TvShowRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for creating TV shows from TMDb data (without description).
 * Creates TV show with metadata only (title, year, etc.).
 * Description should be generated asynchronously via queue job.
 */
class TmdbTvShowCreationService
{
    public function __construct(
        private readonly TvShowRepository $tvShowRepository
    ) {}

    /**
     * Create TV show from TMDb data (metadata only, no description).
     * Description should be generated asynchronously via queue job.
     *
     * @param  array{name: string, first_air_date?: string|null, overview?: string|null, id: int}  $tmdbData
     * @param  string  $requestSlug  Original slug from request
     * @return TvShow|null Returns null if TV show already exists
     */
    public function createFromTmdb(array $tmdbData, string $requestSlug): ?TvShow
    {
        $title = $tmdbData['name'];
        $firstAirDate = $tmdbData['first_air_date'] ?? null;
        $firstAirYear = ! empty($firstAirDate)
            ? (int) substr($firstAirDate, 0, 4)
            : null;
        $overview = $tmdbData['overview'] ?? '';
        $tmdbId = $tmdbData['id'];

        // Generate slug from TMDb data
        $generatedSlug = TvShow::generateSlug($title, $firstAirYear);

        // Check if TV show already exists by generated slug
        $existing = $this->tvShowRepository->findBySlugForJob($generatedSlug);
        if ($existing) {
            Log::info('TV show already exists by generated slug, returning existing', [
                'request_slug' => $requestSlug,
                'generated_slug' => $generatedSlug,
                'tv_show_id' => $existing->id,
                'tmdb_id' => $tmdbId,
            ]);

            return $existing;
        }

        // Check if TV show exists by title + year (even if slug differs)
        $existingByTitleYear = TvShow::where('title', $title)
            ->where('first_air_date', $firstAirDate)
            ->first();

        if ($existingByTitleYear) {
            Log::info('TV show already exists by title+year, returning existing', [
                'request_slug' => $requestSlug,
                'generated_slug' => $generatedSlug,
                'existing_tv_show_id' => $existingByTitleYear->id,
                'tmdb_id' => $tmdbId,
            ]);

            return $existingByTitleYear;
        }

        // Create TV show from TMDb data (metadata only, no description)
        // Description will be generated asynchronously via queue job
        $tvShow = TvShow::create([
            'title' => $title,
            'slug' => $generatedSlug,
            'first_air_date' => $firstAirDate,
            'genres' => [], // Genres can be added later if needed
        ]);

        // Save TMDb snapshot
        try {
            /** @var TmdbVerificationService $tmdbService */
            $tmdbService = app(TmdbVerificationService::class);
            $tmdbService->saveSnapshot('TV_SHOW', $tvShow->id, $tmdbId, 'tv', $tmdbData);
        } catch (\Throwable $e) {
            Log::warning('Failed to save TMDb snapshot after TV show creation', [
                'tv_show_id' => $tvShow->id,
                'tmdb_id' => $tmdbId,
                'error' => $e->getMessage(),
            ]);
        }

        // Invalidate TV show search cache when new TV show is created
        $this->invalidateTvShowSearchCache();

        Log::info('TV show created from TMDb data (metadata only, description will be generated asynchronously)', [
            'request_slug' => $requestSlug,
            'generated_slug' => $generatedSlug,
            'tv_show_id' => $tvShow->id,
            'tmdb_id' => $tmdbId,
            'title' => $title,
            'first_air_year' => $firstAirYear,
        ]);

        return $tvShow;
    }

    /**
     * Invalidate TV show search cache when a new TV show is created.
     */
    private function invalidateTvShowSearchCache(): void
    {
        try {
            Cache::tags(['tv_show_search'])->flush();
            Log::debug('TvShowSearchService: invalidated tagged cache after TV show creation');
        } catch (\BadMethodCallException $e) {
            Log::debug('TvShowSearchService: tagged cache not supported, cache will expire naturally', [
                'driver' => config('cache.default'),
            ]);
        }
    }
}
