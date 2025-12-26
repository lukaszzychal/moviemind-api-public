<?php

declare(strict_types=1);

namespace App\Features;

/**
 * Feature flag: Use TOON format for AI communication.
 *
 * When enabled, tabular data (lists of movies, people, etc.) will be sent
 * to OpenAI API in TOON format instead of JSON, potentially saving 30-60% tokens.
 *
 * ⚠️ EXPERIMENTAL: This is a developer flag for testing TOON format.
 * Requires validation with gpt-4o-mini to ensure proper parsing.
 */
class ai_use_toon_format extends BaseFeature {}
