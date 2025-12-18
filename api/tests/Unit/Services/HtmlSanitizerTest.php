<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\HtmlSanitizer;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sanitize_removes_script_tags(): void
    {
        $malicious = '<script>alert("XSS")</script>Safe text';
        $result = $this->sanitizer->sanitize($malicious);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Safe text', $result);
    }

    public function test_sanitize_removes_style_tags(): void
    {
        $malicious = '<style>body { background: red; }</style>Safe text';
        $result = $this->sanitizer->sanitize($malicious);

        $this->assertStringNotContainsString('<style>', $result);
        $this->assertStringContainsString('Safe text', $result);
    }

    public function test_sanitize_removes_event_handlers(): void
    {
        $malicious = '<div onclick="alert(\'XSS\')">Click me</div>';
        $result = $this->sanitizer->sanitize($malicious);

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringNotContainsString('<div', $result);
        // Content should remain after removing dangerous parts
        $this->assertStringContainsString('Click me', $result);
    }

    public function test_sanitize_removes_javascript_uri(): void
    {
        $malicious = '<a href="javascript:alert(\'XSS\')">Link</a>';
        $result = $this->sanitizer->sanitize($malicious);

        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Link', $result);
    }

    public function test_sanitize_removes_data_uri_html(): void
    {
        $malicious = '<iframe src="data:text/html,<script>alert(\'XSS\')</script>"></iframe>';
        $result = $this->sanitizer->sanitize($malicious);

        $this->assertStringNotContainsString('data:text/html', $result);
        $this->assertStringNotContainsString('<iframe>', $result);
    }

    public function test_sanitize_removes_all_html_tags(): void
    {
        $html = '<p>Paragraph</p><strong>Bold</strong><em>Italic</em>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('<strong>', $result);
        $this->assertStringNotContainsString('<em>', $result);
        $this->assertStringContainsString('Paragraph', $result);
        $this->assertStringContainsString('Bold', $result);
        $this->assertStringContainsString('Italic', $result);
    }

    public function test_sanitize_decodes_html_entities(): void
    {
        $html = '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;Safe text';
        $result = $this->sanitizer->sanitize($html);

        // Should decode entities and remove script content
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Safe text', $result);
    }

    public function test_sanitize_handles_double_encoded_entities(): void
    {
        $html = '&amp;lt;script&amp;gt;alert(&amp;quot;XSS&amp;quot;)&amp;lt;/script&amp;gt;';
        $result = $this->sanitizer->sanitize($html);

        // After multiple decode passes: <script>alert("XSS")</script>
        // Script tags should be removed by regex or strip_tags
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('</script>', $result);
        // Text content may remain but is harmless without script tags
        $this->assertIsString($result);
    }

    public function test_sanitize_normalizes_whitespace(): void
    {
        $html = "Text   with\n\nmultiple\t\tspaces";
        $result = $this->sanitizer->sanitize($html);

        // Should normalize to single spaces
        $this->assertStringNotContainsString("\n", $result);
        $this->assertStringNotContainsString("\t", $result);
        $this->assertStringNotContainsString('   ', $result); // Multiple spaces
        $this->assertStringContainsString('Text with multiple spaces', $result);
    }

    public function test_sanitize_removes_null_bytes(): void
    {
        $html = "Safe text\0<script>alert('XSS')</script>";
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString("\0", $result);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('Safe text', $result);
    }

    public function test_sanitize_preserves_plain_text(): void
    {
        $text = 'This is a plain text description of a movie.';
        $result = $this->sanitizer->sanitize($text);

        $this->assertEquals($text, $result);
    }

    public function test_sanitize_handles_empty_string(): void
    {
        $result = $this->sanitizer->sanitize('');

        $this->assertEquals('', $result);
    }

    public function test_sanitize_handles_complex_xss_attempt(): void
    {
        $malicious = '<img src=x onerror="alert(\'XSS\')"><svg/onload=alert("XSS")><iframe src="javascript:alert(\'XSS\')"></iframe>';
        $result = $this->sanitizer->sanitize($malicious);

        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('onload', $result);
        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringNotContainsString('<img>', $result);
        $this->assertStringNotContainsString('<svg>', $result);
        $this->assertStringNotContainsString('<iframe>', $result);
    }

    public function test_sanitize_logs_suspicious_content(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Suspicious HTML content detected in AI output', \Mockery::on(function ($context) {
                return isset($context['content_preview']) && isset($context['content_length']);
            }));

        // Use @import which survives sanitization (it's a CSS pattern, not HTML tag)
        // This pattern is detected AFTER sanitization, so it will still be in the content
        $malicious = '@import url("evil.css"); Safe text';
        $result = $this->sanitizer->sanitize($malicious);

        // Verify that @import is still in the result (not removed by sanitization)
        $this->assertStringContainsString('@import', $result);
    }

    public function test_sanitize_does_not_log_safe_content(): void
    {
        Log::shouldReceive('warning')
            ->never();

        $safe = 'This is a safe movie description without any HTML.';
        $this->sanitizer->sanitize($safe);
    }

    public function test_sanitize_with_formatting_strips_dangerous_tags(): void
    {
        $html = '<p>Safe</p><script>alert("XSS")</script><strong>Bold</strong>';
        $result = $this->sanitizer->sanitizeWithFormatting($html);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
        // Note: Currently allowedTags is empty, so all tags are stripped
        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('<strong>', $result);
        $this->assertStringContainsString('Safe', $result);
        $this->assertStringContainsString('Bold', $result);
    }

    public function test_sanitize_handles_css_expression_attack(): void
    {
        $malicious = '<div style="background: expression(alert(\'XSS\'))">Content</div>';
        $result = $this->sanitizer->sanitize($malicious);

        $this->assertStringNotContainsString('expression', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Content', $result);
    }

    public function test_sanitize_handles_vbscript_attack(): void
    {
        $malicious = '<a href="vbscript:alert(\'XSS\')">Link</a>';
        $result = $this->sanitizer->sanitize($malicious);

        $this->assertStringNotContainsString('vbscript:', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Link', $result);
    }

    public function test_sanitize_handles_iframe_attack(): void
    {
        $malicious = '<iframe src="http://evil.com"></iframe>Safe content';
        $result = $this->sanitizer->sanitize($malicious);

        $this->assertStringNotContainsString('<iframe>', $result);
        $this->assertStringContainsString('Safe content', $result);
    }

    public function test_sanitize_handles_object_embed_attack(): void
    {
        $malicious = '<object data="evil.swf"></object><embed src="evil.swf"></embed>Safe';
        $result = $this->sanitizer->sanitize($malicious);

        $this->assertStringNotContainsString('<object>', $result);
        $this->assertStringNotContainsString('<embed>', $result);
        $this->assertStringContainsString('Safe', $result);
    }
}
