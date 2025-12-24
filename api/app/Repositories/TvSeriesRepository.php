<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TvSeries;
use Illuminate\Support\Collection;

class TvSeriesRepository
{
    public function searchTvSeries(?string $query, int $limit = 50): Collection
    {
        return TvSeries::query()
            ->when($query, function ($builder) use ($query) {
                $builder->whereRaw('LOWER(title) LIKE LOWER(?)', ["%$query%"])
                    ->orWhereJsonContains('genres', $query);
            })
            ->with(['defaultDescription', 'people'])
            ->withCount('descriptions')
            ->limit($limit)
            ->get();
    }

    public function findBySlugWithRelations(string $slug): ?TvSeries
    {
        // Try exact match first
        $tvSeries = TvSeries::with(['descriptions', 'defaultDescription', 'people'])
            ->withCount('descriptions')
            ->where('slug', $slug)
            ->first();

        if ($tvSeries) {
            return $tvSeries;
        }

        // If slug doesn't contain year, try to find by title only
        // This handles backwards compatibility with slugs without year
        $parsed = TvSeries::parseSlug($slug);
        if ($parsed['year'] === null) {
            // Try to match by title slug (without year)
            // Note: This may return the first match if multiple tv series share the same title
            $titleSlug = \Illuminate\Support\Str::slug($parsed['title']);

            return TvSeries::with(['descriptions', 'defaultDescription', 'people'])
                ->withCount('descriptions')
                ->whereRaw('slug LIKE ?', ["{$titleSlug}%"])
                ->orderBy('first_air_date', 'desc') // Return most recent by default
                ->first();
        }

        return null;
    }

    /**
     * Find all tv series with the same title (different years).
     * Useful for disambiguation when multiple tv series share a title.
     * Searches by title slug in both slug field and title field to find all matches.
     */
    public function findAllByTitleSlug(string $baseSlug): Collection
    {
        // Search by slug containing the base slug
        // Also search by title containing the base slug
        $titleSlugPattern = '%'.str_replace('-', '%', $baseSlug).'%';
        $slugPattern = '%'.$baseSlug.'%';

        return TvSeries::with(['descriptions', 'defaultDescription'])
            ->withCount('descriptions')
            ->where(function ($query) use ($slugPattern, $titleSlugPattern) {
                $query->whereRaw('slug LIKE ?', [$slugPattern])
                    ->orWhereRaw('LOWER(title) LIKE LOWER(?)', [$titleSlugPattern]);
            })
            ->orderBy('first_air_date', 'desc')
            ->get();
    }

    /**
     * Find tv series by slug for use in Jobs.
     * Handles ambiguous slugs (without year) by returning the most recent tv series.
     * Also handles cases where request slug doesn't have year but database slug does.
     * Uses lighter relations than findBySlugWithRelations (only 'descriptions').
     *
     * @param  string  $slug  The slug to search for
     * @param  string|null  $existingId  Optional existing tv series ID (UUID) to check first
     */
    public function findBySlugForJob(string $slug, ?string $existingId = null): ?TvSeries
    {
        // If existing ID is provided, try to find by ID first
        if ($existingId !== null) {
            $tvSeries = TvSeries::with('descriptions')->find($existingId);
            if ($tvSeries) {
                return $tvSeries;
            }
        }

        // Try exact match first
        $tvSeries = TvSeries::with('descriptions')->where('slug', $slug)->first();
        if ($tvSeries) {
            return $tvSeries;
        }

        // Parse slug to extract title and year
        $parsed = TvSeries::parseSlug($slug);
        $titleSlug = \Illuminate\Support\Str::slug($parsed['title']);

        // If slug from request doesn't contain year, try to find by title only
        // This handles ambiguous slugs and cases where job generated slug with year
        // but request slug doesn't have year
        if ($parsed['year'] === null) {
            // Return most recent tv series with matching title slug
            // This will find tv series like "breaking-bad-2008" even if request slug is "breaking-bad"
            return TvSeries::with('descriptions')
                ->whereRaw('slug LIKE ?', ["{$titleSlug}%"])
                ->orderBy('first_air_date', 'desc')
                ->first();
        }

        // Slug from request HAS year - check if tv series exists with same title + year
        // This handles cases where slug format differs (e.g., "breaking-bad" vs "breaking-bad-2008")
        // but represents the same tv series
        $year = $parsed['year'];
        // Check if tv series exists with same title and year (even if slug format differs)
        $tvSeries = TvSeries::with('descriptions')
            ->whereRaw('slug LIKE ?', ["{$titleSlug}-{$year}%"])
            ->whereYear('first_air_date', $year)
            ->first();
        if ($tvSeries) {
            return $tvSeries;
        }

        return null;
    }

    /**
     * Find multiple tv series by slugs.
     * Returns tv series in the same order as requested slugs.
     * Handles duplicate slugs by deduplicating them.
     *
     * @param  array<int, string>  $slugs
     * @param  array<int, string>  $include  Relations to include (descriptions, people)
     * @return Collection<int, TvSeries>
     */
    public function findBySlugs(array $slugs, array $include = []): Collection
    {
        // Deduplicate slugs while preserving order
        $uniqueSlugs = array_values(array_unique($slugs));

        if (empty($uniqueSlugs)) {
            return collect();
        }

        // Build relations array based on include parameter
        $relations = ['defaultDescription'];
        if (in_array('descriptions', $include, true)) {
            $relations[] = 'descriptions';
        }
        if (in_array('people', $include, true)) {
            $relations[] = 'people';
        }

        // Fetch tv series
        $tvSeries = TvSeries::with($relations)
            ->withCount('descriptions')
            ->whereIn('slug', $uniqueSlugs)
            ->get();

        // Create a map for quick lookup
        $tvSeriesBySlug = $tvSeries->keyBy('slug');

        // Return tv series in the same order as requested slugs
        $orderedTvSeries = collect();
        foreach ($uniqueSlugs as $slug) {
            if ($tvSeriesBySlug->has($slug)) {
                $orderedTvSeries->push($tvSeriesBySlug->get($slug));
            }
        }

        return $orderedTvSeries;
    }
}
