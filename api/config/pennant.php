<?php

declare(strict_types=1);

use App\Features\admin_bulk_regeneration;
use App\Features\admin_edit_lock;
use App\Features\admin_flag_console;
use App\Features\ai_bio_generation;
use App\Features\ai_description_generation;
use App\Features\ai_generation_baseline_locking;
use App\Features\ai_plagiarism_detection;
use App\Features\ai_quality_scoring;
use App\Features\ai_translation_pipeline;
use App\Features\ai_use_toon_format;
use App\Features\api_v1_deprecation_notice;
use App\Features\debug_endpoints;
use App\Features\description_style_packs;
use App\Features\generate_v2_pipeline;
use App\Features\glossary_enforcement;
use App\Features\hallucination_guard;
use App\Features\human_moderation_required;
use App\Features\locale_auto_detect;
use App\Features\multilingual_support;
use App\Features\prewarm_top_titles;
use App\Features\public_jobs_polling;
use App\Features\public_search_advanced;
use App\Features\rate_limit_free_plan;
use App\Features\recommendation_engine;
use App\Features\redis_cache_bios;
use App\Features\redis_cache_descriptions;
use App\Features\tmdb_verification;
use App\Features\toxicity_filter;
use App\Features\usage_analytics;
use App\Features\webhook_billing;

