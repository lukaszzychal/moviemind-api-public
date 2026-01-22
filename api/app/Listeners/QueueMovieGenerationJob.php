<?php

namespace App\Listeners;

use App\Events\MovieGenerationRequested;
use App\Helpers\AiServiceSelector;
use App\Jobs\MockGenerateMovieJob;
use App\Jobs\RealGenerateMovieJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class QueueMovieGenerationJob
{
    /**
     * Handle the event.
     * Dispatches Mock or Real job based on AI_SERVICE configuration.
     */
    public function handle(MovieGenerationRequested $event): void
    {
        Log::info('[QueueMovieGenerationJob] Handling event.', ['slug' => $event->slug]);

        $aiService = AiServiceSelector::getService();
        AiServiceSelector::validate();

        Log::info('[QueueMovieGenerationJob] AI Service selected.', ['service' => $aiService]);

        try {
            switch ($aiService) {
                case 'real':
                    Log::info('[QueueMovieGenerationJob] Dispatching RealGenerateMovieJob.');
                    RealGenerateMovieJob::dispatch(
                        $event->slug,
                        $event->jobId,
                        existingMovieId: $event->existingMovieId,
                        baselineDescriptionId: $event->baselineDescriptionId,
                        locale: $event->locale,
                        contextTag: $event->contextTag,
                        tmdbData: $event->tmdbData
                    );
                    break;
                case 'mock':
                    Log::info('[QueueMovieGenerationJob] Dispatching MockGenerateMovieJob.');
                    MockGenerateMovieJob::dispatch(
                        $event->slug,
                        $event->jobId,
                        existingMovieId: $event->existingMovieId,
                        baselineDescriptionId: $event->baselineDescriptionId,
                        locale: $event->locale,
                        contextTag: $event->contextTag,
                        tmdbData: $event->tmdbData
                    );
                    break;
                default:
                    throw new \InvalidArgumentException("Invalid AI service: {$aiService}. Must be 'mock' or 'real'.");
            }

            Log::info('[QueueMovieGenerationJob] Dispatch command finished.');

            $queueLength = Redis::llen('queues:default');
            Log::info('[QueueMovieGenerationJob] Checked queue length.', ['length' => $queueLength]);

        } catch (\Throwable $e) {
            Log::error('[QueueMovieGenerationJob] Exception caught during handle.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
