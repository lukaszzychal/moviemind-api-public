<?php

namespace App\Listeners;

use App\Events\MovieGenerationRequested;
use App\Helpers\AiServiceSelector;
use App\Jobs\MockGenerateMovieJob;
use App\Jobs\RealGenerateMovieJob;

class QueueMovieGenerationJob
{
    /**
     * Handle the event.
     * Dispatches Mock or Real job based on AI_SERVICE configuration.
     */
    public function handle(MovieGenerationRequested $event): void
    {
        $aiService = AiServiceSelector::getService();
        AiServiceSelector::validate();

        match ($aiService) {
            'real' => RealGenerateMovieJob::dispatch(
                $event->slug,
                $event->jobId,
                existingMovieId: $event->existingMovieId,
                baselineDescriptionId: $event->baselineDescriptionId
            ),
            'mock' => MockGenerateMovieJob::dispatch(
                $event->slug,
                $event->jobId,
                existingMovieId: $event->existingMovieId,
                baselineDescriptionId: $event->baselineDescriptionId
            ),
            default => throw new \InvalidArgumentException("Invalid AI service: {$aiService}. Must be 'mock' or 'real'."),
        };
    }
}