$flags = [
    // Core AI generation
    'ai_description_generation' => [
        'class' => ai_description_generation::class,
        'description' => 'Enables AI-generated movie/series descriptions.',
        'category' => 'core_ai',
        'default' => true,
        'togglable' => true,
    ],
    'ai_bio_generation' => [
        'class' => ai_bio_generation::class,
        'description' => 'Enables AI-generated person biographies.',
        'category' => 'core_ai',
        'default' => true,
        'togglable' => true,
    ],
    'ai_translation_pipeline' => [
        'class' => ai_translation_pipeline::class,
        'description' => 'Translation pipeline (translate-then-adapt).',
        'category' => 'localization',
        'default' => false,
        'togglable' => false,
    ],
    'ai_quality_scoring' => [
        'class' => ai_quality_scoring::class,
        'description' => 'Internal quality scoring for AI-generated content.',
        'category' => 'ai_quality',
        'default' => false,
        'togglable' => false,
    ],
    'ai_plagiarism_detection' => [
        'class' => ai_plagiarism_detection::class,
        'description' => 'Similarity / plagiarism detection for generated content.',
        'category' => 'ai_quality',
        'default' => false,
        'togglable' => false,
    ],
    'ai_generation_baseline_locking' => [
        'class' => ai_generation_baseline_locking::class,
        'description' => 'Locking + baseline update flow for AI generation (TASK-012).',
        'category' => 'core_ai',
        'default' => false,
        'togglable' => true,
    ],

    // Experiments
    'generate_v2_pipeline' => [
        'class' => generate_v2_pipeline::class,
        'description' => 'Experimental v2 generation flow.',
        'category' => 'experiments',
        'default' => false,
        'togglable' => false,
    ],
    'description_style_packs' => [
        'class' => description_style_packs::class,
        'description' => 'Additional description style packs (modern, critical, playful, ...).',
        'category' => 'experiments',
        'default' => false,
        'togglable' => false,
    ],
    'ai_use_toon_format' => [
        'class' => ai_use_toon_format::class,
        'description' => 'Experimental: Use TOON format for tabular data (lists) to save tokens. WIP - requires validation.',
        'category' => 'experiments',
        'default' => false,
        'togglable' => false,
    ],

    // Moderation & safety
    'hallucination_guard' => [
        'class' => hallucination_guard::class,
        'description' => 'Anti-hallucination guards (validation, heuristics).',
        'category' => 'moderation',
        'default' => true,
        'togglable' => false,
    ],
    'toxicity_filter' => [
        'class' => toxicity_filter::class,
        'description' => 'Toxic / NSFW content filtering.',
        'category' => 'moderation',
        'default' => true,
        'togglable' => false,
    ],
    'human_moderation_required' => [
        'class' => human_moderation_required::class,
        'description' => 'Require manual moderation before publishing.',
        'category' => 'moderation',
        'default' => false,
        'togglable' => true,
    ],
    'tmdb_verification' => [
        'class' => tmdb_verification::class,
        'description' => 'TMDb API verification for movies and people before AI generation. Verifies entity existence in TMDb before queueing AI generation job.',
        'category' => 'moderation',
        'default' => true,
        'togglable' => true,
    ],

    // Performance & cache
    'redis_cache_descriptions' => [
        'class' => redis_cache_descriptions::class,
        'description' => 'Cache movie descriptions in Redis.',
        'category' => 'performance',
        'default' => true,
        'togglable' => false,
    ],
    'redis_cache_bios' => [
        'class' => redis_cache_bios::class,
        'description' => 'Cache person biographies in Redis.',
        'category' => 'performance',
        'default' => true,
        'togglable' => false,
    ],
    'prewarm_top_titles' => [
        'class' => prewarm_top_titles::class,
        'description' => 'Pre-warm cache for top titles.',
        'category' => 'performance',
        'default' => false,
        'togglable' => false,
    ],

    // Internationalisation
    'multilingual_support' => [
        'class' => multilingual_support::class,
        'description' => 'Global enablement of multilingual support.',
        'category' => 'i18n',
        'default' => false,
        'togglable' => false,
    ],
    'locale_auto_detect' => [
        'class' => locale_auto_detect::class,
        'description' => 'Automatic locale detection for requests.',
        'category' => 'i18n',
        'default' => false,
        'togglable' => false,
    ],
    'glossary_enforcement' => [
        'class' => glossary_enforcement::class,
        'description' => 'Glossary enforcement (non-translatable terms).',
        'category' => 'i18n',
        'default' => false,
        'togglable' => false,
    ],

    // Public API
    'public_search_advanced' => [
        'class' => public_search_advanced::class,
        'description' => 'Advanced search (fuzzy, aliases, embeddings).',
        'category' => 'public_api',
        'default' => false,
        'togglable' => false,
    ],
    'public_jobs_polling' => [
        'class' => public_jobs_polling::class,
        'description' => 'Polling job statuses via public API.',
        'category' => 'public_api',
        'default' => true,
        'togglable' => true,
    ],
    'api_v1_deprecation_notice' => [
        'class' => api_v1_deprecation_notice::class,
        'description' => 'API v1 deprecation notices (progressive rollout).',
        'category' => 'public_api',
        'default' => false,
        'togglable' => false,
    ],

    // Billing & analytics
    'rate_limit_free_plan' => [
        'class' => rate_limit_free_plan::class,
        'description' => 'Additional rate limits for the Free plan.',
        'category' => 'billing',
        'default' => true,
        'togglable' => false,
    ],
    'webhook_billing' => [
        'class' => webhook_billing::class,
        'description' => 'Billing webhooks (RapidAPI/Stripe etc.).',
        'category' => 'billing',
        'default' => false,
        'togglable' => false,
    ],
    'usage_analytics' => [
        'class' => usage_analytics::class,
        'description' => 'Collect and report API usage analytics.',
        'category' => 'analytics',
        'default' => true,
        'togglable' => false,
    ],

    // Recommendations
    'recommendation_engine' => [
        'class' => recommendation_engine::class,
        'description' => 'Recommendations and similarity engine.',
        'category' => 'recommendations',
        'default' => false,
        'togglable' => false,
    ],

    // Admin / operations
    'admin_flag_console' => [
        'class' => admin_flag_console::class,
        'description' => 'Admin console/endpoints for managing flags.',
        'category' => 'operations',
        'default' => false,
        'togglable' => false,
    ],
    'admin_bulk_regeneration' => [
        'class' => admin_bulk_regeneration::class,
        'description' => 'Bulk content regenerations (admin operations).',
        'category' => 'operations',
        'default' => false,
        'togglable' => false,
    ],
    'admin_edit_lock' => [
        'class' => admin_edit_lock::class,
        'description' => 'Edit locks during batch / admin actions.',
        'category' => 'operations',
        'default' => false,
        'togglable' => false,
    ],
    'debug_endpoints' => [
        'class' => debug_endpoints::class,
        'description' => 'Debug endpoints for service configuration inspection (e.g., /v1/admin/debug/config).',
        'category' => 'operations',
        'default' => false,
        'togglable' => true,
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
        'array' => [
            'driver' => 'array',
        ],
    ],

    'features' => array_map(
        static fn (array $flag): string => $flag['class'],
        $flags
    ),

    'flags' => $flags,
];
