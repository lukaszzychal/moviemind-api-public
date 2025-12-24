<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Services\PromptSanitizer;

class SlugValidator
{
    /**
     * Validate movie slug format and return confidence score.
     *
     * @return array{valid: bool, confidence: float, reason: string}
     */
    public static function validateMovieSlug(string $slug): array
    {
        // Check for prompt injection first (highest priority security check)
        $promptSanitizer = app(PromptSanitizer::class);
        if ($promptSanitizer->detectInjection($slug)) {
            return [
                'valid' => false,
                'confidence' => 0.0,
                'reason' => 'Potential prompt injection detected',
            ];
        }

        if (strlen($slug) < 3) {
            return [
                'valid' => false,
                'confidence' => 0.0,
                'reason' => 'Slug too short (minimum 3 characters)',
            ];
        }

        if (preg_match('/\b(18[89]\d|19\d{2}|20[0-3]\d)\b/', $slug, $matches)) {
            $year = (int) $matches[1];
            $currentYear = (int) date('Y');

            if ($year >= 1888 && $year <= $currentYear + 2) {
                return [
                    'valid' => true,
                    'confidence' => 0.9,
                    'reason' => 'Slug contains valid year format (title-year)',
                ];
            }
        }

        if (preg_match('/\b[a-z]{1,3}-\d{2,}\b/i', $slug)) {
            return [
                'valid' => false,
                'confidence' => 0.4,
                'reason' => 'Slug format suspicious (random pattern detected)',
            ];
        }

        if (preg_match('/^\d+$/', $slug)) {
            return [
                'valid' => false,
                'confidence' => 0.1,
                'reason' => 'Slug contains only digits',
            ];
        }

        if (preg_match('/^[a-z0-9-]+$/i', $slug) && strlen($slug) >= 5) {
            return [
                'valid' => true,
                'confidence' => 0.6,
                'reason' => 'Slug looks like a title but no year detected',
            ];
        }

        return [
            'valid' => false,
            'confidence' => 0.3,
            'reason' => 'Slug does not match expected movie slug format',
        ];
    }

    /**
     * Validate person slug format and return confidence score.
     *
     * @return array{valid: bool, confidence: float, reason: string}
     */
    public static function validatePersonSlug(string $slug): array
    {
        // Check for prompt injection first (highest priority security check)
        $promptSanitizer = app(PromptSanitizer::class);
        if ($promptSanitizer->detectInjection($slug)) {
            return [
                'valid' => false,
                'confidence' => 0.0,
                'reason' => 'Potential prompt injection detected',
            ];
        }

        if (strlen($slug) < 3) {
            return [
                'valid' => false,
                'confidence' => 0.0,
                'reason' => 'Slug too short (minimum 3 characters)',
            ];
        }

        if (preg_match('/\b[a-z]{1,3}-\d{2,}\b/i', $slug)) {
            return [
                'valid' => false,
                'confidence' => 0.1,
                'reason' => 'Slug format suspicious (random pattern detected)',
            ];
        }

        if (preg_match('/^\d+$/', $slug)) {
            return [
                'valid' => false,
                'confidence' => 0.1,
                'reason' => 'Slug contains only digits',
            ];
        }

        $words = explode('-', $slug);
        $wordCount = count($words);

        if ($wordCount >= 2 && $wordCount <= 4) {
            return [
                'valid' => true,
                'confidence' => 0.85,
                'reason' => 'Slug matches name format (2-4 words)',
            ];
        }

        if ($wordCount === 1 && strlen($slug) >= 5) {
            return [
                'valid' => true,
                'confidence' => 0.6,
                'reason' => 'Slug is a single word (possible mononym)',
            ];
        }

        return [
            'valid' => false,
            'confidence' => 0.3,
            'reason' => 'Slug does not match expected person slug format',
        ];
    }

    /**
     * Validate TV series slug format and return confidence score.
     *
     * @return array{valid: bool, confidence: float, reason: string}
     */
    public static function validateTvSeriesSlug(string $slug): array
    {
        // Check for prompt injection first (highest priority security check)
        $promptSanitizer = app(PromptSanitizer::class);
        if ($promptSanitizer->detectInjection($slug)) {
            return [
                'valid' => false,
                'confidence' => 0.0,
                'reason' => 'Potential prompt injection detected',
            ];
        }

        if (strlen($slug) < 3) {
            return [
                'valid' => false,
                'confidence' => 0.0,
                'reason' => 'Slug too short (minimum 3 characters)',
            ];
        }

        if (preg_match('/\b(19\d{2}|20[0-3]\d)\b/', $slug, $matches)) {
            $year = (int) $matches[1];
            $currentYear = (int) date('Y');

            if ($year >= 1950 && $year <= $currentYear + 2) {
                return [
                    'valid' => true,
                    'confidence' => 0.9,
                    'reason' => 'Slug contains valid year format (title-year)',
                ];
            }
        }

        if (preg_match('/\b[a-z]{1,3}-\d{2,}\b/i', $slug)) {
            return [
                'valid' => false,
                'confidence' => 0.4,
                'reason' => 'Slug format suspicious (random pattern detected)',
            ];
        }

        if (preg_match('/^\d+$/', $slug)) {
            return [
                'valid' => false,
                'confidence' => 0.1,
                'reason' => 'Slug contains only digits',
            ];
        }

        if (preg_match('/^[a-z0-9-]+$/i', $slug) && strlen($slug) >= 5) {
            return [
                'valid' => true,
                'confidence' => 0.6,
                'reason' => 'Slug looks like a title but no year detected',
            ];
        }

        return [
            'valid' => false,
            'confidence' => 0.3,
            'reason' => 'Slug does not match expected TV series slug format',
        ];
    }

    /**
     * Validate TV show slug format and return confidence score.
     *
     * @return array{valid: bool, confidence: float, reason: string}
     */
    public static function validateTvShowSlug(string $slug): array
    {
        // Check for prompt injection first (highest priority security check)
        $promptSanitizer = app(PromptSanitizer::class);
        if ($promptSanitizer->detectInjection($slug)) {
            return [
                'valid' => false,
                'confidence' => 0.0,
                'reason' => 'Potential prompt injection detected',
            ];
        }

        if (strlen($slug) < 3) {
            return [
                'valid' => false,
                'confidence' => 0.0,
                'reason' => 'Slug too short (minimum 3 characters)',
            ];
        }

        if (preg_match('/\b(19\d{2}|20[0-3]\d)\b/', $slug, $matches)) {
            $year = (int) $matches[1];
            $currentYear = (int) date('Y');

            if ($year >= 1950 && $year <= $currentYear + 2) {
                return [
                    'valid' => true,
                    'confidence' => 0.9,
                    'reason' => 'Slug contains valid year format (title-year)',
                ];
            }
        }

        if (preg_match('/\b[a-z]{1,3}-\d{2,}\b/i', $slug)) {
            return [
                'valid' => false,
                'confidence' => 0.4,
                'reason' => 'Slug format suspicious (random pattern detected)',
            ];
        }

        if (preg_match('/^\d+$/', $slug)) {
            return [
                'valid' => false,
                'confidence' => 0.1,
                'reason' => 'Slug contains only digits',
            ];
        }

        if (preg_match('/^[a-z0-9-]+$/i', $slug) && strlen($slug) >= 5) {
            return [
                'valid' => true,
                'confidence' => 0.6,
                'reason' => 'Slug looks like a title but no year detected',
            ];
        }

        return [
            'valid' => false,
            'confidence' => 0.3,
            'reason' => 'Slug does not match expected TV show slug format',
        ];
    }
}
