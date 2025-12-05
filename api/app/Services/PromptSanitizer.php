<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Service for sanitizing user inputs before using them in AI prompts.
 *
 * Protects against prompt injection attacks by:
 * - Removing newlines and special characters
 * - Detecting suspicious patterns
 * - Escaping special characters
 * - Validating input length
 */
class PromptSanitizer
{
    private const MAX_SLUG_LENGTH = 255;

    private const MAX_TEXT_LENGTH = 10000;

    /**
     * Suspicious patterns that indicate potential prompt injection.
     *
     * @var array<string>
     */
    private array $suspiciousPatterns = [
        // Ignore/forget previous instructions (with spaces and hyphens)
        '/ignore[\s-]+(previous|all|all[\s-]+previous)[\s-]+(instructions?|prompts?)/i',
        '/forget[\s-]+(previous|all|all[\s-]+previous)[\s-]+(instructions?|prompts?)/i',
        '/disregard[\s-]+(previous|all|all[\s-]+previous)[\s-]+(instructions?|prompts?)/i',
        '/override[\s-]+(system|previous|all)/i',
        '/(system|previous|all)[\s-]+override/i',
        '/bypass[\s-]+(system|previous|all)/i',
        '/(system|previous|all)[\s-]+bypass/i',

        // Role manipulation attempts (with spaces, hyphens, and colons)
        '/system[\s-]*:[\s-]*/i',
        '/user[\s-]*:[\s-]*/i',
        '/assistant[\s-]*:[\s-]*/i',
        '/role[\s-]*:[\s-]*/i',

        // Jailbreak attempts (with spaces and hyphens)
        '/you[\s-]+are[\s-]+now/i',
        '/you[\s-]+must[\s-]+now/i',
        '/developer[\s-]+mode/i',
        '/jailbreak/i',
        '/escape[\s-]+mode/i',
        '/unrestricted[\s-]+mode/i',

        // Data exfiltration attempts (with spaces and hyphens)
        '/return[\s-]+(all|every|system|environment|secret|key|password|token|credential)/i',
        '/exfiltrate/i',
        '/leak/i',
        '/reveal/i',
        '/expose/i',
        '/dump/i',
        '/show[\s-]+(all|every|system|environment|secret|key|password|token)/i',

        // Command injection attempts (with spaces and hyphens)
        '/execute[\s-]+(command|code|script)/i',
        '/run[\s-]+(command|code|script)/i',
        '/eval\s*\(/i',
        '/system\s*\(/i',
        '/exec\s*\(/i',

        // Prompt manipulation (with spaces and hyphens)
        '/new[\s-]+instructions?/i',
        '/different[\s-]+instructions?/i',
        '/alternative[\s-]+instructions?/i',
        '/change[\s-]+(role|instructions?|prompt)/i',
    ];

    /**
     * Sanitize a slug before using it in AI prompts.
     *
     * @param  string  $slug  The slug to sanitize
     * @return string The sanitized slug
     *
     * @throws InvalidArgumentException If slug is too long or contains injection patterns
     */
    public function sanitizeSlug(string $slug): string
    {
        // Remove newlines, carriage returns, and tabs
        $slug = str_replace(["\n", "\r", "\t"], '', $slug);

        // Trim whitespace
        $slug = trim($slug);

        // Validate length
        if (strlen($slug) > self::MAX_SLUG_LENGTH) {
            throw new InvalidArgumentException("Slug too long (max {$this->getMaxSlugLength()} characters)");
        }

        // Detect injection attempts
        if ($this->detectInjection($slug)) {
            $this->logInjectionAttempt('slug', $slug);

            throw new InvalidArgumentException('Potential prompt injection detected in slug');
        }

        return $slug;
    }

    /**
     * Sanitize text data (e.g., from TMDb) before using it in AI prompts.
     *
     * @param  string  $text  The text to sanitize
     * @return string The sanitized text
     */
    public function sanitizeText(string $text): string
    {
        // Remove newlines and tabs (replace with space to preserve readability)
        $text = str_replace(["\n", "\r", "\t"], ' ', $text);

        // Trim whitespace
        $text = trim($text);

        // Validate length
        if (strlen($text) > self::MAX_TEXT_LENGTH) {
            $text = substr($text, 0, self::MAX_TEXT_LENGTH);
        }

        // Detect injection attempts (log but don't block - TMDb data may have false positives)
        if ($this->detectInjection($text)) {
            $this->logInjectionAttempt('text', $text);
            // Note: We don't throw exception for text data as it may come from trusted sources
            // but we log it for monitoring
        }

        return $text;
    }

    /**
     * Detect potential prompt injection in input string.
     *
     * @param  string  $input  The input to check
     * @return bool True if injection pattern detected, false otherwise
     */
    public function detectInjection(string $input): bool
    {
        $inputLower = strtolower($input);

        foreach ($this->suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $inputLower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log injection attempt for monitoring.
     *
     * @param  string  $type  Type of input ('slug' or 'text')
     * @param  string  $input  The suspicious input
     */
    private function logInjectionAttempt(string $type, string $input): void
    {
        $context = [
            'type' => $type,
            'input_preview' => substr($input, 0, 200),
            'input_length' => strlen($input),
        ];

        // Add request context if available (may not be available in unit tests)
        if (app()->bound('request')) {
            try {
                $request = request();
                $context['ip'] = $request->ip() ?? 'unknown';
                $context['user_agent'] = $request->userAgent() ?? 'unknown';
                $context['url'] = $request->fullUrl();
            } catch (\Throwable $e) {
                // Request not available (e.g., in unit tests)
                // Context will be logged without request info
            }
        }

        Log::warning('Prompt injection detected', $context);
    }

    /**
     * Get maximum allowed slug length.
     */
    public function getMaxSlugLength(): int
    {
        return self::MAX_SLUG_LENGTH;
    }

    /**
     * Get maximum allowed text length.
     */
    public function getMaxTextLength(): int
    {
        return self::MAX_TEXT_LENGTH;
    }
}
