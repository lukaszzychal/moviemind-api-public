<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TvShow;
use App\Models\TvShowDescription;

/**
 * DTO for TV show retrieval result.
 */
class TvShowRetrievalResult
{
    /**
     * @param  array<string, mixed>|null  $cachedData  Cached TV show data (if from cache)
     * @param  TvShow|null  $tvShow  TV Show model (if found locally)
     * @param  TvShowDescription|null  $selectedDescription  Selected description (if requested)
     * @param  bool  $isCached  Whether result came from cache
     * @param  bool  $isFound  Whether TV show was found
     * @param  bool  $isDescriptionNotFound  Whether requested description was not found
     * @param  bool  $isNotFound  Whether TV show was not found at all
     * @param  bool  $isGenerationQueued  Whether generation was queued (202 response)
     * @param  bool  $isDisambiguation  Whether disambiguation is needed (300 response)
     * @param  bool  $isInvalidSlug  Whether slug is invalid (400 response)
     * @param  string|null  $errorMessage  Error message (if any)
     * @param  int|null  $errorCode  HTTP error code (if any)
     * @param  array<string, mixed>|null  $additionalData  Additional data (e.g., disambiguation options, validation details)
     */
    public function __construct(
        private readonly ?array $cachedData = null,
        private readonly ?TvShow $tvShow = null,
        private readonly ?TvShowDescription $selectedDescription = null,
        private readonly bool $isCached = false,
        private readonly bool $isFound = false,
        private readonly bool $isDescriptionNotFound = false,
        private readonly bool $isNotFound = false,
        private readonly bool $isGenerationQueued = false,
        private readonly bool $isDisambiguation = false,
        private readonly bool $isInvalidSlug = false,
        private readonly ?string $errorMessage = null,
        private readonly ?int $errorCode = null,
        private readonly ?array $additionalData = null
    ) {}

    public static function fromCache(array $cachedData): self
    {
        return new self(
            cachedData: $cachedData,
            isCached: true,
            isFound: true
        );
    }

    public static function found(TvShow $tvShow, ?TvShowDescription $selectedDescription = null): self
    {
        return new self(
            tvShow: $tvShow,
            selectedDescription: $selectedDescription,
            isFound: true
        );
    }

    public static function descriptionNotFound(): self
    {
        return new self(
            isDescriptionNotFound: true,
            errorMessage: 'Description not found for TV show',
            errorCode: 404
        );
    }

    public static function notFound(): self
    {
        return new self(
            isNotFound: true,
            errorMessage: 'TV show not found',
            errorCode: 404
        );
    }

    public static function generationQueued(array $generationResult): self
    {
        return new self(
            isGenerationQueued: true,
            errorCode: 202,
            additionalData: $generationResult
        );
    }

    public static function disambiguation(string $slug, array $options): self
    {
        return new self(
            isDisambiguation: true,
            errorMessage: 'Multiple TV shows found',
            errorCode: 300,
            additionalData: [
                'slug' => $slug,
                'options' => $options,
                'count' => count($options),
            ]
        );
    }

    public static function invalidSlug(string $slug, array $validation): self
    {
        return new self(
            isInvalidSlug: true,
            errorMessage: 'Invalid slug format',
            errorCode: 400,
            additionalData: [
                'slug' => $slug,
                'reason' => $validation['reason'],
                'confidence' => $validation['confidence'],
            ]
        );
    }

    public function isCached(): bool
    {
        return $this->isCached;
    }

    public function isFound(): bool
    {
        return $this->isFound;
    }

    public function isDescriptionNotFound(): bool
    {
        return $this->isDescriptionNotFound;
    }

    public function isNotFound(): bool
    {
        return $this->isNotFound;
    }

    public function getData(): ?array
    {
        return $this->cachedData;
    }

    public function getTvShow(): ?TvShow
    {
        return $this->tvShow;
    }

    public function getSelectedDescription(): ?TvShowDescription
    {
        return $this->selectedDescription;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    public function isGenerationQueued(): bool
    {
        return $this->isGenerationQueued;
    }

    public function isDisambiguation(): bool
    {
        return $this->isDisambiguation;
    }

    public function isInvalidSlug(): bool
    {
        return $this->isInvalidSlug;
    }

    public function getAdditionalData(): ?array
    {
        return $this->additionalData;
    }
}
