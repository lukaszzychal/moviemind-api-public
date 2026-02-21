<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MovieGenerationCompleted;
use App\Events\MovieGenerationFailed;
use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use App\Jobs\SendOutgoingWebhookJob;
use App\Models\WebhookSubscription;
use App\Services\OutgoingWebhookService;
use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Feature;

/**
 * Listener for sending outgoing webhooks when generation events occur.
 *
 * Listens to:
 * - MovieGenerationRequested
 * - PersonGenerationRequested
 * - MovieGenerationCompleted
 * - MovieGenerationFailed
 */
class SendOutgoingWebhookListener
{
    public function handle(MovieGenerationRequested|PersonGenerationRequested|MovieGenerationCompleted|MovieGenerationFailed $event): void
    {
        if ($event instanceof MovieGenerationRequested) {
            $this->handleMovieGenerationRequested($event);
        } elseif ($event instanceof PersonGenerationRequested) {
            $this->handlePersonGenerationRequested($event);
        } elseif ($event instanceof MovieGenerationCompleted) {
            $this->handleMovieGenerationCompleted($event);
        } elseif ($event instanceof MovieGenerationFailed) {
            $this->handleMovieGenerationFailed($event);
        }
    }

    public function handleMovieGenerationRequested(MovieGenerationRequested $event): void
    {
        if (! Feature::active('webhook_notifications')) {
            return;
        }

        $urls = $this->getUrlsForEvent('movie.generation.requested', 'movie.generation.completed', 'generation.completed');
        if (empty($urls)) {
            return;
        }

        $webhookService = app(OutgoingWebhookService::class);
        foreach ($urls as $url) {
            if (empty($url)) {
                continue;
            }
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
            if ($webhook->isFailed()) {
                SendOutgoingWebhookJob::dispatch($webhook->id)
                    ->delay($webhook->next_retry_at);
            }
        }
    }

    public function handlePersonGenerationRequested(PersonGenerationRequested $event): void
    {
        if (! Feature::active('webhook_notifications')) {
            return;
        }

        $urls = $this->getUrlsForEvent('person.generation.completed', 'generation.completed');
        if (empty($urls)) {
            return;
        }
        foreach ($urls as $url) {
            if (empty($url)) {
                continue;
            }
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
            if ($webhook->isFailed()) {
                SendOutgoingWebhookJob::dispatch($webhook->id)
                    ->delay($webhook->next_retry_at);
            }
        }
    }

    public function handleMovieGenerationCompleted(MovieGenerationCompleted $event): void
    {
        if (! Feature::active('webhook_notifications')) {
            return;
        }

        $urls = $this->getUrlsForEvent('movie.generation.completed', 'generation.completed');
        if (empty($urls)) {
            return;
        }

        $webhookService = app(OutgoingWebhookService::class);
        foreach ($urls as $url) {
            if (empty($url)) {
                continue;
            }
            $webhook = $webhookService->sendWebhook(
                eventType: 'movie.generation.completed',
                payload: [
                    'entity_type' => 'MOVIE',
                    'job_id' => $event->jobId,
                    'slug' => $event->slug,
                    'entity_id' => $event->entityId,
                    'description_id' => $event->descriptionId,
                    'locale' => $event->locale,
                    'context_tag' => $event->contextTag,
                ],
                url: $url
            );
            if ($webhook->isFailed()) {
                SendOutgoingWebhookJob::dispatch($webhook->id)
                    ->delay($webhook->next_retry_at);
            }
        }
    }

    public function handleMovieGenerationFailed(MovieGenerationFailed $event): void
    {
        if (! Feature::active('webhook_notifications')) {
            return;
        }

        $urls = $this->getUrlsForEvent('movie.generation.failed', 'generation.failed');
        if (empty($urls)) {
            return;
        }

        $webhookService = app(OutgoingWebhookService::class);
        foreach ($urls as $url) {
            if (empty($url)) {
                continue;
            }
            $webhook = $webhookService->sendWebhook(
                eventType: 'movie.generation.failed',
                payload: [
                    'entity_type' => 'MOVIE',
                    'job_id' => $event->jobId,
                    'slug' => $event->slug,
                    'error_message' => $event->errorMessage,
                    'locale' => $event->locale,
                    'context_tag' => $event->contextTag,
                ],
                url: $url
            );
            if ($webhook->isFailed()) {
                SendOutgoingWebhookJob::dispatch($webhook->id)
                    ->delay($webhook->next_retry_at);
            }
        }
    }

    /**
     * @param  string  $primaryKey  Event type key (e.g. movie.generation.completed)
     * @param  string  ...$fallbackKeys  Optional fallback keys if primary has no URLs
     * @return array<int, string>
     */
    public function getUrlsForEvent(string $primaryKey, string ...$fallbackKeys): array
    {
        $outgoingUrls = Config::get('webhooks.outgoing_urls', []);
        $fromConfig = $outgoingUrls[$primaryKey] ?? [];
        foreach ($fallbackKeys as $key) {
            if (! empty($fromConfig)) {
                break;
            }
            $fromConfig = $outgoingUrls[$key] ?? [];
        }
        $fromConfig = is_array($fromConfig) ? array_filter($fromConfig) : [];

        $fromSubscriptions = WebhookSubscription::where('event_type', $primaryKey)
            ->pluck('url')
            ->filter()
            ->all();

        $merged = array_values(array_unique(array_merge($fromConfig, $fromSubscriptions)));

        return array_values(array_filter($merged, fn (mixed $url): bool => trim((string) $url) !== ''));
    }
}
