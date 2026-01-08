<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Secret key used to verify notification webhook signatures.
    | This should be set in your .env file as NOTIFICATION_WEBHOOK_SECRET.
    | If not set, webhook signature verification will be disabled.
    |
    */

    'notification_secret' => env('NOTIFICATION_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Enable Notification Webhook Signature Verification
    |--------------------------------------------------------------------------
    |
    | If true, notification webhooks must include valid signature header.
    | If false, webhook signature verification is disabled (useful for testing).
    |
    */

    'verify_notification_signature' => env('WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE', true),

    /*
    |--------------------------------------------------------------------------
    | Supported Notification Event Types
    |--------------------------------------------------------------------------
    |
    | List of supported notification event types that can be received
    | from external systems.
    |
    */

    'supported_notification_events' => [
        'generation.completed',
        'generation.failed',
        'user.registered',
        'user.updated',
    ],

    /*
    |--------------------------------------------------------------------------
    | Outgoing Webhook URLs
    |--------------------------------------------------------------------------
    |
    | Configure webhook URLs for outgoing notifications.
    | These URLs will receive webhooks when events occur in MovieMind API.
    |
    | Format: 'event_type' => ['url1', 'url2', ...]
    |
    | Example:
    | 'outgoing_urls' => [
    |     'generation.completed' => [
    |         env('WEBHOOK_URL_GENERATION_COMPLETED'),
    |     ],
    |     'generation.failed' => [
    |         env('WEBHOOK_URL_GENERATION_FAILED'),
    |     ],
    | ],
    |
    */

    'outgoing_urls' => [
        'generation.completed' => array_filter([
            env('WEBHOOK_URL_GENERATION_COMPLETED'),
        ]),
        'generation.failed' => array_filter([
            env('WEBHOOK_URL_GENERATION_FAILED'),
        ]),
        'movie.generation.completed' => array_filter([
            env('WEBHOOK_URL_MOVIE_GENERATION_COMPLETED'),
        ]),
        'movie.generation.failed' => array_filter([
            env('WEBHOOK_URL_MOVIE_GENERATION_FAILED'),
        ]),
        'person.generation.completed' => array_filter([
            env('WEBHOOK_URL_PERSON_GENERATION_COMPLETED'),
        ]),
        'person.generation.failed' => array_filter([
            env('WEBHOOK_URL_PERSON_GENERATION_FAILED'),
        ]),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outgoing Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Secret key used to sign outgoing webhooks.
    | This should be set in your .env file as OUTGOING_WEBHOOK_SECRET.
    | If not set, outgoing webhooks will not be signed.
    |
    */

    'outgoing_secret' => env('OUTGOING_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Outgoing Webhook Signature Header
    |--------------------------------------------------------------------------
    |
    | Header name for outgoing webhook signature.
    |
    */

    'outgoing_signature_header' => 'X-MovieMind-Webhook-Signature',

    /*
    |--------------------------------------------------------------------------
    | Outgoing Webhook Max Attempts
    |--------------------------------------------------------------------------
    |
    | Maximum number of retry attempts for outgoing webhooks.
    |
    */

    'outgoing_max_attempts' => (int) env('WEBHOOK_OUTGOING_MAX_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Outgoing Webhook Retry Delays (minutes)
    |--------------------------------------------------------------------------
    |
    | Exponential backoff delays for outgoing webhook retries.
    |
    */

    'outgoing_retry_delays' => [
        1 => (int) env('WEBHOOK_RETRY_DELAY_1', 1),
        2 => (int) env('WEBHOOK_RETRY_DELAY_2', 5),
        3 => (int) env('WEBHOOK_RETRY_DELAY_3', 15),
    ],
];
