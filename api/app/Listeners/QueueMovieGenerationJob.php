<?php

namespace App\Listeners;

use App\Events\MovieGenerationRequested;
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
        $aiService = config('services.ai.service', 'mock');

        match ($aiService) {
            'real' => RealGenerateMovieJob::dispatch($event->slug, $event->jobId),
            'mock' => MockGenerateMovieJob::dispatch($event->slug, $event->jobId),
            default => throw new \InvalidArgumentException("Invalid AI service: {$aiService}. Must be 'mock' or 'real'."),
        };
    }
}

