<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\GenerationRequestNormalizer;
use Tests\TestCase;

class GenerationRequestNormalizerTest extends TestCase
{
    public function test_normalize_locale_returns_null_for_null(): void
    {
        $this->assertNull(GenerationRequestNormalizer::normalizeLocale(null));
    }

    public function test_normalize_locale_returns_null_for_empty_string(): void
    {
        $this->assertNull(GenerationRequestNormalizer::normalizeLocale(''));
    }

    public function test_normalize_locale_returns_en_us_for_valid_en_us(): void
    {
        $this->assertSame('en-US', GenerationRequestNormalizer::normalizeLocale('en-US'));
    }

    public function test_normalize_locale_returns_pl_pl_for_valid_pl_pl(): void
    {
        $this->assertSame('pl-PL', GenerationRequestNormalizer::normalizeLocale('pl-PL'));
    }

    public function test_normalize_locale_accepts_underscore_and_returns_canonical(): void
    {
        $this->assertSame('en-US', GenerationRequestNormalizer::normalizeLocale('en_US'));
    }

    public function test_normalize_locale_returns_null_for_invalid(): void
    {
        $this->assertNull(GenerationRequestNormalizer::normalizeLocale('INVALID'));
    }

    public function test_normalize_locale_is_case_insensitive(): void
    {
        $this->assertSame('en-US', GenerationRequestNormalizer::normalizeLocale('EN-us'));
        $this->assertSame('pl-PL', GenerationRequestNormalizer::normalizeLocale('PL-pl'));
    }

    public function test_normalize_context_tag_returns_null_for_null(): void
    {
        $this->assertNull(GenerationRequestNormalizer::normalizeContextTag(null));
    }

    public function test_normalize_context_tag_returns_null_for_empty_string(): void
    {
        $this->assertNull(GenerationRequestNormalizer::normalizeContextTag(''));
    }

    public function test_normalize_context_tag_returns_modern_for_valid(): void
    {
        $this->assertSame('modern', GenerationRequestNormalizer::normalizeContextTag('modern'));
    }

    public function test_normalize_context_tag_is_case_insensitive(): void
    {
        $this->assertSame('modern', GenerationRequestNormalizer::normalizeContextTag('MODERN'));
    }

    public function test_normalize_context_tag_returns_null_for_invalid(): void
    {
        $this->assertNull(GenerationRequestNormalizer::normalizeContextTag('invalid-tag'));
    }

    public function test_normalize_context_tag_returns_default_value(): void
    {
        $this->assertSame('DEFAULT', GenerationRequestNormalizer::normalizeContextTag('default'));
    }

    public function test_confidence_label_returns_unknown_for_null(): void
    {
        $this->assertSame('unknown', GenerationRequestNormalizer::confidenceLabel(null));
    }

    public function test_confidence_label_returns_high_for_09(): void
    {
        $this->assertSame('high', GenerationRequestNormalizer::confidenceLabel(0.9));
    }

    public function test_confidence_label_returns_medium_for_07(): void
    {
        $this->assertSame('medium', GenerationRequestNormalizer::confidenceLabel(0.7));
    }

    public function test_confidence_label_returns_low_for_05(): void
    {
        $this->assertSame('low', GenerationRequestNormalizer::confidenceLabel(0.5));
    }

    public function test_confidence_label_returns_very_low_below_05(): void
    {
        $this->assertSame('very_low', GenerationRequestNormalizer::confidenceLabel(0.3));
    }

    public function test_confidence_label_boundaries(): void
    {
        $this->assertSame('high', GenerationRequestNormalizer::confidenceLabel(0.9));
        $this->assertSame('medium', GenerationRequestNormalizer::confidenceLabel(0.89));
        $this->assertSame('medium', GenerationRequestNormalizer::confidenceLabel(0.7));
        $this->assertSame('low', GenerationRequestNormalizer::confidenceLabel(0.69));
        $this->assertSame('low', GenerationRequestNormalizer::confidenceLabel(0.5));
        $this->assertSame('very_low', GenerationRequestNormalizer::confidenceLabel(0.49));
    }
}
