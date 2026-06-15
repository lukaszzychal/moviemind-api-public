<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Support\SearchResult;
use Illuminate\Http\JsonResponse;

abstract class BaseResponseFormatter
{
    /**
     * Entity type identifier used for translation keys (e.g. 'movie', 'person').
     */
    protected string $entityType;

    public function formatError(string $errorMessage, int $statusCode, ?array $additionalData = null): JsonResponse
    {
        $response = ['error' => $errorMessage];

        if ($additionalData !== null) {
            $response = array_merge($response, $additionalData);
        }

        return response()->json($response, $statusCode);
    }

    public function formatNotFound(?string $customMessage = null): JsonResponse
    {
        $message = $customMessage ?? trans("api.{$this->entityType}.not_found");

        return $this->formatError($message, 404);
    }

    public function formatDescriptionNotFound(): JsonResponse
    {
        return $this->formatError(trans("api.{$this->entityType}.description_not_found"), 404);
    }

    public function formatBioNotFound(): JsonResponse
    {
        return $this->formatError(trans("api.{$this->entityType}.bio_not_found"), 404);
    }

    public function formatInvalidSlug(string $slug, array $validation): JsonResponse
    {
        return $this->formatError(
            trans("api.{$this->entityType}.invalid_slug"),
            400,
            [
                'message' => $validation['reason'] ?? '',
                'confidence' => $validation['confidence'] ?? 0,
                'slug' => $slug,
            ]
        );
    }

    public function formatDisambiguation(string $slug, array $options): JsonResponse
    {
        return $this->formatError(
            trans("api.{$this->entityType}.multiple_found"),
            300,
            [
                'message' => trans("api.{$this->entityType}.disambiguation_message"),
                'slug' => $slug,
                'options' => $options,
                'count' => count($options),
                'hint' => "Use the slug from options to access specific {$this->entityType} (e.g., GET /api/v1/{$this->getEndpointBase()}/{slug})",
            ]
        );
    }

    public function formatGenerationQueued(array|object $generationResult): JsonResponse
    {
        return response()->json($generationResult, 202);
    }

    public function formatSearchResult(SearchResult $searchResult): JsonResponse
    {
        $statusCode = $searchResult->getHttpStatusCode();

        if ($searchResult->isAmbiguous()) {
            return response()->json([
                'error' => trans("api.{$this->entityType}.search_multiple_found"),
                'message' => trans("api.{$this->entityType}.search_multiple_message"),
                'match_type' => $searchResult->matchType,
                'count' => $searchResult->total,
                'results' => $searchResult->results,
                'hint' => "Use the slug from results to access specific {$this->entityType} (e.g., GET /api/v1/{$this->getEndpointBase()}/{slug})",
            ], $statusCode);
        }

        if ($searchResult->isEmpty()) {
            return response()->json([
                'error' => trans("api.{$this->entityType}.search_none_found"),
                'message' => trans("api.{$this->entityType}.search_none_message"),
                'match_type' => $searchResult->matchType,
                'total' => $searchResult->total,
                'results' => [],
            ], $statusCode);
        }

        return response()->json($searchResult->toArray(), $statusCode);
    }

    public function formatDisambiguationSelectionNotFound(): JsonResponse
    {
        return $this->formatError(trans("api.{$this->entityType}.disambiguation_selection_not_found"), 404);
    }

    public function formatRefreshNoSnapshot(): JsonResponse
    {
        return $this->formatError(trans("api.{$this->entityType}.no_snapshot"), 404);
    }

    public function formatRefreshFailed(): JsonResponse
    {
        return $this->formatError(trans("api.{$this->entityType}.refresh_failed"), 500);
    }

    public function formatRefreshSuccess(string $slug, string|int $entityId): JsonResponse
    {
        return response()->json([
            'message' => trans("api.{$this->entityType}.refresh_success"),
            'slug' => $slug,
            "{$this->entityType}_id" => $entityId,
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get the API endpoint base name for hints (e.g., 'movies' for movie).
     */
    protected function getEndpointBase(): string
    {
        return match ($this->entityType) {
            'person' => 'people',
            'tv_series' => 'tv-series',
            'tv_show' => 'tv-shows',
            default => 'movies',
        };
    }
}
