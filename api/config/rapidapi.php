<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | RapidAPI Proxy Secret
    |--------------------------------------------------------------------------
    |
    | Secret key used to verify requests coming from RapidAPI proxy.
    | This should be set in your .env file as RAPIDAPI_PROXY_SECRET.
    | If not set, proxy secret verification will be disabled.
    |
    */

    'proxy_secret' => env('RAPIDAPI_PROXY_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | RapidAPI Plan Mapping
    |--------------------------------------------------------------------------
    |
    | Maps RapidAPI subscription plans to our internal subscription plans.
    | RapidAPI plans: basic, pro, ultra
    | Our plans: free, pro, enterprise
    |
    */

    'plan_mapping' => [
        'basic' => 'free',
        'pro' => 'pro',
        'ultra' => 'enterprise',
    ],

    /*
    |--------------------------------------------------------------------------
    | RapidAPI Headers
    |--------------------------------------------------------------------------
    |
    | Standard headers sent by RapidAPI proxy:
    | - X-RapidAPI-Key: API key (already handled by RapidApiAuth)
    | - X-RapidAPI-Proxy-Secret: Secret for proxy verification
    | - X-RapidAPI-User: RapidAPI user identifier
    | - X-RapidAPI-Subscription: Subscription plan (basic, pro, ultra)
    |
    */

    'headers' => [
        'proxy_secret' => 'X-RapidAPI-Proxy-Secret',
        'user' => 'X-RapidAPI-User',
        'subscription' => 'X-RapidAPI-Subscription',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enable Proxy Secret Verification
    |--------------------------------------------------------------------------
    |
    | If true, requests must include valid X-RapidAPI-Proxy-Secret header.
    | If false, proxy secret verification is disabled (useful for testing).
    |
    */

    'verify_proxy_secret' => env('RAPIDAPI_VERIFY_PROXY_SECRET', true),

    /*
    |--------------------------------------------------------------------------
    | Log RapidAPI Requests
    |--------------------------------------------------------------------------
    |
    | If true, all requests with RapidAPI headers will be logged.
    | Useful for debugging and monitoring.
    |
    */

    'log_requests' => env('RAPIDAPI_LOG_REQUESTS', false),

    /*
    |--------------------------------------------------------------------------
    | RapidAPI Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Secret key used to verify webhook signatures from RapidAPI.
    | This should be set in your .env file as RAPIDAPI_WEBHOOK_SECRET.
    | If not set, webhook signature verification will be disabled.
    |
    */

    'webhook_secret' => env('RAPIDAPI_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Enable Webhook Signature Verification
    |--------------------------------------------------------------------------
    |
    | If true, webhooks must include valid X-RapidAPI-Signature header.
    | If false, webhook signature verification is disabled (useful for testing).
    |
    */

    'verify_webhook_signature' => env('RAPIDAPI_VERIFY_WEBHOOK_SIGNATURE', true),
];
