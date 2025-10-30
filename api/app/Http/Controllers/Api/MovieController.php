<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Repositories\MovieRepository;
use App\Services\HateoasService;
use App\Services\AiServiceInterface;
use Laravel\Pennant\Feature;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function __construct(
        private readonly MovieRepository $movieRepository,
        private readonly HateoasService $hateoas,
        private readonly AiServiceInterface $ai
    ) {}

    public function index(Request $request)
    {
        $q = $request->query('q');
        $movies = $this->movieRepository->searchMovies($q, 50);

        $data = $movies->map(function (Movie $m) {
            $arr = $m->toArray();
            $arr['_links'] = $this->hateoas->movieLinks($m);
            return $arr;
        });

        return response()->json(['data' => $data]);
    }

    public function show(string $slug)
    {
        $movie = $this->movieRepository->findBySlugWithRelations($slug);
        if ($movie) {
            // Check if slug without year matched multiple movies
            $parsed = Movie::parseSlug($slug);
            if ($parsed['year'] === null) {
                $allMovies = $this->movieRepository->findAllByTitleSlug(Str::slug($parsed['title']));
                if ($allMovies->count() > 1) {
                    // Multiple movies with same title - include disambiguation info
                    $payload = $movie->toArray();
                    $payload['_links'] = $this->hateoas->movieLinks($movie);
                    $payload['_meta'] = [
                        'ambiguous' => true,
                        'message' => 'Multiple movies found with this title. Showing most recent. Use slug with year (e.g., "bad-boys-1995") for specific version.',
                        'alternatives' => $allMovies->map(function (Movie $m) {
                            return [
                                'slug' => $m->slug,
                                'title' => $m->title,
                                'release_year' => $m->release_year,
                                'url' => url("/api/v1/movies/{$m->slug}"),
                            ];
                        })->toArray(),
                    ];
                    return response()->json($payload);
                }
            }
            
            $payload = $movie->toArray();
            $payload['_links'] = $this->hateoas->movieLinks($movie);
            return response()->json($payload);
        }

        if (! Feature::active('ai_description_generation')) {
            return response()->json(['error' => 'Movie not found'], 404);
        }

        $jobId = (string) Str::uuid();
        $this->ai->queueMovieGeneration($slug, $jobId);
        return response()->json([
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Generation queued for movie by slug',
            'slug' => $slug,
        ], 202);
    }
}


