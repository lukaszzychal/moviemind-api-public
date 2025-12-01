<?php

namespace Tests\Unit\Services;

use App\Services\AiDataValidator;
use Tests\TestCase;

class AiDataValidatorTest extends TestCase
{
    private AiDataValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AiDataValidator;
    }

    public function test_validate_movie_data_accepts_valid_data(): void
    {
        $aiResponse = [
            'title' => 'The Matrix',
            'release_year' => 1999,
            'director' => 'Lana Wachowski',
            'description' => 'A computer hacker learns about the true nature of reality.',
            'genres' => ['Action', 'Sci-Fi'],
        ];

        $result = $this->validator->validateMovieData($aiResponse, 'the-matrix-1999');

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertNotNull($result['similarity']);
        $this->assertGreaterThan(0.6, $result['similarity']);
    }

    public function test_validate_movie_data_rejects_invalid_year(): void
    {
        $aiResponse = [
            'title' => 'The Matrix',
            'release_year' => 1800, // Too old
            'director' => 'Lana Wachowski',
        ];

        $result = $this->validator->validateMovieData($aiResponse, 'the-matrix-1999');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Invalid release year', $result['errors'][0]);
    }

    public function test_validate_movie_data_rejects_future_year(): void
    {
        $currentYear = (int) date('Y');
        $futureYear = $currentYear + 10;

        $aiResponse = [
            'title' => 'The Matrix',
            'release_year' => $futureYear,
            'director' => 'Lana Wachowski',
        ];

        $result = $this->validator->validateMovieData($aiResponse, 'the-matrix-1999');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Invalid release year', $result['errors'][0]);
    }

    public function test_validate_movie_data_rejects_mismatched_title(): void
    {
        $aiResponse = [
            'title' => 'Inception', // Doesn't match slug
            'release_year' => 1999,
            'director' => 'Lana Wachowski',
        ];

        $result = $this->validator->validateMovieData($aiResponse, 'the-matrix-1999');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('does not match slug', $result['errors'][0]);
    }

    public function test_validate_movie_data_rejects_year_mismatch_with_slug(): void
    {
        $aiResponse = [
            'title' => 'The Matrix',
            'release_year' => 2010, // Slug has 1999
            'director' => 'Lana Wachowski',
        ];

        $result = $this->validator->validateMovieData($aiResponse, 'the-matrix-1999');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Release year mismatch', $result['errors'][0]);
    }

    public function test_validate_person_data_accepts_valid_data(): void
    {
        $aiResponse = [
            'name' => 'Keanu Reeves',
            'birth_date' => '1964-09-02',
            'birthplace' => 'Beirut, Lebanon',
            'biography' => 'Canadian actor.',
        ];

        $result = $this->validator->validatePersonData($aiResponse, 'keanu-reeves');

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertNotNull($result['similarity']);
        $this->assertGreaterThan(0.6, $result['similarity']);
    }

    public function test_validate_person_data_rejects_invalid_birth_date_format(): void
    {
        $aiResponse = [
            'name' => 'Keanu Reeves',
            'birth_date' => 'invalid-date',
            'birthplace' => 'Beirut',
        ];

        $result = $this->validator->validatePersonData($aiResponse, 'keanu-reeves');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Invalid birth date format', $result['errors'][0]);
    }

    public function test_validate_person_data_rejects_invalid_birth_year(): void
    {
        $aiResponse = [
            'name' => 'Keanu Reeves',
            'birth_date' => '1800-01-01', // Too old
            'birthplace' => 'Beirut',
        ];

        $result = $this->validator->validatePersonData($aiResponse, 'keanu-reeves');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Invalid birth year', $result['errors'][0]);
    }

    public function test_validate_person_data_rejects_mismatched_name(): void
    {
        $aiResponse = [
            'name' => 'John Doe', // Doesn't match slug
            'birth_date' => '1964-09-02',
            'birthplace' => 'Beirut',
        ];

        $result = $this->validator->validatePersonData($aiResponse, 'keanu-reeves');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('does not match slug', $result['errors'][0]);
    }

    public function test_calculate_similarity_returns_high_score_for_matching_texts(): void
    {
        // Test similarity indirectly through validateMovieData
        $aiResponse = [
            'title' => 'The Matrix',
            'release_year' => 1999,
        ];

        $result = $this->validator->validateMovieData($aiResponse, 'the-matrix-1999');

        $this->assertTrue($result['valid']);
        $this->assertNotNull($result['similarity']);
        $this->assertGreaterThan(0.6, $result['similarity']);
    }

    public function test_calculate_similarity_returns_low_score_for_different_texts(): void
    {
        // Test similarity indirectly through validateMovieData
        $aiResponse = [
            'title' => 'Inception',
            'release_year' => 1999,
        ];

        $result = $this->validator->validateMovieData($aiResponse, 'the-matrix-1999');

        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['similarity']);
        $this->assertLessThan(0.6, $result['similarity']);
    }
}
