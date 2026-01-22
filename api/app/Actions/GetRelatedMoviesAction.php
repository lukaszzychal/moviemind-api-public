<?php

namespace App\Actions;

use App\Enums\RelationshipType;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Models\MovieRelationship;
use App\Services\HateoasService;
use App\Services\SimilarMoviesService;
use Illuminate\Http\Request;

class GetRelatedMoviesAction
{
    public function __construct(
        private readonly SimilarMoviesService $similarMoviesService,
        private readonly HateoasService $hateoas
    ) {}

    public function handle(Movie $movie, Request $request): array
    {
        // Parse type filter: collection, similar, or all (default)
        $typeFilter = $request->query('type', 'all');
        $typeFilter = strtolower((string) $typeFilter);

        // Parse genre filters
        $genreSlugs = $this->parseGenreFilters($request);

        $collectionMovies = [];
        $similarMovies = [];

        // Collection relationships
        if ($typeFilter === 'all' || $typeFilter === 'collection') {
            $collectionMovies = $this->getCollectionMovies($movie, $genreSlugs);
        }

        // Similar movies
        if ($typeFilter === 'all' || $typeFilter === 'similar') {
            $similarMovies = $this->similarMoviesService->getSimilarMovies($movie, 10);

            // Filter similar movies by genre if filter is applied
            if (! empty($genreSlugs)) {
                $similarMovies = array_filter($similarMovies, function ($movieData) use ($genreSlugs) {
                    // Similar movies come from TMDb API, so we need to check if they exist in our DB
                    $relatedMovie = Movie::where('slug', $movieData['slug'] ?? null)->first();
                    if (! $relatedMovie) {
                        return false; // Skip movies not in our DB (can't check genres)
                    }

                    return $this->matchesGenreFilter($relatedMovie, $genreSlugs);
                });
            }
        }

        return [
            'collection' => $collectionMovies,
            'similar' => $similarMovies,
            'type_filter' => $typeFilter,
        ];
    }

    private function getCollectionMovies(Movie $movie, array $genreSlugs): array
    {
        $collectionTypes = [
            RelationshipType::SEQUEL->value,
            RelationshipType::PREQUEL->value,
            RelationshipType::SERIES->value,
            RelationshipType::SPINOFF->value,
            RelationshipType::REMAKE->value,
        ];

        $relatedMoviesCollection = $movie->getRelatedMovies($collectionTypes);

        if ($relatedMoviesCollection->isNotEmpty()) {
            $relatedMoviesCollection->loadMissing('genres');
        }

        return $relatedMoviesCollection
            ->filter(function (Movie $relatedMovie) use ($genreSlugs) {
                if (! $relatedMovie->relationLoaded('genres')) {
                    $relatedMovie->load('genres');
                }

                return $this->matchesGenreFilter($relatedMovie, $genreSlugs);
            })
            ->map(function (Movie $relatedMovie) use ($movie) {
                $relationship = MovieRelationship::where(function ($query) use ($movie, $relatedMovie) {
                    $query->where('movie_id', $movie->id)
                        ->where('related_movie_id', $relatedMovie->id);
                })->orWhere(function ($query) use ($movie, $relatedMovie) {
                    $query->where('movie_id', $relatedMovie->id)
                        ->where('related_movie_id', $movie->id);
                })->first();

                $resource = MovieResource::make($relatedMovie)->additional([
                    '_links' => $this->hateoas->movieLinks($relatedMovie),
                ]);

                $movieData = $resource->resolve();
                $movieData['relationship_type'] = $relationship?->relationship_type->value ?? null;
                $movieData['relationship_label'] = $relationship?->relationship_type->label() ?? null;
                $movieData['relationship_order'] = $relationship?->order;

                return $movieData;
            })->values()->toArray();
    }

    private function parseGenreFilters(Request $request): array
    {
        $genreSlugs = [];

        $singleGenre = $request->query('genre');
        if ($singleGenre !== null) {
            $genreSlugs[] = strtolower((string) $singleGenre);
        }

        $multipleGenres = $request->query('genres', []);
        if (is_array($multipleGenres)) {
            foreach ($multipleGenres as $genre) {
                if (is_string($genre) && ! empty($genre)) {
                    $genreSlugs[] = strtolower($genre);
                }
            }
        }

        return array_values(array_unique($genreSlugs));
    }

    private function matchesGenreFilter(Movie $movie, array $genreSlugs): bool
    {
        if (empty($genreSlugs)) {
            return true;
        }

        if (! $movie->relationLoaded('genres')) {
            $movie->load('genres');
        }

        $movieGenreSlugs = $movie->genres->pluck('slug')->map(fn ($s) => strtolower($s))->toArray();

        foreach ($genreSlugs as $slug) {
            if (! in_array($slug, $movieGenreSlugs, true)) {
                return false;
            }
        }

        return true;
    }
}
