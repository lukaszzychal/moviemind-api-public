<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TvSeries;
use App\Repositories\TvSeriesRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for creating TV series from TMDb data (without description).
 * Creates TV series with metadata only (title, year, etc.).
 * Description should be generated asynchronously via queue job.
 */
class TmdbTvSeriesCreationService
{
    public function __construct(
        private readonly TvSeriesRepository $tvSeriesRepository
    ) {}

    /**
     * Create TV series from TMDb data (metadata only, no description).
     * Description should be generated asynchronously via queue job.
     *
     * @param  array{name: string, first_air_date?: string|null, overview?: string|null, id: int}  $tmdbData
     * @param  string  $requestSlug  Original slug from request
     * @return TvSeries|null Returns null if TV series already exists
     */
    public function createFromTmdb(array $tmdbData, string $requestSlug): ?TvSeries
    {
        $title = $tmdbData['name'];
        $firstAirDate = $tmdbData['first_air_date'] ?? null;
        $firstAirYear = ! empty($firstAirDate)
            ? (int) substr($firstAirDate, 0, 4)
            : null;
        $overview = $tmdbData['overview'] ?? '';
        $tmdbId = $tmdbData['id'];

        // Generate slug from TMDb data
        $generatedSlug = TvSeries::generateSlug($title, $firstAirYear);

        // Check if TV series already exists by generated slug
        $existing = $this->tvSeriesRepository->findBySlugForJob($generatedSlug);
        if ($existing) {
            Log::info('TV series already exists by generated slug, returning existing', [
                'request_slug' => $requestSlug,
                'generated_slug' => $generatedSlug,
                'tv_series_id' => $existing->id,
                'tmdb_id' => $tmdbId,
            ]);

            return $existing;
        }

        // Check if TV series exists by title + year (even if slug differs)
        $existingByTitleYear = TvSeries::where('title', $title)
            ->where('first_air_date', $firstAirDate)
            ->first();

        if ($existingByTitleYear) {
            Log::info('TV series already exists by title+year, returning existing', [
                'request_slug' => $requestSlug,
                'generated_slug' => $generatedSlug,
                'existing_tv_series_id' => $existingByTitleYear->id,
                'tmdb_id' => $tmdbId,
            ]);

            return $existingByTitleYear;
        }

        // Create TV series from TMDb data (metadata only, no description)
        // Description will be generated asynchronously via queue job
        $tvSeries = TvSeries::create([
            'title' => $title,
            'slug' => $generatedSlug,
            'first_air_date' => $firstAirDate,
            'genres' => [], // Genres can be added later if needed
        ]);

        // Save TMDb snapshot
        try {
            /** @var TmdbVerificationService $tmdbService */
            $tmdbService = app(TmdbVerificationService::class);
            $tmdbService->saveSnapshot('TV_SERIES', $tvSeries->id, $tmdbId, 'tv', $tmdbData);
        } catch (\Throwable $e) {
            Log::warning('Failed to save TMDb snapshot after TV series creation', [
                'tv_series_id' => $tvSeries->id,
                'tmdb_id' => $tmdbId,
                'error' => $e->getMessage(),
            ]);
        }

        // Invalidate TV series search cache when new TV series is created
        $this->invalidateTvSeriesSearchCache();

        Log::info('TV series created from TMDb data (metadata only, description will be generated asynchronously)', [
            'request_slug' => $requestSlug,
            'generated_slug' => $generatedSlug,
            'tv_series_id' => $tvSeries->id,
            'tmdb_id' => $tmdbId,
            'title' => $title,
            'first_air_year' => $firstAirYear,
        ]);

        return $tvSeries;
    }

    /**
     * Invalidate TV series search cache when a new TV series is created.
     */
    private function invalidateTvSeriesSearchCache(): void
    {
        try {
            Cache::tags(['tv_series_search'])->flush();
            Log::debug('TvSeriesSearchService: invalidated tagged cache after TV series creation');
        } catch (\BadMethodCallException $e) {
            Log::debug('TvSeriesSearchService: tagged cache not supported, cache will expire naturally', [
                'driver' => config('cache.default'),
            ]);
        }
    }
}
