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
        $jobClass = AiServiceSelector::getJobClass(RealGenerateTvShowJob::class, MockGenerateTvShowJob::class);

        $jobClass::dispatch(
            $event->slug,
            $event->jobId,
            existingTvShowId: $event->existingTvShowId,
            baselineDescriptionId: $event->baselineDescriptionId,
            locale: $event->locale,
            contextTag: $event->contextTag,
            tmdbData: $event->tmdbData
        );
    }
}
