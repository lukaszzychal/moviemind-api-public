<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Models\PersonBio;
use App\Services\HateoasService;
use App\Services\PersonDisambiguationService;
use App\Support\PersonRetrievalResult;
use Illuminate\Http\JsonResponse;

/**
 * Response Formatter for Person API responses.
 * Formats different types of responses (success, error, disambiguation, etc.) into JSON.
 */
class PersonResponseFormatter extends BaseResponseFormatter
{
    public function __construct(
        private readonly HateoasService $hateoas,
        private readonly PersonDisambiguationService $personDisambiguationService
    ) {
        $this->entityType = 'person';
    }

    /**
     * Format successful person retrieval response.
     */
    public function formatSuccess(
        Person $person,
        string $slug,
        ?PersonBio $selectedBio = null
    ): JsonResponse {
        $resource = PersonResource::make($person)->additional([
            '_links' => $this->hateoas->personLinks($person),
        ]);

        if ($meta = $this->personDisambiguationService->determineMeta($person, $slug)) {
            $resource->additional(['_meta' => $meta]);
        }

        $data = $resource->resolve();

        // Add all bios to response
        if ($person->relationLoaded('bios')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, PersonBio> $bios */
            $bios = $person->bios;
            $data['bios'] = $bios->map(function (PersonBio $bio) {
                return [
                    'id' => $bio->id,
                    'locale' => $bio->locale->value,
                    'text' => $bio->text,
                    'context_tag' => $bio->context_tag->value,
                    'origin' => $bio->origin->value,
                    'ai_model' => $bio->ai_model,
                    'created_at' => $bio->created_at?->toISOString(),
                    'updated_at' => $bio->updated_at?->toISOString(),
                ];
            })->values()->toArray();
        } else {
            // If bios are not loaded, load them
            $person->load('bios');
            /** @var \Illuminate\Database\Eloquent\Collection<int, PersonBio> $bios */
            $bios = $person->bios;
            $data['bios'] = $bios->map(function (PersonBio $bio) {
                return [
                    'id' => $bio->id,
                    'locale' => $bio->locale->value,
                    'text' => $bio->text,
                    'context_tag' => $bio->context_tag->value,
                    'origin' => $bio->origin->value,
                    'ai_model' => $bio->ai_model,
                    'created_at' => $bio->created_at?->toISOString(),
                    'updated_at' => $bio->updated_at?->toISOString(),
                ];
            })->values()->toArray();
        }

        if ($selectedBio !== null) {
            $data['selected_bio'] = [
                'id' => $selectedBio->id,
                'locale' => $selectedBio->locale->value,
                'text' => $selectedBio->text,
                'context_tag' => $selectedBio->context_tag->value,
                'origin' => $selectedBio->origin->value,
                'ai_model' => $selectedBio->ai_model,
                'created_at' => $selectedBio->created_at?->toISOString(),
                'updated_at' => $selectedBio->updated_at?->toISOString(),
            ];
        }

        return response()->json($data);
    }

    /**
     * Format response from PersonRetrievalResult.
     */
    public function formatFromResult(PersonRetrievalResult $result, string $slug): JsonResponse
    {
        if ($result->isCached()) {
            return response()->json($result->getData());
        }

        if ($result->isFound()) {
            return $this->formatSuccess(
                $result->getPerson(),
                $slug,
                $result->getSelectedBio()
            );
        }

        if ($result->isBioNotFound()) {
            return $this->formatBioNotFound();
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
}
