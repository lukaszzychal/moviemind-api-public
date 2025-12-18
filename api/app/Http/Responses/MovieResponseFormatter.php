<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Services\HateoasService;
use App\Services\MovieDisambiguationService;
use App\Support\MovieRetrievalResult;
use App\Support\SearchResult;
use Illuminate\Http\JsonResponse;

/**
 * Response Formatter for Movie API responses.
 * Formats different types of responses (success, error, disambiguation, etc.) into JSON.
 */
class MovieResponseFormatter
{
    public function __construct(
        private readonly HateoasService $hateoas,
        private readonly MovieDisambiguationService $movieDisambiguationService
    ) {}

    /**
     * Format successful movie retrieval response.
     */
    public function formatSuccess(
        Movie $movie,
        string $slug,
        ?MovieDescription $selectedDescription = null
    ): JsonResponse {
        $resource = MovieResource::make($movie)->additional([
            '_links' => $this->hateoas->movieLinks($movie),
        ]);

        if ($meta = $this->movieDisambiguationService->determineMeta($movie, $slug)) {
            $resource->additional(['_meta' => $meta]);
        }

        $data = $resource->resolve();

        // Add all descriptions to response
        if ($movie->relationLoaded('descriptions')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, MovieDescription> $descriptions */
            $descriptions = $movie->descriptions;
            $data['descriptions'] = $descriptions->map(function (MovieDescription $description) {
                return [
                    'id' => $description->id,
                    'locale' => $description->locale->value,
                    'text' => $description->text,
                    'context_tag' => $description->context_tag->value,
                    'origin' => $description->origin->value,
                    'ai_model' => $description->ai_model,
                    'created_at' => $description->created_at?->toISOString(),
                    'updated_at' => $description->updated_at?->toISOString(),
                ];
            })->values()->toArray();
        } else {
            // If descriptions are not loaded, load them
            $movie->load('descriptions');
            /** @var \Illuminate\Database\Eloquent\Collection<int, MovieDescription> $descriptions */
            $descriptions = $movie->descriptions;
            $data['descriptions'] = $descriptions->map(function (MovieDescription $description) {
                return [
                    'id' => $description->id,
                    'locale' => $description->locale->value,
                    'text' => $description->text,
                    'context_tag' => $description->context_tag->value,
                    'origin' => $description->origin->value,
                    'ai_model' => $description->ai_model,
                    'created_at' => $description->created_at?->toISOString(),
                    'updated_at' => $description->updated_at?->toISOString(),
                ];
            })->values()->toArray();
        }

        if ($selectedDescription !== null) {
            $data['selected_description'] = $selectedDescription->toArray();
        }

        return response()->json($data);
    }

    /**
     * Format error response.
     */
    public function formatError(string $errorMessage, int $statusCode, ?array $additionalData = null): JsonResponse
    {
        $response = ['error' => $errorMessage];

        if ($additionalData !== null) {
            $response = array_merge($response, $additionalData);
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Format description not found response.
     */
    public function formatDescriptionNotFound(): JsonResponse
    {
        return $this->formatError('Description not found for movie', 404);
    }

    /**
     * Format invalid slug response.
     */
    public function formatInvalidSlug(string $slug, array $validation): JsonResponse
    {
        return $this->formatError(
            'Invalid slug format',
            400,
            [
                'message' => $validation['reason'],
                'confidence' => $validation['confidence'],
                'slug' => $slug,
            ]
        );
    }

    /**
     * Format disambiguation response.
     */
    public function formatDisambiguation(string $slug, array $options): JsonResponse
    {
        return $this->formatError(
            'Multiple movies found',
            300,
            [
                'message' => 'Multiple movies match your search. Please select one from the options below:',
                'slug' => $slug,
                'options' => $options,
                'count' => count($options),
                'hint' => 'Use the slug from options to access specific movie (e.g., GET /api/v1/movies/{slug})',
            ]
        );
    }

    /**
     * Format generation queued response.
     */
    public function formatGenerationQueued(array $generationResult): JsonResponse
    {
        return response()->json($generationResult, 202);
    }

    /**
     * Format not found response.
     */
    public function formatNotFound(?string $customMessage = null): JsonResponse
    {
        $message = $customMessage ?? 'Movie not found';

        return $this->formatError($message, 404);
    }

    /**
     * Format response from MovieRetrievalResult.
     */
    public function formatFromResult(MovieRetrievalResult $result, string $slug): JsonResponse
    {
        if ($result->isCached()) {
            return response()->json($result->getData());
        }

        if ($result->isFound()) {
            return $this->formatSuccess(
                $result->getMovie(),
                $slug,
                $result->getSelectedDescription()
            );
        }

        if ($result->isDescriptionNotFound()) {
            return $this->formatDescriptionNotFound();
        }

        if ($result->isInvalidSlug()) {
            $data = $result->getAdditionalData();

            return $this->formatInvalidSlug($data['slug'] ?? $slug, $data);
        }

        if ($result->isDisambiguation()) {
            $data = $result->getAdditionalData();

            return $this->formatDisambiguation(
                $data['slug'] ?? $slug,
                $data['options'] ?? []
            );
        }

        if ($result->isGenerationQueued()) {
            return $this->formatGenerationQueued($result->getAdditionalData() ?? []);
        }

        return $this->formatNotFound($result->getErrorMessage());
    }

    /**
     * Format search result response.
     */
    public function formatSearchResult(SearchResult $searchResult): JsonResponse
    {
        $statusCode = $searchResult->getHttpStatusCode();

        if ($searchResult->isAmbiguous()) {
            return response()->json([
                'error' => 'Multiple movies found',
                'message' => 'Multiple movies match your search criteria. Please refine your search or select a specific movie.',
                'match_type' => $searchResult->matchType,
                'count' => $searchResult->total,
                'results' => $searchResult->results,
                'hint' => 'Use the slug from results to access specific movie (e.g., GET /api/v1/movies/{slug})',
            ], $statusCode);
        }

        if ($searchResult->isEmpty()) {
            return response()->json([
                'error' => 'No movies found',
                'message' => 'No movies match your search criteria.',
                'match_type' => $searchResult->matchType,
                'total' => $searchResult->total,
                'results' => [],
            ], $statusCode);
        }

        // For exact or partial match (200)
        return response()->json($searchResult->toArray(), $statusCode);
    }

    /**
     * Format list of movies response.
     */
    public function formatMovieList(array $movies): JsonResponse
    {
        return response()->json(['data' => $movies]);
    }

    /**
     * Format disambiguation selection error (movie not found in search results).
     */
    public function formatDisambiguationSelectionNotFound(): JsonResponse
    {
        return $this->formatError('Selected movie not found in search results', 404);
    }

    /**
     * Format refresh success response.
     *
     * @param  string  $slug  Movie slug
     * @param  string  $movieId  Movie ID (UUID)
     */
    public function formatRefreshSuccess(string $slug, string $movieId): JsonResponse
    {
        return response()->json([
            'message' => 'Movie data refreshed from TMDb',
            'slug' => $slug,
            'movie_id' => $movieId,
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Format refresh error - no snapshot found.
     */
    public function formatRefreshNoSnapshot(): JsonResponse
    {
        return $this->formatError('No TMDb snapshot found for this movie', 404);
    }

    /**
     * Format refresh error - failed to refresh.
     */
    public function formatRefreshFailed(): JsonResponse
    {
        return $this->formatError('Failed to refresh movie data from TMDb', 500);
    }
}
