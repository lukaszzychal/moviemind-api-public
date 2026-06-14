<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Resources\TvShowResource;
use App\Models\TvShow;
use App\Models\TvShowDescription;
use App\Services\HateoasService;
use App\Support\TvShowRetrievalResult;
use Illuminate\Http\JsonResponse;

/**
 * Response Formatter for TV Show API responses.
 */
class TvShowResponseFormatter extends BaseResponseFormatter
{
    public function __construct(
        private readonly HateoasService $hateoas
    ) {
        $this->entityType = 'tv_show';
    }

    /**
     * Format successful TV show retrieval response.
     */
    public function formatSuccess(
        TvShow $tvShow,
        string $slug,
        ?TvShowDescription $selectedDescription = null
    ): JsonResponse {
        $resource = TvShowResource::make($tvShow)->additional([
            '_links' => $this->hateoas->tvShowLinks($tvShow),
        ]);

        $data = $resource->resolve();

        // Add all descriptions to response
        if ($tvShow->relationLoaded('descriptions')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, TvShowDescription> $descriptions */
            $descriptions = $tvShow->descriptions;
            $data['descriptions'] = $descriptions->map(function (TvShowDescription $description) {
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
            $tvShow->load('descriptions');
            /** @var \Illuminate\Database\Eloquent\Collection<int, TvShowDescription> $descriptions */
            $descriptions = $tvShow->descriptions;
            $data['descriptions'] = $descriptions->map(function (TvShowDescription $description) {
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
     * Format response from TvShowRetrievalResult.
     */
    public function formatFromResult(TvShowRetrievalResult $result, string $slug): JsonResponse
    {
        if ($result->isCached()) {
            return response()->json($result->getData());
        }

        if ($result->isFound()) {
            return $this->formatSuccess(
                $result->getTvShow(),
                $slug,
                $result->getSelectedDescription()
            );
        }

        if ($result->isDescriptionNotFound()) {
            return $this->formatDescriptionNotFound();
        }

        if ($result->isGenerationQueued()) {
            return $this->formatGenerationQueued($result->getAdditionalData() ?? []);
        }

        if ($result->isDisambiguation()) {
            $additionalData = $result->getAdditionalData() ?? [];

            return $this->formatDisambiguation($slug, $additionalData['options'] ?? []);
        }

        if ($result->isInvalidSlug()) {
            $additionalData = $result->getAdditionalData() ?? [];

            return $this->formatInvalidSlug($slug, $additionalData);
        }

        return $this->formatNotFound($result->getErrorMessage());
    }
}
