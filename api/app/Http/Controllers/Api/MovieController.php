<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueueMovieGenerationAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Repositories\MovieRepository;
use App\Services\HateoasService;
use App\Services\MovieDisambiguationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

class MovieController extends Controller
{
    public function __construct(
        private readonly MovieRepository $movieRepository,
        private readonly HateoasService $hateoas,
        private readonly QueueMovieGenerationAction $queueMovieGenerationAction,
        private readonly MovieDisambiguationService $movieDisambiguationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $movies = $this->movieRepository->searchMovies($q, 50);
        $data = $movies->map(fn (Movie $movie) => $this->transformMovie($movie));

        return response()->json(['data' => $data]);
    }

    public function show(string $slug): JsonResponse
    {
        $movie = $this->movieRepository->findBySlugWithRelations($slug);
        if ($movie) {
            return $this->respondWithExistingMovie($movie, $slug);
        }

        if (! Feature::active('ai_description_generation')) {
            return response()->json(['error' => 'Movie not found'], 404);
        }

        $result = $this->queueMovieGenerationAction->handle($slug);

        return response()->json($result, 202);
    }

    private function respondWithExistingMovie(Movie $movie, string $slug): JsonResponse
    {
        return response()->json($this->transformMovie($movie, $slug));
    }

    private function transformMovie(Movie $movie, ?string $slug = null): array
    {
        $resource = MovieResource::make($movie)->additional([
            '_links' => $this->hateoas->movieLinks($movie),
        ]);

        if ($slug !== null) {
            if ($meta = $this->movieDisambiguationService->determineMeta($movie, $slug)) {
                $resource->additional(['_meta' => $meta]);
            }
        }

        return $resource->resolve();
    }
}
