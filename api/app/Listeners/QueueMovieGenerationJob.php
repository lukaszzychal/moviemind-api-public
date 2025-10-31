<?php

namespace App\Listeners;

use App\Events\MovieGenerationRequested;
use App\Jobs\GenerateMovieJob;

class QueueMovieGenerationJob
{
    /**
     * Handle the event.
     */
    public function handle(MovieGenerationRequested $event): void
    {
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}

