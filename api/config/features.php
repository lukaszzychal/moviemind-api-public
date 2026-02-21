<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags Configuration
    |--------------------------------------------------------------------------
    |
    | Define your features here. Each feature can have:
    | - default: The soft default value (overridden by DB/UI)
    | - force: The forced value (overrides EVERYTHING, including DB/UI)
    | - name: Human readable name
    | - description: Description for UI
    |
    */

    'test_feature' => [
        'default' => env('FEATURE_TEST_FEATURE', false),
        'force' => env('FEATURE_TEST_FEATURE_FORCE'),
        'name' => 'Test Feature',
        'description' => 'Feature used for testing purposes.',
        'togglable' => true,
    ],

    // Core AI generation

    'ai_description_generation' => [
        'default' => env('FEATURE_AI_DESCRIPTION_GENERATION', true),
        'force' => env('FEATURE_AI_DESCRIPTION_GENERATION_FORCE'),
        'name' => 'AI Description Generation',
        'description' => 'Enables AI-generated movie/series descriptions.',
        'category' => 'core_ai',
        'togglable' => true,
    ],
    'ai_bio_generation' => [
        'default' => env('FEATURE_AI_BIO_GENERATION', true),
        'force' => env('FEATURE_AI_BIO_GENERATION_FORCE'),
        'name' => 'AI Bio Generation',
        'description' => 'Enables AI-generated person biographies.',
        'category' => 'core_ai',
        'togglable' => true,
    ],
    'ai_generation_baseline_locking' => [
        'default' => env('FEATURE_AI_GENERATION_BASELINE_LOCKING', true),
        'force' => env('FEATURE_AI_GENERATION_BASELINE_LOCKING_FORCE'),
        'name' => 'AI Generation Baseline Locking',
        'description' => 'Locking + baseline update flow for AI generation (TASK-012).',
        'category' => 'core_ai',
        'togglable' => true,
    ],

    // AI Quality & Safety
    'ai_quality_scoring' => [
        'default' => env('FEATURE_AI_QUALITY_SCORING', false),
        'force' => env('FEATURE_AI_QUALITY_SCORING_FORCE'),
        'name' => 'AI Quality Scoring',
        'description' => 'Internal quality scoring for AI-generated content.',
        'category' => 'ai_quality',
        'togglable' => true,
    ],
    'ai_plagiarism_detection' => [
        'default' => env('FEATURE_AI_PLAGIARISM_DETECTION', false),
        'force' => env('FEATURE_AI_PLAGIARISM_DETECTION_FORCE'),
        'name' => 'AI Plagiarism Detection',
        'description' => 'Similarity / plagiarism detection for generated content.',
        'category' => 'ai_quality',
        'togglable' => true,
    ],
    'hallucination_guard' => [
        'default' => env('FEATURE_HALLUCINATION_GUARD', true),
        'force' => env('FEATURE_HALLUCINATION_GUARD_FORCE'),
        'name' => 'Hallucination Guard',
        'description' => 'Anti-hallucination guards (validation, heuristics).',
        'category' => 'ai_quality',
        'togglable' => true,
    ],
    'tmdb_verification' => [
        'default' => env('FEATURE_TMDB_VERIFICATION', true),
        'force' => env('FEATURE_TMDB_VERIFICATION_FORCE'),
        'name' => 'TMDb Verification',
        'description' => 'TMDb API verification for movies and people before AI generation.',
        'category' => 'ai_quality',
        'togglable' => true,
    ],
    'tvmaze_verification' => [
        'default' => env('FEATURE_TVMAZE_VERIFICATION', true),
        'force' => env('FEATURE_TVMAZE_VERIFICATION_FORCE'),
        'name' => 'TVmaze Verification',
        'description' => 'TVmaze API verification for TV series and TV shows before AI generation.',
        'category' => 'ai_quality',
        'togglable' => true,
    ],

    // Experimental Features
    'ai_use_toon_format' => [
        'default' => env('FEATURE_AI_USE_TOON_FORMAT', false),
        'force' => env('FEATURE_AI_USE_TOON_FORMAT_FORCE'),
        'name' => 'AI Use TOON Format',
        'description' => 'Experimental: Use TOON format for tabular data to save tokens.',
        'category' => 'experiments',
        'togglable' => true,
    ],

    // Admin / operations
    'webhook_notifications' => [
        'default' => env('FEATURE_WEBHOOK_NOTIFICATIONS', true),
        'force' => env('FEATURE_WEBHOOK_NOTIFICATIONS_FORCE'),
        'name' => 'Webhook Notifications',
        'description' => 'Send outgoing webhooks when generation events occur (e.g. to webhook.site).',
        'category' => 'operations',
        'togglable' => true,
    ],
    'debug_endpoints' => [
        'default' => env('FEATURE_DEBUG_ENDPOINTS', false),
        'force' => env('FEATURE_DEBUG_ENDPOINTS_FORCE'),
        'name' => 'Debug Endpoints',
        'description' => 'Debug endpoints for service configuration inspection.',
        'category' => 'operations',
        'togglable' => true,
    ],
];
