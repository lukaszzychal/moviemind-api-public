<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Rate Limits (requests per minute)
    |--------------------------------------------------------------------------
    |
    | Default rate limits for each endpoint when system is not under load.
    | These are the maximum limits that will be applied under normal conditions.
    |
    */
    'defaults' => [
        'search' => 100,      // /api/v1/movies/search, /api/v1/people/search
        'show' => 120,        // /api/v1/movies/{slug}, /api/v1/people/{slug} - Higher limit (simpler query)
        'bulk' => 30,         // /api/v1/movies/bulk - Lower limit (multiple movies per request)
        'generate' => 10,     // /api/v1/generate
        'report' => 20,       // /api/v1/movies/{slug}/report, /api/v1/people/{slug}/report (when implemented)
    ],

    /*
    |--------------------------------------------------------------------------
    | Minimum Rate Limits (requests per minute)
    |--------------------------------------------------------------------------
    |
    | Minimum rate limits that will be applied even under heavy load.
    | These ensure the API remains accessible even during high load.
    |
    */
    'min' => [
        'search' => 20,       // Minimum 20 req/min even under heavy load (movies/search, people/search)
        'show' => 30,         // Minimum 30 req/min even under heavy load (movies/{slug}, people/{slug})
        'bulk' => 5,          // Minimum 5 req/min even under heavy load (movies/bulk)
        'generate' => 2,      // Minimum 2 req/min even under heavy load (generate)
        'report' => 5,        // Minimum 5 req/min even under heavy load (movies/{slug}/report, people/{slug}/report)
    ],

    /*
    |--------------------------------------------------------------------------
    | Load Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for determining system load levels.
    | Load factor is calculated as weighted average of CPU, queue, and active jobs.
    |
    */
    'thresholds' => [
        'low' => 0.3,         // Below 30% = low load (use default limits)
        'medium' => 0.5,      // 30-50% = medium load (reduce by 20%)
        'high' => 0.7,        // 50-70% = high load (reduce by 50%)
        'critical' => 0.9,    // Above 70% = critical load (use min limits)
    ],

    /*
    |--------------------------------------------------------------------------
    | Load Factor Weights
    |--------------------------------------------------------------------------
    |
    | Weights for calculating load factor from different metrics.
    | Total should sum to 1.0 (100%).
    |
    */
    'weights' => [
        'cpu' => 0.4,         // 40% weight for CPU load
        'queue' => 0.4,       // 40% weight for queue size
        'active_jobs' => 0.2, // 20% weight for active jobs
    ],

    /*
    |--------------------------------------------------------------------------
    | CPU Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for CPU load monitoring.
    |
    */
    'cpu' => [
        'enabled' => true,    // Enable CPU load monitoring
        'cores' => 4,         // Number of CPU cores (for normalization)
        'max_load' => 4.0,    // Maximum expected load (for normalization)
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for queue size monitoring.
    |
    */
    'queue' => [
        'enabled' => true,    // Enable queue size monitoring
        'connection' => 'redis', // Redis connection name
        'queue_name' => 'default', // Queue name to monitor
        'max_size' => 1000,   // Maximum expected queue size (for normalization)
    ],

    /*
    |--------------------------------------------------------------------------
    | Active Jobs Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for active jobs monitoring (Horizon).
    |
    */
    'active_jobs' => [
        'enabled' => true,    // Enable active jobs monitoring
        'max_jobs' => 100,    // Maximum expected active jobs (for normalization)
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Reduction Factors
    |--------------------------------------------------------------------------
    |
    | Factors by which to reduce rate limits at different load levels.
    | Applied as multiplier (e.g., 0.8 = 80% of default = 20% reduction).
    |
    */
    'reduction_factors' => [
        'low' => 1.0,         // No reduction (100% of default)
        'medium' => 0.8,      // 20% reduction (80% of default)
        'high' => 0.5,        // 50% reduction (50% of default)
        'critical' => 0.0,    // Use minimum limits (0.0 = ignore default, use min)
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for logging rate limit changes.
    |
    */
    'logging' => [
        'enabled' => true,    // Enable logging of rate limit changes
        'channel' => 'default', // Log channel
        'level' => 'info',    // Log level (info, warning, etc.)
    ],
];
