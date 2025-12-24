<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TvSeriesGenerationRequested;
use App\Helpers\AiServiceSelector;
use App\Jobs\MockGenerateTvSeriesJob;
use App\Jobs\RealGenerateTvSeriesJob;

class QueueTvSeriesGenerationJob
{
    /**
     * Handle the event.
     * Dispatches Mock or Real job based on AI_SERVICE configuration.
     */
    public function handle(TvSeriesGenerationRequested $event): void
    {
        $aiService = AiServiceSelector::getService();
        AiServiceSelector::validate();

        match ($aiService) {
            'real' => RealGenerateTvSeriesJob::dispatch(
                $event->slug,
                $event->jobId,
                existingTvSeriesId: $event->existingTvSeriesId,
                baselineDescriptionId: $event->baselineDescriptionId,
                locale: $event->locale,
                contextTag: $event->contextTag,
                tmdbData: $event->tmdbData
            ),
            'mock' => MockGenerateTvSeriesJob::dispatch(
                $event->slug,
                $event->jobId,
                existingTvSeriesId: $event->existingTvSeriesId,
                baselineDescriptionId: $event->baselineDescriptionId,
                locale: $event->locale,
                contextTag: $event->contextTag,
                tmdbData: $event->tmdbData
            ),
            default => throw new \InvalidArgumentException("Invalid AI service: {$aiService}. Must be 'mock' or 'real'."),
        };
    }
}
