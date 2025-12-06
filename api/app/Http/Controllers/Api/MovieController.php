<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueueMovieGenerationAction;
use App\Enums\Locale;
use App\Helpers\SlugValidator;
use App\Http\Controllers\Controller;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Repositories\MovieRepository;
use App\Services\EntityVerificationServiceInterface;
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
        private readonly MovieDisambiguationService $movieDisambiguationService,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService
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

        // Validate slug format and check for prompt injection
        $validation = SlugValidator::validateMovieSlug($slug);
        if (! $validation['valid']) {
            return response()->json([
                'error' => 'Invalid slug format',
                'message' => $validation['reason'],
                'confidence' => $validation['confidence'],
                'slug' => $slug,
            ], 400);
        }

        // Check for disambiguation request
        $tmdbId = $request->query('tmdb_id');
        if ($tmdbId !== null) {
            return $this->handleDisambiguationSelection($slug, (int) $tmdbId);
        }

        // Verify movie exists in TMDb before queueing job (if feature flag enabled)
        $tmdbData = $this->tmdbVerificationService->verifyMovie($slug);
        if (! $tmdbData) {
            // If TMDb verification is disabled (feature flag off), allow generation without TMDb data
            if (! Feature::active('tmdb_verification')) {
                $result = $this->queueMovieGenerationAction->handle(
                    $slug,
                    locale: Locale::EN_US->value,
                    tmdbData: null
                );

                return response()->json($result, 202);
            }

            // Check if there are multiple matches (disambiguation needed)
            $searchResults = $this->tmdbVerificationService->searchMovies($slug, 5);
            if (count($searchResults) > 1) {
                return $this->respondWithDisambiguation($slug, $searchResults);
            }

            return response()->json(['error' => 'Movie not found'], 404);
        }

        $result = $this->queueMovieGenerationAction->handle(
            $slug,
            locale: Locale::EN_US->value,
            tmdbData: $tmdbData
        );

        return response()->json($result, 202);
    }

    /**
     * Handle disambiguation selection when user chooses specific movie from TMDb results.
     */
    private function handleDisambiguationSelection(string $slug, int $tmdbId): JsonResponse
    {
        // Search for movies and find the one matching tmdb_id
        $searchResults = $this->tmdbVerificationService->searchMovies($slug, 10);
        $selectedMovie = null;

        foreach ($searchResults as $result) {
            if ($result['id'] === $tmdbId) {
                $selectedMovie = $result;
                break;
            }
        }

        if (! $selectedMovie) {
            return response()->json(['error' => 'Selected movie not found in search results'], 404);
        }

        $result = $this->queueMovieGenerationAction->handle(
            $slug,
            locale: Locale::EN_US->value,
            tmdbData: $selectedMovie
        );

        return response()->json($result, 202);
    }

    /**
     * Respond with disambiguation options when multiple movies match the slug.
     */
    private function respondWithDisambiguation(string $slug, array $searchResults): JsonResponse
    {
        $options = array_map(function ($result) use ($slug) {
            $year = ! empty($result['release_date']) ? substr($result['release_date'], 0, 4) : null;
            $director = $result['director'] ?? null;

            return [
                'tmdb_id' => $result['id'],
                'title' => $result['title'],
                'release_year' => $year,
                'director' => $director,
                'overview' => substr($result['overview'] ?? '', 0, 200).(strlen($result['overview'] ?? '') > 200 ? '...' : ''),
                'select_url' => url("/api/v1/movies/{$slug}?tmdb_id={$result['id']}"),
            ];
        }, $searchResults);

        return response()->json([
            'error' => 'Multiple movies found',
            'message' => 'Multiple movies match this slug. Please select one:',
            'slug' => $slug,
            'options' => $options,
            'count' => count($options),
        ], 300); // 300 Multiple Choices
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
