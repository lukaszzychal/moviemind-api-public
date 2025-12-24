<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TvShow;
use Illuminate\Support\Collection;

class TvShowRepository
{
    public function searchTvShows(?string $query, int $limit = 50): Collection
    {
        return TvShow::query()
            ->when($query, function ($builder) use ($query) {
                $builder->whereRaw('LOWER(title) LIKE LOWER(?)', ["%$query%"])
                    ->orWhereJsonContains('genres', $query)
                    ->orWhere('show_type', 'LIKE', "%{$query}%");
            })
            ->with(['defaultDescription', 'people'])
            ->withCount('descriptions')
            ->limit($limit)
            ->get();
    }

    public function findBySlugWithRelations(string $slug): ?TvShow
    {
        // Try exact match first
        $tvShow = TvShow::with(['descriptions', 'defaultDescription', 'people'])
            ->withCount('descriptions')
            ->where('slug', $slug)
            ->first();

        if ($tvShow) {
            return $tvShow;
        }

        // If slug doesn't contain year, try to find by title only
        // This handles backwards compatibility with slugs without year
        $parsed = TvShow::parseSlug($slug);
        if ($parsed['year'] === null) {
            // Try to match by title slug (without year)
            // Note: This may return the first match if multiple tv shows share the same title
            $titleSlug = \Illuminate\Support\Str::slug($parsed['title']);

            return TvShow::with(['descriptions', 'defaultDescription', 'people'])
                ->withCount('descriptions')
                ->whereRaw('slug LIKE ?', ["{$titleSlug}%"])
                ->orderBy('first_air_date', 'desc') // Return most recent by default
                ->first();
        }

        return null;
    }

    /**
     * Find all tv shows with the same title (different years).
     * Useful for disambiguation when multiple tv shows share a title.
     * Searches by title slug in both slug field and title field to find all matches.
     */
    public function findAllByTitleSlug(string $baseSlug): Collection
    {
        // Search by slug containing the base slug
        // Also search by title containing the base slug
        $titleSlugPattern = '%'.str_replace('-', '%', $baseSlug).'%';
        $slugPattern = '%'.$baseSlug.'%';

        return TvShow::with(['descriptions', 'defaultDescription'])
            ->withCount('descriptions')
            ->where(function ($query) use ($slugPattern, $titleSlugPattern) {
                $query->whereRaw('slug LIKE ?', [$slugPattern])
                    ->orWhereRaw('LOWER(title) LIKE LOWER(?)', [$titleSlugPattern]);
            })
            ->orderBy('first_air_date', 'desc')
            ->get();
    }

    /**
     * Find tv show by slug for use in Jobs.
     * Handles ambiguous slugs (without year) by returning the most recent tv show.
     * Also handles cases where request slug doesn't have year but database slug does.
     * Uses lighter relations than findBySlugWithRelations (only 'descriptions').
     *
     * @param  string  $slug  The slug to search for
     * @param  string|null  $existingId  Optional existing tv show ID (UUID) to check first
     */
    public function findBySlugForJob(string $slug, ?string $existingId = null): ?TvShow
    {
        // If existing ID is provided, try to find by ID first
        if ($existingId !== null) {
            $tvShow = TvShow::with('descriptions')->find($existingId);
            if ($tvShow) {
                return $tvShow;
            }
        }

        // Try exact match first
        $tvShow = TvShow::with('descriptions')->where('slug', $slug)->first();
        if ($tvShow) {
            return $tvShow;
        }

        // Parse slug to extract title and year
        $parsed = TvShow::parseSlug($slug);
        $titleSlug = \Illuminate\Support\Str::slug($parsed['title']);

        // If slug from request doesn't contain year, try to find by title only
        // This handles ambiguous slugs and cases where job generated slug with year
        // but request slug doesn't have year
        if ($parsed['year'] === null) {
            // Return most recent tv show with matching title slug
            // This will find tv shows like "the-tonight-show-1954" even if request slug is "the-tonight-show"
            return TvShow::with('descriptions')
                ->whereRaw('slug LIKE ?', ["{$titleSlug}%"])
                ->orderBy('first_air_date', 'desc')
                ->first();
        }

        // Slug from request HAS year - check if tv show exists with same title + year
        // This handles cases where slug format differs (e.g., "the-tonight-show" vs "the-tonight-show-1954")
        // but represents the same tv show
        $year = $parsed['year'];
        // Check if tv show exists with same title and year (even if slug format differs)
        $tvShow = TvShow::with('descriptions')
            ->whereRaw('slug LIKE ?', ["{$titleSlug}-{$year}%"])
            ->whereYear('first_air_date', $year)
            ->first();
        if ($tvShow) {
            return $tvShow;
        }

        return null;
    }

    /**
     * Find multiple tv shows by slugs.
     * Returns tv shows in the same order as requested slugs.
     * Handles duplicate slugs by deduplicating them.
     *
     * @param  array<int, string>  $slugs
     * @param  array<int, string>  $include  Relations to include (descriptions, people)
     * @return Collection<int, TvShow>
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

        // Fetch tv shows
        $tvShows = TvShow::with($relations)
            ->withCount('descriptions')
            ->whereIn('slug', $uniqueSlugs)
            ->get();

        // Create a map for quick lookup
        $tvShowsBySlug = $tvShows->keyBy('slug');

        // Return tv shows in the same order as requested slugs
        $orderedTvShows = collect();
        foreach ($uniqueSlugs as $slug) {
            if ($tvShowsBySlug->has($slug)) {
                $orderedTvShows->push($tvShowsBySlug->get($slug));
            }
        }

        return $orderedTvShows;
    }
}
