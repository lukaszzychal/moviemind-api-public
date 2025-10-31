<?php

namespace App\Listeners;

use App\Events\PersonGenerationRequested;
use App\Jobs\GeneratePersonJob;

class QueuePersonGenerationJob
{
    /**
     * Handle the event.
     */
    public function handle(PersonGenerationRequested $event): void
    {
        GeneratePersonJob::dispatch($event->slug, $event->jobId);
    }
}

