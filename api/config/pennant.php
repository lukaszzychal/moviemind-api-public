<?php

declare(strict_types=1);

$flags = [
    // Core AI generation
    'ai_description_generation' => [
        'description' => 'Enables AI-generated movie/series descriptions.',
        'category' => 'core_ai',
        'default' => true,
    ],
    'ai_bio_generation' => [
        'description' => 'Enables AI-generated person biographies.',
        'category' => 'core_ai',
        'default' => true,
    ],
    'ai_generation_baseline_locking' => [
        'description' => 'Locking + baseline update flow for AI generation (TASK-012).',
        'category' => 'core_ai',
        'default' => true,
    ],

    // AI Quality & Safety
    'ai_quality_scoring' => [
        'description' => 'Internal quality scoring for AI-generated content.',
        'category' => 'ai_quality',
        'default' => false,
    ],
    'ai_plagiarism_detection' => [
        'description' => 'Similarity / plagiarism detection for generated content.',
        'category' => 'ai_quality',
        'default' => false,
    ],
    'hallucination_guard' => [
        'description' => 'Anti-hallucination guards (validation, heuristics).',
        'category' => 'ai_quality',
        'default' => true,
    ],
    'tmdb_verification' => [
        'description' => 'TMDb API verification for movies and people before AI generation.',
        'category' => 'ai_quality',
        'default' => true,
    ],
    'tvmaze_verification' => [
        'description' => 'TVmaze API verification for TV series and TV shows before AI generation.',
        'category' => 'ai_quality',
        'default' => true,
    ],

    // Experimental Features
    'ai_use_toon_format' => [
        'description' => 'Experimental: Use TOON format for tabular data to save tokens.',
        'category' => 'experiments',
        'default' => false,
    ],

    // Admin / operations
    'debug_endpoints' => [
        'description' => 'Debug endpoints for service configuration inspection (e.g., /v1/admin/debug/config).',
        'category' => 'operations',
        'default' => false,
    ],
];

return [
    'default' => env('PENNANT_DEFAULT_STORE', 'database'),

    'stores' => [
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', null),
            'table' => 'features',
        ],
    ],

    'features' => array_map(
        fn ($flag) => $flag['default'],
        $flags
    ),

    'metadata' => $flags,
];
