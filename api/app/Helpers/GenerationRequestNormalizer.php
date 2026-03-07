<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\ContextTag;
use App\Enums\Locale;

class GenerationRequestNormalizer
{
    /**
     * Normalize locale string to canonical value (e.g. en-US) or null if invalid.
     */
    public static function normalizeLocale(?string $locale): ?string
    {
        if ($locale === null || $locale === '') {
            return null;
        }

        $candidate = str_replace('_', '-', $locale);
        $candidateLower = strtolower($candidate);

        foreach (Locale::cases() as $case) {
            if (strtolower($case->value) === $candidateLower) {
                return $case->value;
            }
        }

        return null;
    }

    /**
     * Normalize context tag string to canonical value or null if invalid.
     */
    public static function normalizeContextTag(?string $contextTag): ?string
    {
        if ($contextTag === null || $contextTag === '') {
            return null;
        }

        $candidateLower = strtolower($contextTag);

        foreach (ContextTag::cases() as $case) {
            if (strtolower($case->value) === $candidateLower) {
                return $case->value;
            }
        }

        return null;
    }

    /**
     * Convert confidence score to human-readable label.
     */
    public static function confidenceLabel(?float $confidence): string
    {
        if ($confidence === null) {
            return 'unknown';
        }

        return match (true) {
            $confidence >= 0.9 => 'high',
            $confidence >= 0.7 => 'medium',
            $confidence >= 0.5 => 'low',
            default => 'very_low',
        };
    }
}
