<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\SlugValidator;

/**
 * Validates slugs before AI generation to prevent hallucinations.
 * Performs heuristic checks to identify suspicious or invalid slugs
 * before making expensive AI API calls.
 */
class PreGenerationValidator
{
    private const MIN_CONFIDENCE_THRESHOLD = 0.5;

    /**
     * Check if movie should be generated based on slug validation.
     *
     * @param  string  $slug  Movie slug to validate
     * @return array<string, mixed> Returns array with keys: 'should_generate' (bool), 'reason' (string), 'confidence' (float|null)
     */
    public function shouldGenerateMovie(string $slug): array
    {
        // Use SlugValidator to get confidence score
        $slugValidation = SlugValidator::validateMovieSlug($slug);

        // Low confidence = probably doesn't exist
        if ($slugValidation['confidence'] < self::MIN_CONFIDENCE_THRESHOLD) {
            return [
                'should_generate' => false,
                'reason' => 'Low confidence slug format: '.$slugValidation['reason'],
                'confidence' => $slugValidation['confidence'],
            ];
        }

        // Check release year validity (if present in slug) - before suspicious patterns
        $yearValidation = $this->validateReleaseYear($slug);
        if (! $yearValidation['valid']) {
            return [
                'should_generate' => false,
                'reason' => $yearValidation['reason'],
                'confidence' => $slugValidation['confidence'],
            ];
        }

        // Check for suspicious patterns (after year validation to avoid false positives)
        if ($this->isSuspiciousPattern($slug)) {
            return [
                'should_generate' => false,
                'reason' => 'Suspicious slug pattern detected',
                'confidence' => $slugValidation['confidence'],
            ];
        }

        return [
            'should_generate' => true,
            'confidence' => $slugValidation['confidence'],
            'reason' => $slugValidation['reason'],
        ];
    }

    /**
     * Check if person should be generated based on slug validation.
     *
     * @param  string  $slug  Person slug to validate
     * @return array<string, mixed> Returns array with keys: 'should_generate' (bool), 'reason' (string), 'confidence' (float|null)
     */
    public function shouldGeneratePerson(string $slug): array
    {
        // Use SlugValidator to get confidence score
        $slugValidation = SlugValidator::validatePersonSlug($slug);

        // Low confidence = probably doesn't exist
        if ($slugValidation['confidence'] < self::MIN_CONFIDENCE_THRESHOLD) {
            return [
                'should_generate' => false,
                'reason' => 'Low confidence slug format: '.$slugValidation['reason'],
                'confidence' => $slugValidation['confidence'],
            ];
        }

        // Check for suspicious patterns
        if ($this->isSuspiciousPattern($slug)) {
            return [
                'should_generate' => false,
                'reason' => 'Suspicious slug pattern detected',
                'confidence' => $slugValidation['confidence'],
            ];
        }

        // Check birth date validity (if present in slug - less common but possible)
        $birthDateValidation = $this->validateBirthDate($slug);
        if (! $birthDateValidation['valid']) {
            return [
                'should_generate' => false,
                'reason' => $birthDateValidation['reason'],
                'confidence' => $slugValidation['confidence'],
            ];
        }

        return [
            'should_generate' => true,
            'confidence' => $slugValidation['confidence'],
            'reason' => $slugValidation['reason'],
        ];
    }

    /**
     * Check if slug contains suspicious patterns that indicate test/fake data.
     *
     * @param  string  $slug  Slug to check
     * @return bool True if suspicious pattern detected
     */
    private function isSuspiciousPattern(string $slug): bool
    {
        // Patterns like: test-123, random-xyz-999, etc.
        // Note: We don't check for long digit sequences here as years (4 digits) are valid

        // First, extract and remove valid year patterns to avoid false positives
        $slugWithoutYear = preg_replace('/\b(18[89]\d|19\d{2}|20[0-3]\d)\b/', '', $slug);

        $suspiciousPatterns = [
            '/\b(test|random|xyz|abc|123|999|000|fake|dummy|example|sample)\b/i',
            '/^test-/i',
            '/-test$/i',
            '/\d{4,}/', // Long sequences of digits (after removing years)
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $slugWithoutYear) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate release year extracted from slug.
     *
     * @param  string  $slug  Movie slug to check
     * @return array<string, mixed> Returns array with keys: 'valid' (bool), 'reason' (string)
     */
    private function validateReleaseYear(string $slug): array
    {
        $yearPattern = '/\b(18[89]\d|19\d{2}|20[0-3]\d)\b/';
        if (preg_match($yearPattern, $slug, $matches) === 1) {
            $year = (int) $matches[1];
            $currentYear = (int) date('Y');
            $maxYear = $currentYear + 2; // Allow up to 2 years in future

            if ($year < 1888) {
                return [
                    'valid' => false,
                    'reason' => "Invalid release year: {$year} (before 1888, first movie was in 1888)",
                ];
            }

            if ($year > $maxYear) {
                return [
                    'valid' => false,
                    'reason' => "Invalid release year: {$year} (more than 2 years in future, max: {$maxYear})",
                ];
            }
        }

        return [
            'valid' => true,
            'reason' => 'Release year validation passed',
        ];
    }

    /**
     * Validate birth date extracted from slug (if present).
     *
     * @param  string  $slug  Person slug to check
     * @return array<string, mixed> Returns array with keys: 'valid' (bool), 'reason' (string)
     */
    private function validateBirthDate(string $slug): array
    {
        // Birth dates in slugs are less common, but we can check for suspicious patterns
        // Like very old dates or future dates
        $yearPattern = '/\b(1[0-7]\d{2}|18[0-4]\d|20\d{3})\b/'; // Years before 1850 or after 2000 (suspicious for birth dates)
        if (preg_match($yearPattern, $slug, $matches) === 1) {
            $year = (int) $matches[1];
            $currentYear = (int) date('Y');

            if ($year < 1850) {
                return [
                    'valid' => false,
                    'reason' => "Invalid birth year in slug: {$year} (before 1850, unlikely to be valid)",
                ];
            }

            if ($year > $currentYear) {
                return [
                    'valid' => false,
                    'reason' => "Invalid birth year in slug: {$year} (future date, not possible)",
                ];
            }
        }

        return [
            'valid' => true,
            'reason' => 'Birth date validation passed',
        ];
    }
}
