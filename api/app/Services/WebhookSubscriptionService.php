<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Service for listing and managing webhook subscriptions (env + DB).
 * Single place of truth for "registered webhooks" used by API and UI.
 */
class WebhookSubscriptionService
{
    /**
     * List all registered webhook URLs (from config/env + from webhook_subscriptions) with source.
     *
     * @return array<int, array{event_type: string, url: string, source: 'env'|'subscription', id?: string}>
     */
    public function listRegistered(): array
    {
        $outgoingUrls = config('webhooks.outgoing_urls', []);
        if (! is_array($outgoingUrls)) {
            $outgoingUrls = [];
        }

        $list = [];

        foreach ($outgoingUrls as $eventType => $urls) {
            $urls = is_array($urls) ? array_filter($urls) : [];
            foreach ($urls as $url) {
                $url = trim((string) $url);
                if ($url !== '') {
                    $list[] = [
                        'event_type' => $eventType,
                        'url' => $url,
                        'source' => 'env',
                    ];
                }
            }
        }

        $subscriptions = WebhookSubscription::orderBy('event_type')->orderBy('url')->get();
        foreach ($subscriptions as $sub) {
            $list[] = [
                'event_type' => $sub->event_type,
                'url' => $sub->url,
                'source' => 'subscription',
                'id' => $sub->id,
            ];
        }

        return $list;
    }

    /**
     * Add a webhook subscription (URL for an event type). Event type must exist in config.
     *
     * @throws ValidationException
     */
    public function addSubscription(string $eventType, string $url): WebhookSubscription
    {
        $this->validateEventType($eventType);
        $this->validateUrl($url);

        $exists = WebhookSubscription::where('event_type', $eventType)->where('url', $url)->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'url' => ['This URL is already registered for this event type.'],
            ]);
        }

        return WebhookSubscription::create([
            'event_type' => $eventType,
            'url' => $url,
        ]);
    }

    /**
     * Update an existing subscription by id.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws ValidationException
     */
    public function updateSubscription(string $id, string $eventType, string $url): WebhookSubscription
    {
        $this->validateEventType($eventType);
        $this->validateUrl($url);

        $sub = WebhookSubscription::findOrFail($id);

        $exists = WebhookSubscription::where('event_type', $eventType)
            ->where('url', $url)
            ->where('id', '!=', $id)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'url' => ['This URL is already registered for this event type.'],
            ]);
        }

        $sub->update(['event_type' => $eventType, 'url' => $url]);

        return $sub->fresh();
    }

    /**
     * Delete a subscription by id.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function deleteSubscription(string $id): void
    {
        $sub = WebhookSubscription::findOrFail($id);
        $sub->delete();
    }

    private function validateEventType(string $eventType): void
    {
        $allowed = array_keys(config('webhooks.outgoing_urls', []));
        $validator = Validator::make(
            ['event_type' => $eventType],
            ['event_type' => ['required', 'string', 'in:'.implode(',', $allowed)]],
            ['event_type.in' => 'The event type must be one of the configured keys in webhooks.outgoing_urls.']
        );
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function validateUrl(string $url): void
    {
        $validator = Validator::make(
            ['url' => $url],
            ['url' => ['required', 'string', 'url', 'max:500']]
        );
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
