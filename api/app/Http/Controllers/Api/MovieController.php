<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueueMovieGenerationAction;
use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Models\MovieDescription;
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

    public function show(Request $request, string $slug): JsonResponse
    {
        $descriptionId = $this->normalizeDescriptionId($request->query('description_id'));
        if ($descriptionId === false) {
            return response()->json([
                'error' => 'Invalid description_id parameter',
            ], 422);
        }

        $cacheKey = $this->cacheKey($slug, $descriptionId);
        if ($cached = Cache::get($cacheKey)) {
            return response()->json($cached);
        }
        $movie = $this->movieRepository->findBySlugWithRelations($slug);
        if ($movie) {
            $selectedDescription = null;
            if ($descriptionId !== null) {
                $candidate = $movie->descriptions->firstWhere('id', $descriptionId);
                if ($candidate instanceof MovieDescription) {
                    $selectedDescription = $candidate;
                }

                if ($selectedDescription === null) {
                    return response()->json(['error' => 'Description not found for movie'], 404);
                }
            }

            return $this->respondWithExistingMovie($movie, $slug, $selectedDescription, $cacheKey);
        }

        if (! Feature::active('ai_description_generation')) {
            return response()->json(['error' => 'Movie not found'], 404);
        }

        $result = $this->queueMovieGenerationAction->handle($slug, locale: Locale::EN_US->value);
        // $result = [
        //     'job_id' => '123',
        //     'status' => 'PENDING',
        //     'slug' => $slug,
        //     'locale' => Locale::EN_US->value,
        // ];

        return response()->json($result, 202);
    }

    private function respondWithExistingMovie(
        Movie $movie,
        string $slug,
        ?MovieDescription $selectedDescription,
        string $cacheKey
    ): JsonResponse {
        $payload = $this->transformMovie($movie, $slug, $selectedDescription);
        Cache::put($cacheKey, $payload, now()->addSeconds(self::CACHE_TTL_SECONDS));

        return response()->json($payload);
    }

    private function transformMovie(Movie $movie, ?string $slug = null, ?MovieDescription $selectedDescription = null): array
    {
        $resource = MovieResource::make($movie)->additional([
            '_links' => $this->hateoas->movieLinks($movie),
        ]);

        if ($slug !== null) {
            if ($meta = $this->movieDisambiguationService->determineMeta($movie, $slug)) {
                $resource->additional(['_meta' => $meta]);
            }
        }

        $data = $resource->resolve();

        if ($selectedDescription) {
            $data['selected_description'] = $selectedDescription->toArray();
        }

        return $data;
    }

    private function cacheKey(string $slug, ?int $descriptionId = null): string
    {
        $suffix = $descriptionId !== null ? 'desc:'.$descriptionId : 'desc:default';

        return 'movie:'.$slug.':'.$suffix;
    }

    private function normalizeDescriptionId(mixed $descriptionId): null|int|false
    {
        if ($descriptionId === null || $descriptionId === '') {
            return null;
        }

        if (filter_var($descriptionId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return false;
        }

        return (int) $descriptionId;
    }
}
