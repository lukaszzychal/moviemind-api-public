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
        $jobClass = AiServiceSelector::getJobClass(RealGenerateMovieJob::class, MockGenerateMovieJob::class);

        $jobClass::dispatch(
            $event->slug,
            $event->jobId,
            existingMovieId: $event->existingMovieId,
            baselineDescriptionId: $event->baselineDescriptionId,
            locale: $event->locale,
            contextTag: $event->contextTag,
            tmdbData: $event->tmdbData
        );
    }
}
