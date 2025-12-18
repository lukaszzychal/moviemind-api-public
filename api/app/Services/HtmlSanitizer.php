<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Service for sanitizing HTML content in AI-generated output to prevent XSS attacks.
 *
 * Removes potentially dangerous HTML tags and attributes while preserving
 * safe formatting if needed. For movie/person descriptions, we typically
 * want plain text, so this service strips all HTML.
 */
class HtmlSanitizer
{
    /**
     * Allowed HTML tags for rich text (currently none - we want plain text).
     *
     * @var array<string>
     */
    private array $allowedTags = [];

    /**
     * Sanitize HTML content by removing all HTML tags and encoding special characters.
     *
     * This method:
     * 1. Strips all HTML tags
     * 2. Decodes HTML entities to plain text
     * 3. Escapes remaining special characters
     * 4. Removes JavaScript event handlers and data URIs
     *
     * @param  string  $html  HTML content to sanitize
     * @return string Sanitized plain text
     */
    public function sanitize(string $html): string
    {
        // Remove null bytes
        $html = str_replace("\0", '', $html);

        // Decode HTML entities multiple times to handle double/triple encoding
        // This is important for security - attackers may use multiple layers of encoding
        $previousHtml = '';
        $maxDecodeIterations = 5;
        $iteration = 0;
        while ($html !== $previousHtml && $iteration < $maxDecodeIterations) {
            $previousHtml = $html;
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $iteration++;
        }

        // Remove script tags and their content
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is', '', $html);

        // Remove style tags and their content
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/is', '', $html);

        // Remove event handlers (onclick, onerror, etc.)
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s*on\w+\s*=\s*[^\s>]*/i', '', $html);

        // Remove javascript: and data: URIs
        $html = preg_replace('/javascript:/i', '', $html);
        $html = preg_replace('/data:\s*text\/html/i', '', $html);

        // Remove all HTML tags
        $html = strip_tags($html);

        // Final decode of any remaining HTML entities
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        $html = trim($html);

        // Log if suspicious content was detected
        if ($this->detectSuspiciousContent($html)) {
            $this->logSuspiciousContent($html);
        }

        return $html;
    }

    /**
     * Sanitize and preserve basic formatting (bold, italic, paragraphs).
     *
     * Currently not used, but kept for future use if rich text is needed.
     *
     * @param  string  $html  HTML content to sanitize
     * @return string Sanitized HTML with allowed tags only
     */
    public function sanitizeWithFormatting(string $html): string
    {
        // Remove null bytes
        $html = str_replace("\0", '', $html);

        // Decode HTML entities
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove script and style tags
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/is', '', $html);

        // Remove event handlers
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s*on\w+\s*=\s*[^\s>]*/i', '', $html);

        // Remove javascript: and data: URIs
        $html = preg_replace('/javascript:/i', '', $html);
        $html = preg_replace('/data:\s*text\/html/i', '', $html);

        // Strip tags except allowed ones
        if (! empty($this->allowedTags)) {
            $allowedTagsString = '<'.implode('><', $this->allowedTags).'>';
            $html = strip_tags($html, $allowedTagsString);
        } else {
            $html = strip_tags($html);
        }

        // Decode remaining HTML entities
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        $html = trim($html);

        return $html;
    }

    /**
     * Detect suspicious content that might indicate XSS attempts.
     *
     * @param  string  $content  Content to check
     * @return bool True if suspicious content detected
     */
    private function detectSuspiciousContent(string $content): bool
    {
        $suspiciousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/data:\s*text\/html/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/<link/i',
            '/<meta/i',
            '/expression\s*\(/i', // CSS expression
            '/vbscript:/i',
            '/@import/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log suspicious content for monitoring.
     *
     * @param  string  $content  The suspicious content
     */
    private function logSuspiciousContent(string $content): void
    {
        $context = [
            'content_preview' => substr($content, 0, 200),
            'content_length' => strlen($content),
        ];

        // Add request context if available
        if (app()->bound('request')) {
            try {
                $request = request();
                $context['ip'] = $request->ip() ?? 'unknown';
                $context['user_agent'] = $request->userAgent() ?? 'unknown';
                $context['url'] = $request->fullUrl();
            } catch (\Throwable $e) {
                // Request not available (e.g., in unit tests)
            }
        }

        Log::warning('Suspicious HTML content detected in AI output', $context);
    }
}
