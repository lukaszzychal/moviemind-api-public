<?php

namespace App\Helpers;

/**
 * Helper class for validating slug formats.
 * Helps prevent generation of data from random/invalid slugs.
 */
class SlugValidator
{
    /**
     * Validate if slug looks like a valid movie slug.
     *
     * @return array{valid: bool, confidence: float, reason: string}
     */
    public static function validateMovieSlug(string $slug): array
    {
        // Minimum length check
        if (strlen($slug) < 3) {
            return [
                'valid' => false,
                'confidence' => 0.0,
                'reason' => 'Slug too short (minimum 3 characters)',
            ];
        }

        // Check if slug is just numbers
        if (preg_match('/^\d+$/', $slug)) {
            return [
                'valid' => false,
                'confidence' => 0.1,
                'reason' => 'Slug contains only numbers',
            ];
        }

        // Check for common patterns indicating a movie slug
        $confidence = 0.5; // Base confidence

        // Pattern: title-year (e.g., "the-matrix-1999")
        if (preg_match('/^[a-z0-9-]+-(\d{4})$/', $slug, $matches)) {
            $year = (int) $matches[1];
            // Check if year is reasonable (1888 to current year + 2)
            if ($year >= 1888 && $year <= (int) date('Y') + 2) {
                return [
                    'valid' => true,
                    'confidence' => 0.9,
                    'reason' => 'Contains valid year format (title-year)',
                ];
            }
        }

        // Pattern: title-year-director (e.g., "inception-2010-christopher-nolan")
        if (preg_match('/^[a-z0-9-]+-(\d{4})-[a-z-]+$/', $slug, $matches)) {
            $year = (int) $matches[1];
            if ($year >= 1888 && $year <= (int) date('Y') + 2) {
                return [
                    'valid' => true,
                    'confidence' => 0.95,
                    'reason' => 'Contains valid year and director format',
                ];
            }
        }

        // Pattern: common movie words or patterns
        $movieKeywords = [
            'movie', 'film', 'cinema', 'director', 'actor', 'actress',
            'the', 'a', 'an', 'of', 'and', 'in', 'on', 'at',
        ];
        $slugWords = explode('-', $slug);
        $hasKeywords = count(array_intersect(array_map('strtolower', $slugWords), $movieKeywords)) > 0;

        if ($hasKeywords) {
            $confidence = 0.7;
        }

        // Check for random-looking patterns (consecutive numbers, single chars)
        $hasRandomPattern = preg_match('/\d{3,}/', $slug) || // 3+ consecutive digits
            preg_match('/^[a-z]-\d+$/', $slug) || // "a-123" pattern
            preg_match('/\d+[a-z]\d+/', $slug) || // "123a456" pattern
            preg_match('/^[a-z]{1,2}-?\d+$/', $slug) || // "abc-123", "a123" pattern
            preg_match('/^[a-z]+-\d{1,2}$/', $slug); // "random-12" (short number at end, not year)

        if ($hasRandomPattern) {
            $confidence -= 0.4; // More penalty for random patterns
        }

        // Check minimum word count (at least 2 words or one meaningful word)
        $wordCount = count(array_filter($slugWords, fn ($w) => strlen($w) > 2));
        if ($wordCount >= 2) {
            $confidence += 0.2;
        } elseif ($wordCount === 1 && strlen($slugWords[0]) >= 5) {
            $confidence += 0.1;
        }

        // Normalize confidence to 0-1 range
        $confidence = max(0.1, min(1.0, $confidence));

        return [
            'valid' => $confidence >= 0.5,
            'confidence' => round($confidence, 2),
            'reason' => $confidence >= 0.5 ? 'Slug format acceptable' : 'Slug format suspicious',
        ];
    }

    /**
     * Validate if slug looks like a valid person slug.
     *
     * @return array{valid: bool, confidence: float, reason: string}
     */
    public static function validatePersonSlug(string $slug): array
    {
        // Minimum length check
        if (strlen($slug) < 3) {
            return [
                'valid' => false,
                'confidence' => 0.0,
                'reason' => 'Slug too short (minimum 3 characters)',
            ];
        }

        // Check if slug is just numbers
        if (preg_match('/^\d+$/', $slug)) {
            return [
                'valid' => false,
                'confidence' => 0.1,
                'reason' => 'Slug contains only numbers',
            ];
        }

        $confidence = 0.5; // Base confidence

        // Pattern: firstname-lastname (e.g., "keanu-reeves")
        $slugWords = explode('-', $slug);
        $wordCount = count(array_filter($slugWords, fn ($w) => strlen($w) >= 2));

        // Person slugs typically have 2-4 parts (first, middle, last names)
        if ($wordCount >= 2 && $wordCount <= 4) {
            $confidence = 0.85;
        } elseif ($wordCount === 1 && strlen($slugWords[0]) >= 5) {
            $confidence = 0.6; // Single word, but reasonably long (might be mononym)
        }

        // Check for common name patterns (not just random chars)
        $hasValidPattern = ! preg_match('/^\d+$/', $slug) && // Not all numbers
            ! preg_match('/\d{3,}/', $slug) && // Not many consecutive digits
            ! preg_match('/^[a-z]-\d+$/', $slug); // Not "a-123" pattern

        if (! $hasValidPattern) {
            $confidence -= 0.4;
        }

        // Normalize confidence
        $confidence = max(0.1, min(1.0, $confidence));

        return [
            'valid' => $confidence >= 0.5,
            'confidence' => round($confidence, 2),
            'reason' => $confidence >= 0.5 ? 'Slug format acceptable' : 'Slug format suspicious',
        ];
    }
}
