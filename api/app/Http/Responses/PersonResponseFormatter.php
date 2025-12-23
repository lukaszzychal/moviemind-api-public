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
class PersonResponseFormatter
{
    public function __construct(
        private readonly HateoasService $hateoas,
        private readonly PersonDisambiguationService $personDisambiguationService
    ) {}

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
     * Format bio not found response.
     */
    public function formatBioNotFound(): JsonResponse
    {
        return $this->formatError('Bio not found for person', 404);
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
            'Multiple people found',
            300,
            [
                'message' => 'Multiple people match your search. Please select one from the options below:',
                'slug' => $slug,
                'options' => $options,
                'count' => count($options),
                'hint' => 'Use the slug from options to access specific person (e.g., GET /api/v1/people/{slug})',
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
        $message = $customMessage ?? 'Person not found';

        return $this->formatError($message, 404);
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

    /**
     * Format disambiguation selection error (person not found in search results).
     */
    public function formatDisambiguationSelectionNotFound(): JsonResponse
    {
        return $this->formatError('Selected person not found in search results', 404);
    }

    /**
     * Format refresh success response.
     *
     * @param  string  $slug  Person slug
     * @param  string  $personId  Person ID (UUID)
     */
    public function formatRefreshSuccess(string $slug, string $personId): JsonResponse
    {
        return response()->json([
            'message' => 'Person data refreshed from TMDb',
            'slug' => $slug,
            'person_id' => $personId,
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Format refresh error - no snapshot found.
     */
    public function formatRefreshNoSnapshot(): JsonResponse
    {
        return $this->formatError('No TMDb snapshot found for this person', 404);
    }

    /**
     * Format refresh error - failed to refresh.
     */
    public function formatRefreshFailed(): JsonResponse
    {
        return $this->formatError('Failed to refresh person data from TMDb', 500);
    }
}
