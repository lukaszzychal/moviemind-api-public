<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which AI service implementation to use:
    | - 'mock' => MockGenerateMovieJob/MockGeneratePersonJob (for local development, testing)
    | - 'real' => RealGenerateMovieJob/RealGeneratePersonJob (for production with real AI API)
    |
    */

    'ai' => [
        'service' => env('AI_SERVICE', 'mock'), // 'mock' or 'real'
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OpenAI API (used by RealGenerateMovieJob/RealGeneratePersonJob)
    |
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'url' => env('OPENAI_URL', 'https://api.openai.com/v1/responses'),
        'health_url' => env('OPENAI_HEALTH_URL', 'https://api.openai.com/v1/models'),
        'backoff' => [
            'enabled' => (bool) env('OPENAI_BACKOFF_ENABLED', true),
            'intervals' => array_values(array_filter(array_map(
                static fn (string $value): ?int => is_numeric($value) ? (int) $value : null,
                explode(',', (string) env('OPENAI_BACKOFF_INTERVALS', '20,60,180'))
            ))),
        ],
    ],

];
