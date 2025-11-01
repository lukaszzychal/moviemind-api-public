<?php

namespace App\Http\Controllers\Api;

use App\Events\MovieGenerationRequested;
use App\Http\Controllers\Controller;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Repositories\MovieRepository;
use App\Services\HateoasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

class MovieController extends Controller
{
    public function __construct(
        private readonly MovieRepository $movieRepository,
        private readonly HateoasService $hateoas
    ) {}

    public function index(Request $request)
    {
        $q = $request->query('q');
        $movies = $this->movieRepository->searchMovies($q, 50);

        $data = $movies->map(function (Movie $m) use ($request) {
            $resource = new MovieResource($m);
            $resource->additional(['_links' => $this->hateoas->movieLinks($m)]);

            return $resource->resolve($request);
        });

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, string $slug)
    {
        $movie = $this->movieRepository->findBySlugWithRelations($slug);
        if ($movie) {
            // Check if slug without year matched multiple movies
            $parsed = Movie::parseSlug($slug);
            if ($parsed['year'] === null) {
                $allMovies = $this->movieRepository->findAllByTitleSlug(Str::slug($parsed['title']));
                if ($allMovies->count() > 1) {
                    // Multiple movies with same title - include disambiguation info
                    $resource = new MovieResource($movie);
                    $resource->additional(['_links' => $this->hateoas->movieLinks($movie)]);
                    $payload = $resource->resolve($request);
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

            $resource = new MovieResource($movie);
            $resource->additional(['_links' => $this->hateoas->movieLinks($movie)]);

            // Use response()->json() to avoid data wrapper from JsonResource::toResponse()
            return response()->json($resource->resolve($request));
        }

        if (! Feature::active('ai_description_generation')) {
            return response()->json(['error' => 'Movie not found'], 404);
        }

        $jobId = (string) Str::uuid();

        // Set initial cache status
        Cache::put("ai_job:{$jobId}", [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'entity' => 'MOVIE',
            'slug' => $slug,
        ], now()->addMinutes(15));

        // Emit event - Listener will queue the Job
        event(new MovieGenerationRequested($slug, $jobId));

        return response()->json([
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Generation queued for movie by slug',
            'slug' => $slug,
        ], 202);
    }
}
