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
        $aiService = AiServiceSelector::getService();
        AiServiceSelector::validate();

        match ($aiService) {
            'real' => RealGeneratePersonJob::dispatch(
                $event->slug,
                $event->jobId,
                existingPersonId: $event->existingPersonId,
                baselineBioId: $event->baselineBioId,
                locale: $event->locale,
                contextTag: $event->contextTag,
                tmdbData: $event->tmdbData
            ),
            'mock' => MockGeneratePersonJob::dispatch(
                $event->slug,
                $event->jobId,
                existingPersonId: $event->existingPersonId,
                baselineBioId: $event->baselineBioId,
                locale: $event->locale,
                contextTag: $event->contextTag,
                tmdbData: $event->tmdbData
            ),
            default => throw new \InvalidArgumentException("Invalid AI service: {$aiService}. Must be 'mock' or 'real'."),
        };
    }
}
