<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Resources\TvSeriesResource;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use App\Services\HateoasService;
use App\Support\TvSeriesRetrievalResult;
use Illuminate\Http\JsonResponse;

/**
 * Response Formatter for TV Series API responses.
 */
class TvSeriesResponseFormatter
{
    public function __construct(
        private readonly HateoasService $hateoas
    ) {}

    /**
     * Format successful TV series retrieval response.
     */
    public function formatSuccess(
        TvSeries $tvSeries,
        string $slug,
        ?TvSeriesDescription $selectedDescription = null
    ): JsonResponse {
        $resource = TvSeriesResource::make($tvSeries)->additional([
            '_links' => $this->hateoas->tvSeriesLinks($tvSeries),
        ]);

        $data = $resource->resolve();

        // Add all descriptions to response
        if ($tvSeries->relationLoaded('descriptions')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, TvSeriesDescription> $descriptions */
            $descriptions = $tvSeries->descriptions;
            $data['descriptions'] = $descriptions->map(function (TvSeriesDescription $description) {
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
            $tvSeries->load('descriptions');
            /** @var \Illuminate\Database\Eloquent\Collection<int, TvSeriesDescription> $descriptions */
            $descriptions = $tvSeries->descriptions;
            $data['descriptions'] = $descriptions->map(function (TvSeriesDescription $description) {
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
        return $this->formatError(trans('api.tv_series.description_not_found'), 404);
    }

    /**
     * Format invalid slug response.
     */
    public function formatInvalidSlug(string $slug, array $validation): JsonResponse
    {
        return $this->formatError(
            trans('api.tv_series.invalid_slug'),
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
            trans('api.tv_series.multiple_found'),
            300,
            [
                'message' => trans('api.tv_series.disambiguation_message'),
                'slug' => $slug,
                'options' => $options,
                'count' => count($options),
                'hint' => 'Use the slug from options to access specific TV series (e.g., GET /api/v1/tv-series/{slug})',
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
        $message = $customMessage ?? trans('api.tv_series.not_found');

        return $this->formatError($message, 404);
    }

    /**
     * Format response from TvSeriesRetrievalResult.
     */
    public function formatFromResult(TvSeriesRetrievalResult $result, string $slug): JsonResponse
    {
        if ($result->isCached()) {
            return response()->json($result->getData());
        }

        if ($result->isFound()) {
            return $this->formatSuccess(
                $result->getTvSeries(),
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
