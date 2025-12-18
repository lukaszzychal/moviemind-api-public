<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Service for validating and sanitizing AI-generated output.
 *
 * Provides comprehensive validation including:
 * - HTML/XSS sanitization
 * - Output format validation
 * - Anti-hallucination checks (similarity to source)
 * - AI injection detection in output
 */
class AiOutputValidator
{
    private const MIN_DESCRIPTION_LENGTH = 50;

    private const MAX_DESCRIPTION_LENGTH = 5000;

    private const MIN_SIMILARITY_THRESHOLD = 0.3; // Minimum similarity to TMDb overview (anti-hallucination)

    private const MAX_SIMILARITY_THRESHOLD = 0.95; // Maximum similarity (shouldn't copy TMDb exactly)

    private HtmlSanitizer $htmlSanitizer;

    private PromptSanitizer $promptSanitizer;

    public function __construct(HtmlSanitizer $htmlSanitizer, PromptSanitizer $promptSanitizer)
    {
        $this->htmlSanitizer = $htmlSanitizer;
        $this->promptSanitizer = $promptSanitizer;
    }

    /**
     * Sanitize and validate AI-generated description.
     *
     * @param  string  $description  Raw AI-generated description
     * @param  array{title: string, release_date: string, overview: string, id: int, director?: string}|null  $tmdbData  Optional TMDb data for anti-hallucination check
     * @return array{valid: bool, sanitized: string, errors: array<string>, warnings: array<string>}
     */
    public function validateAndSanitizeDescription(string $description, ?array $tmdbData = null): array
    {
        $errors = [];
        $warnings = [];

        // 1. Sanitize HTML/XSS
        $sanitized = $this->htmlSanitizer->sanitize($description);

        // 2. Validate length
        $length = strlen(trim($sanitized));
        if ($length < self::MIN_DESCRIPTION_LENGTH) {
            $errors[] = "Description too short: {$length} characters (minimum: ".self::MIN_DESCRIPTION_LENGTH.')';
        }

        if ($length > self::MAX_DESCRIPTION_LENGTH) {
            $errors[] = "Description too long: {$length} characters (maximum: ".self::MAX_DESCRIPTION_LENGTH.')';
        }

        // 3. Detect AI injection in output
        if ($this->promptSanitizer->detectInjection($sanitized)) {
            $warnings[] = 'Potential AI injection detected in output';
            Log::warning('AI injection detected in AI output', [
                'description_preview' => substr($sanitized, 0, 200),
                'description_length' => $length,
            ]);
        }

        // 4. Anti-hallucination: Check similarity to TMDb overview
        if ($tmdbData !== null && ! empty($tmdbData['overview'])) {
            $similarity = $this->calculateSimilarity($sanitized, $tmdbData['overview']);

            // Too similar = likely copied from TMDb (should be original)
            if ($similarity > self::MAX_SIMILARITY_THRESHOLD) {
                $warnings[] = 'Description too similar to TMDb overview (similarity: '.number_format($similarity, 2).') - may not be original';
            }

            // Too different = possible hallucination (should be somewhat related)
            if ($similarity < self::MIN_SIMILARITY_THRESHOLD) {
                $warnings[] = 'Description too different from TMDb overview (similarity: '.number_format($similarity, 2).') - possible hallucination';
            }
        }

        // 5. Check for suspicious patterns
        if ($this->containsSuspiciousPatterns($sanitized)) {
            $warnings[] = 'Suspicious patterns detected in description';
        }

        return [
            'valid' => empty($errors),
            'sanitized' => $sanitized,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Calculate similarity between two texts using word overlap and Levenshtein distance.
     *
     * @param  string  $text1  First text
     * @param  string  $text2  Second text
     * @return float Similarity score between 0.0 and 1.0
     */
    private function calculateSimilarity(string $text1, string $text2): float
    {
        // Normalize texts
        $text1Normalized = $this->normalizeText($text1);
        $text2Normalized = $this->normalizeText($text2);

        $len1 = strlen($text1Normalized);
        $len2 = strlen($text2Normalized);

        if ($len1 === 0 && $len2 === 0) {
            return 1.0; // Both empty = identical
        }

        if ($len1 === 0 || $len2 === 0) {
            return 0.0; // One empty, one not = no similarity
        }

        // Word overlap similarity
        $words1 = array_filter(explode(' ', $text1Normalized), fn ($w) => strlen($w) >= 3);
        $words2 = array_filter(explode(' ', $text2Normalized), fn ($w) => strlen($w) >= 3);

        $commonWords = count(array_intersect($words1, $words2));
        $totalWords = count(array_unique(array_merge($words1, $words2)));

        $wordSimilarity = $totalWords > 0 ? (float) ($commonWords / $totalWords) : 0.0;
        $wordSimilarity = max(0.0, min(1.0, $wordSimilarity)); // Ensure between 0 and 1

        // Levenshtein distance similarity
        $maxLength = max($len1, $len2);
        $distance = levenshtein($text1Normalized, $text2Normalized);
        $levenshteinSimilarity = max(0.0, min(1.0, 1 - ($distance / $maxLength)));

        // Combined similarity (weighted average)
        return ($wordSimilarity * 0.6) + ($levenshteinSimilarity * 0.4);
    }

    /**
     * Normalize text for comparison.
     *
     * @param  string  $text  Text to normalize
     * @return string Normalized text
     */
    private function normalizeText(string $text): string
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove punctuation
        $text = preg_replace('/[^\w\s]/', ' ', $text);

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Check for suspicious patterns in description.
     *
     * @param  string  $description  Description to check
     * @return bool True if suspicious patterns detected
     */
    private function containsSuspiciousPatterns(string $description): bool
    {
        $suspiciousPatterns = [
            '/ignore\s+(previous|all|all\s+previous)\s+(instructions?|prompts?)/i',
            '/forget\s+(previous|all|all\s+previous)\s+(instructions?|prompts?)/i',
            '/system\s*:\s*/i',
            '/user\s*:\s*/i',
            '/assistant\s*:\s*/i',
            '/you\s+are\s+now/i',
            '/developer\s+mode/i',
            '/jailbreak/i',
            '/return\s+(all|every|system|environment|secret|key|password|token)/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $description) === 1) {
                return true;
            }
        }

        return false;
    }
}
