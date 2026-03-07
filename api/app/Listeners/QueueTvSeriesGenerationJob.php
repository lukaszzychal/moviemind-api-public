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
        $jobClass = AiServiceSelector::getJobClass(RealGenerateTvSeriesJob::class, MockGenerateTvSeriesJob::class);

        $jobClass::dispatch(
            $event->slug,
            $event->jobId,
            existingEntityId: $event->existingTvSeriesId,
            baselineDescriptionId: $event->baselineDescriptionId,
            locale: $event->locale,
            contextTag: $event->contextTag,
            tmdbData: $event->tmdbData
        );
    }
}
