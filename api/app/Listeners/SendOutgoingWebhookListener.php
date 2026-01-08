<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use App\Jobs\SendOutgoingWebhookJob;
use App\Services\OutgoingWebhookService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

/**
 * Listener for sending outgoing webhooks when generation events occur.
 *
 * Listens to:
 * - MovieGenerationRequested
 * - PersonGenerationRequested
 * - (Future: MovieGenerationCompleted, PersonGenerationCompleted, etc.)
 */
class SendOutgoingWebhookListener
{
    /**
     * Handle incoming events and route to appropriate handler.
     */
    public function handle(MovieGenerationRequested|PersonGenerationRequested $event): void
    {
        if ($event instanceof MovieGenerationRequested) {
            $this->handleMovieGenerationRequested($event);
        } elseif ($event instanceof PersonGenerationRequested) {
            $this->handlePersonGenerationRequested($event);
        }
    }

    /**
     * Handle MovieGenerationRequested event.
     */
    public function handleMovieGenerationRequested(MovieGenerationRequested $event): void
    {
        Log::info('SendOutgoingWebhookListener: handleMovieGenerationRequested called', [
            'slug' => $event->slug,
            'job_id' => $event->jobId,
        ]);

        if (! Feature::active('webhook_notifications')) {
            Log::info('SendOutgoingWebhookListener: webhook_notifications feature flag is OFF');

            return;
        }

        // Get outgoing URLs - use direct array access because keys contain dots
        $outgoingUrls = Config::get('webhooks.outgoing_urls', []);
        $urls = $outgoingUrls['movie.generation.completed'] ?? [];

        // Fallback to generic generation.completed if movie-specific not configured
        if (empty($urls)) {
            $urls = $outgoingUrls['generation.completed'] ?? [];
        }

        Log::info('SendOutgoingWebhookListener: URLs found', [
            'movie_urls' => $outgoingUrls['movie.generation.completed'] ?? [],
            'generic_urls' => $outgoingUrls['generation.completed'] ?? [],
            'final_urls' => $urls,
        ]);

        if (empty($urls)) {
            Log::info('SendOutgoingWebhookListener: No webhook URLs configured, skipping');

            return;
        }

        foreach ($urls as $url) {
            if (empty($url)) {
                Log::warning('SendOutgoingWebhookListener: Empty URL in array, skipping', ['urls' => $urls]);

                continue;
            }

            // Create outgoing webhook and dispatch job
            $webhookService = app(OutgoingWebhookService::class);
            $webhook = $webhookService->sendWebhook(
                eventType: 'movie.generation.requested',
                payload: [
                    'entity_type' => 'MOVIE',
                    'slug' => $event->slug,
                    'job_id' => $event->jobId,
                    'locale' => $event->locale,
                    'context_tag' => $event->contextTag,
                ],
                url: $url
            );

            // If webhook failed, dispatch retry job
            if ($webhook->isFailed()) {
                SendOutgoingWebhookJob::dispatch($webhook->id)
                    ->delay($webhook->next_retry_at);
            }
        }
    }

    /**
     * Handle PersonGenerationRequested event.
     */
    public function handlePersonGenerationRequested(PersonGenerationRequested $event): void
    {
        if (! Feature::active('webhook_notifications')) {
            return;
        }

        // Get outgoing URLs - use direct array access because keys contain dots
        $outgoingUrls = Config::get('webhooks.outgoing_urls', []);
        $urls = $outgoingUrls['person.generation.completed'] ?? [];

        // Fallback to generic generation.completed if person-specific not configured
        if (empty($urls)) {
            $urls = $outgoingUrls['generation.completed'] ?? [];
        }

        foreach ($urls as $url) {
            if (empty($url)) {
                continue;
            }

            // Create outgoing webhook and dispatch job
            $webhookService = app(OutgoingWebhookService::class);
            $webhook = $webhookService->sendWebhook(
                eventType: 'person.generation.requested',
                payload: [
                    'entity_type' => 'PERSON',
                    'slug' => $event->slug,
                    'job_id' => $event->jobId,
                    'locale' => $event->locale,
                    'context_tag' => $event->contextTag,
                ],
                url: $url
            );

            // If webhook failed, dispatch retry job
            if ($webhook->isFailed()) {
                SendOutgoingWebhookJob::dispatch($webhook->id)
                    ->delay($webhook->next_retry_at);
            }
        }
    }
}
