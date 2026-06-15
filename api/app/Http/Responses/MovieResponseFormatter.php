<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Services\HateoasService;
use App\Services\MovieDisambiguationService;
use App\Services\MovieLocaleService;
use App\Support\MovieRetrievalResult;
use Illuminate\Http\JsonResponse;

/**
 * Response Formatter for Movie API responses.
 * Formats different types of responses (success, error, disambiguation, etc.) into JSON.
 */
class MovieResponseFormatter extends BaseResponseFormatter
{
    public function __construct(
        private readonly HateoasService $hateoas,
        private readonly MovieDisambiguationService $movieDisambiguationService,
        private readonly MovieLocaleService $movieLocaleService
    ) {
        $this->entityType = 'movie';
    }

    /**
     * Format successful movie retrieval response.
     */
    public function formatSuccess(
        Movie $movie,
        string $slug,
        ?MovieDescription $selectedDescription = null,
        ?string $locale = null
    ): JsonResponse {
        $resource = MovieResource::make($movie)->additional([
            '_links' => $this->hateoas->movieLinks($movie),
        ]);

        if ($meta = $this->movieDisambiguationService->determineMeta($movie, $slug)) {
            $resource->additional(['_meta' => $meta]);
        }

        $data = $resource->resolve();

        // Add localized metadata if locale is provided
        if ($locale !== null && $locale !== '') {
            $movieLocale = $this->movieLocaleService->getLocalizedMetadata($movie, $locale);
            if ($movieLocale) {
                $data['locale'] = $movieLocale->locale->value;
                if ($movieLocale->title_localized) {
                    $data['title_localized'] = $movieLocale->title_localized;
                }
                if ($movieLocale->director_localized) {
                    $data['director_localized'] = $movieLocale->director_localized;
                }
                if ($movieLocale->tagline) {
                    $data['tagline'] = $movieLocale->tagline;
                }
                if ($movieLocale->synopsis) {
                    $data['synopsis'] = $movieLocale->synopsis;
                }
            } else {
                // Fallback to en-US if requested locale not found
                $enLocale = $this->movieLocaleService->getLocalizedMetadata($movie, 'en-US');
                if ($enLocale) {
                    $data['locale'] = 'en-US';
                    if ($enLocale->title_localized) {
                        $data['title_localized'] = $enLocale->title_localized;
                    }
                    if ($enLocale->director_localized) {
                        $data['director_localized'] = $enLocale->director_localized;
                    }
                }
            }
        }

        // Add all descriptions to response
        if ($movie->relationLoaded('descriptions')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, MovieDescription> $descriptions */
            $descriptions = $movie->descriptions;
            $data['descriptions'] = $descriptions->map(function (MovieDescription $description) {
                return [
                    'id' => $description->id,
                    'locale' => $description->locale->value,
                    'text' => $description->text,
                    'context_tag' => $description->context_tag?->value,
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
                    'context_tag' => $description->context_tag?->value,
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
     * Format response from MovieRetrievalResult.
     */
    public function formatFromResult(MovieRetrievalResult $result, string $slug, ?string $locale = null): JsonResponse
    {
        if ($result->isCached()) {
            return response()->json($result->getData());
        }

        if ($result->isFound()) {
            return $this->formatSuccess(
                $result->getMovie(),
                $slug,
                $result->getSelectedDescription(),
                $locale
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
     * Format list of movies response.
     */
    public function formatMovieList(array $movies): JsonResponse
    {
        return response()->json(['data' => $movies]);
    }
}
