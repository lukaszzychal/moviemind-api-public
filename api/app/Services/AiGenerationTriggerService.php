<?php

namespace App\Services;

use App\Actions\QueueMovieGenerationAction;
use App\Actions\QueuePersonGenerationAction;
use App\Actions\QueueTvSeriesGenerationAction;
use App\Actions\QueueTvShowGenerationAction;
use Illuminate\Support\Facades\Log;

class AiGenerationTriggerService
{
    public function __construct(
        private readonly QueueMovieGenerationAction $movieAction,
        private readonly QueuePersonGenerationAction $personAction,
        private readonly QueueTvSeriesGenerationAction $tvSeriesAction,
        private readonly QueueTvShowGenerationAction $tvShowAction,
    ) {}

    public function trigger(string $entityType, string $slug, string $locale, string $contextTag): ?array
    {
        try {
            return match (strtoupper($entityType)) {
                'MOVIE' => $this->movieAction->handle(
                    slug: $slug,
                    locale: $locale,
                    contextTag: $contextTag
                ),
                'PERSON' => $this->personAction->handle(
                    slug: $slug,
                    locale: $locale,
                    contextTag: $contextTag
                ),
                'TV_SERIES' => $this->tvSeriesAction->handle(
                    slug: $slug,
                    locale: $locale,
                    contextTag: $contextTag
                ),
                'TV_SHOW' => $this->tvShowAction->handle(
                    slug: $slug,
                    locale: $locale,
                    contextTag: $contextTag
                ),
                default => throw new \InvalidArgumentException("Unknown entity type for AI generation: {$entityType}"),
            };
        } catch (\Exception $e) {
            Log::error('Failed to trigger AI generation: '.$e->getMessage());

            return null;
        }
    }
}
