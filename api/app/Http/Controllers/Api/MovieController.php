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
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;

class MovieController extends Controller
{
    private const CACHE_TTL_SECONDS = 3600;

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
        if ($cached = Cache::get($this->cacheKey($slug))) {
            return response()->json($cached);
        }

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
        $payload = $this->transformMovie($movie, $slug);
        Cache::put($this->cacheKey($slug), $payload, now()->addSeconds(self::CACHE_TTL_SECONDS));

        return response()->json($payload);
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

    private function cacheKey(string $slug): string
    {
        return 'movie:'.$slug;
    }
}
