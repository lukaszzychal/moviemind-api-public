<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TvShowGenerationRequested;
use App\Helpers\AiServiceSelector;
use App\Jobs\MockGenerateTvShowJob;
use App\Jobs\RealGenerateTvShowJob;

class QueueTvShowGenerationJob
{
    /**
     * Handle the event.
     * Dispatches Mock or Real job based on AI_SERVICE configuration.
     */
    public function handle(TvShowGenerationRequested $event): void
    {
        $aiService = AiServiceSelector::getService();
        AiServiceSelector::validate();

        match ($aiService) {
            'real' => RealGenerateTvShowJob::dispatch(
                $event->slug,
                $event->jobId,
                existingTvShowId: $event->existingTvShowId,
                baselineDescriptionId: $event->baselineDescriptionId,
                locale: $event->locale,
                contextTag: $event->contextTag,
                tmdbData: $event->tmdbData
            ),
            'mock' => MockGenerateTvShowJob::dispatch(
                $event->slug,
                $event->jobId,
                existingTvShowId: $event->existingTvShowId,
                baselineDescriptionId: $event->baselineDescriptionId,
                locale: $event->locale,
                contextTag: $event->contextTag,
                tmdbData: $event->tmdbData
            ),
            default => throw new \InvalidArgumentException("Invalid AI service: {$aiService}. Must be 'mock' or 'real'."),
        };
    }
}
