<?php

namespace App\Listeners;

use App\Events\PersonGenerationRequested;
use App\Helpers\AiServiceSelector;
use App\Jobs\MockGeneratePersonJob;
use App\Jobs\RealGeneratePersonJob;

class QueuePersonGenerationJob
{
    /**
     * Handle the event.
     * Dispatches Mock or Real job based on AI_SERVICE configuration.
     */
    public function handle(PersonGenerationRequested $event): void
    {
        $jobClass = AiServiceSelector::getJobClass(RealGeneratePersonJob::class, MockGeneratePersonJob::class);

        $jobClass::dispatch(
            $event->slug,
            $event->jobId,
            existingPersonId: $event->existingPersonId,
            baselineBioId: $event->baselineBioId,
            locale: $event->locale,
            contextTag: $event->contextTag,
            tmdbData: $event->tmdbData
        );
    }
}
