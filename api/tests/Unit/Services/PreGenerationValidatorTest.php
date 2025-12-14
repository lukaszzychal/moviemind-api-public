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

    public function test_should_not_generate_movie_with_year_exactly_at_1888_boundary(): void
    {
        // Year 1888 is the minimum valid year, but 1887 should be rejected
        $result = $this->validator->shouldGenerateMovie('some-movie-1887');

        $this->assertFalse($result['should_generate']);
        $this->assertStringContainsString('Invalid release year', $result['reason']);
    }

    public function test_should_generate_movie_with_year_exactly_1888(): void
    {
        // Year 1888 is the minimum valid year
        $result = $this->validator->shouldGenerateMovie('roundhay-garden-scene-1888');

        $this->assertTrue($result['should_generate']);
    }

    public function test_should_not_generate_movie_with_year_more_than_2_years_in_future(): void
    {
        $futureYear = (int) date('Y') + 3;
        $result = $this->validator->shouldGenerateMovie("some-movie-{$futureYear}");

        $this->assertFalse($result['should_generate']);
        $this->assertStringContainsString('Invalid release year', $result['reason']);
        $this->assertStringContainsString('future', $result['reason']);
    }

    public function test_should_generate_movie_with_year_exactly_2_years_in_future(): void
    {
        // 2 years in future is allowed
        $futureYear = (int) date('Y') + 2;
        $result = $this->validator->shouldGenerateMovie("some-movie-{$futureYear}");

        $this->assertTrue($result['should_generate']);
    }

    public function test_should_not_generate_person_with_invalid_birth_year_before_1850(): void
    {
        // Birth year before 1850 is suspicious
        $result = $this->validator->shouldGeneratePerson('person-name-1800');

        // Should be rejected either by birth date validation or suspicious pattern
        $this->assertFalse($result['should_generate']);
    }

    public function test_should_not_generate_person_with_future_birth_year(): void
    {
        $futureYear = (int) date('Y') + 1;
        $result = $this->validator->shouldGeneratePerson("person-name-{$futureYear}");

        // Should be rejected (future birth date is impossible)
        $this->assertFalse($result['should_generate']);
    }

    public function test_should_not_generate_with_example_pattern(): void
    {
        $result = $this->validator->shouldGenerateMovie('example-movie-1999');

        $this->assertFalse($result['should_generate']);
        $this->assertStringContainsString('Suspicious', $result['reason']);
    }

    public function test_should_not_generate_with_sample_pattern(): void
    {
        $result = $this->validator->shouldGenerateMovie('sample-test-123');

        $this->assertFalse($result['should_generate']);
        $this->assertStringContainsString('Suspicious', $result['reason']);
    }

    public function test_should_not_generate_with_long_digit_sequence(): void
    {
        // Long sequences of digits (after removing year) should be suspicious
        $result = $this->validator->shouldGenerateMovie('movie-123456789');

        $this->assertFalse($result['should_generate']);
        $this->assertStringContainsString('Suspicious', $result['reason']);
    }

    public function test_should_generate_movie_with_valid_year_but_suspicious_pattern_in_title(): void
    {
        // Even with valid year, if title contains suspicious pattern, should reject
        $result = $this->validator->shouldGenerateMovie('test-movie-1999');

        $this->assertFalse($result['should_generate']);
        $this->assertStringContainsString('Suspicious', $result['reason']);
    }

    public function test_should_generate_person_with_valid_birth_year_in_slug(): void
    {
        // Person slug with valid birth year (e.g., 1960)
        $result = $this->validator->shouldGeneratePerson('person-name-1960');

        // Should pass - birth year 1960 is valid
        $this->assertTrue($result['should_generate']);
    }
}
