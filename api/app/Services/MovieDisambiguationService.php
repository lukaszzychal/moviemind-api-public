<?php

namespace App\Services;

use App\Models\Movie;
use App\Repositories\MovieRepository;
use Illuminate\Support\Str;

class MovieDisambiguationService
{
    public function __construct(private readonly MovieRepository $movieRepository) {}

    public function determineMeta(Movie $movie, string $slug): ?array
    {
        $parsed = Movie::parseSlug($slug);

        if ($parsed['year'] !== null) {
            return null;
        }

        $allMovies = $this->movieRepository->findAllByTitleSlug(Str::slug($parsed['title']));

        if ($allMovies->count() <= 1) {
            return null;
        }

        return [
            'ambiguous' => true,
            'message' => 'Multiple movies found with this title. Showing most recent. Use slug with year (e.g., "bad-boys-1995") for specific version.',
            'alternatives' => $allMovies->map(function (Movie $movie) {
                return [
                    'slug' => $movie->slug,
                    'title' => $movie->title,
                    'release_year' => $movie->release_year,
                    'url' => url("/api/v1/movies/{$movie->slug}"),
                ];
            })->toArray(),
        ];
    }
}
