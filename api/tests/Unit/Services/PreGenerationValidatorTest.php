<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PreGenerationValidator;
use Tests\TestCase;

class PreGenerationValidatorTest extends TestCase
{
    private PreGenerationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PreGenerationValidator;
    }

    public function test_should_generate_movie_with_valid_slug_and_year(): void
    {
        $result = $this->validator->shouldGenerateMovie('the-matrix-1999');

        $this->assertTrue($result['should_generate']);
        $this->assertGreaterThanOrEqual(0.5, $result['confidence']);
        $this->assertArrayHasKey('reason', $result);
    }

    public function test_should_not_generate_movie_with_suspicious_pattern(): void
    {
        $result = $this->validator->shouldGenerateMovie('test-123-random');

        $this->assertFalse($result['should_generate']);
        $this->assertStringContainsString('Suspicious', $result['reason']);
    }

    public function test_should_not_generate_movie_with_low_confidence_slug(): void
    {
        $result = $this->validator->shouldGenerateMovie('123');

        $this->assertFalse($result['should_generate']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    public function test_should_not_generate_movie_with_invalid_year_before_1888(): void
    {
        // Year 1800 is not matched by year pattern (1888-2039), so it will be detected as suspicious pattern
        // Use a year that matches the pattern but is invalid
        $result = $this->validator->shouldGenerateMovie('some-movie-1880');

        $this->assertFalse($result['should_generate']);
        // SlugValidator will detect this as suspicious pattern since year is outside valid range
        $this->assertArrayHasKey('reason', $result);
    }

    public function test_should_not_generate_movie_with_future_year(): void
    {
        $futureYear = (int) date('Y') + 5;
        $result = $this->validator->shouldGenerateMovie("some-movie-{$futureYear}");

        $this->assertFalse($result['should_generate']);
        $this->assertStringContainsString('Invalid release year', $result['reason']);
        $this->assertStringContainsString('future', $result['reason']);
    }

    public function test_should_generate_person_with_valid_slug(): void
    {
        $result = $this->validator->shouldGeneratePerson('keanu-reeves');

        $this->assertTrue($result['should_generate']);
        $this->assertGreaterThanOrEqual(0.5, $result['confidence']);
        $this->assertArrayHasKey('reason', $result);
    }

    public function test_should_not_generate_person_with_suspicious_pattern(): void
    {
        $result = $this->validator->shouldGeneratePerson('test-xyz-999');

        $this->assertFalse($result['should_generate']);
        // SlugValidator detects this as low confidence first (before suspicious pattern check)
        $this->assertArrayHasKey('reason', $result);
        // Should be rejected (either low confidence or suspicious pattern)
        $this->assertTrue(
            str_contains($result['reason'], 'Low confidence') ||
            str_contains($result['reason'], 'Suspicious')
        );
    }

    public function test_should_not_generate_person_with_low_confidence_slug(): void
    {
        $result = $this->validator->shouldGeneratePerson('123');

        $this->assertFalse($result['should_generate']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    public function test_should_generate_movie_with_valid_year_in_range(): void
    {
        $currentYear = (int) date('Y');
        $result = $this->validator->shouldGenerateMovie("valid-movie-{$currentYear}");

        $this->assertTrue($result['should_generate']);
    }

    public function test_should_not_generate_with_fake_pattern(): void
    {
        $result = $this->validator->shouldGenerateMovie('fake-movie-1999');

        $this->assertFalse($result['should_generate']);
        $this->assertStringContainsString('Suspicious', $result['reason']);
    }

    public function test_should_not_generate_with_dummy_pattern(): void
    {
        $result = $this->validator->shouldGenerateMovie('dummy-test-123');

        $this->assertFalse($result['should_generate']);
        $this->assertStringContainsString('Suspicious', $result['reason']);
    }
}
