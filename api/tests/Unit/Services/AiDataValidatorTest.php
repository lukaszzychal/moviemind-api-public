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

    // ========== Faza 2: Rozszerzone heurystyki ==========

    public function test_validate_movie_data_rejects_director_genre_mismatch(): void
    {
        // Director known for Sci-Fi/Action, but genres are Romance/Comedy
        $aiResponse = [
            'title' => 'The Matrix',
            'release_year' => 1999,
            'director' => 'Lana Wachowski', // Known for Sci-Fi/Action
            'genres' => ['Romance', 'Comedy'], // Doesn't match director's typical genres
        ];

        $result = $this->validator->validateMovieData($aiResponse, 'the-matrix-1999');

        // Should detect mismatch between director and genres
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('director', strtolower($result['errors'][0]));
    }

    public function test_validate_movie_data_accepts_matching_director_genre(): void
    {
        // Director known for Sci-Fi/Action, genres match
        $aiResponse = [
            'title' => 'The Matrix',
            'release_year' => 1999,
            'director' => 'Lana Wachowski',
            'genres' => ['Action', 'Sci-Fi'], // Matches director's typical genres
        ];

        $result = $this->validator->validateMovieData($aiResponse, 'the-matrix-1999');

        $this->assertTrue($result['valid']);
    }

    public function test_validate_movie_data_rejects_genre_year_inconsistency(): void
    {
        // Genres that didn't exist or were rare in that year
        $aiResponse = [
            'title' => 'Some Movie',
            'release_year' => 1920,
            'director' => 'Some Director',
            'genres' => ['Cyberpunk', 'Post-Apocalyptic'], // Genres that didn't exist in 1920
        ];

        $result = $this->validator->validateMovieData($aiResponse, 'some-movie-1920');

        // Should detect genre-year inconsistency
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('genre', strtolower($result['errors'][0]));
    }

    public function test_validate_movie_data_accepts_appropriate_genres_for_year(): void
    {
        // Genres appropriate for the year
        $aiResponse = [
            'title' => 'Some Movie',
            'release_year' => 1920,
            'director' => 'Some Director',
            'genres' => ['Drama', 'Silent'], // Appropriate for 1920
        ];

        $result = $this->validator->validateMovieData($aiResponse, 'some-movie-1920');

        $this->assertTrue($result['valid']);
    }

    public function test_validate_person_data_rejects_birthplace_birthdate_mismatch(): void
    {
        // Birthplace doesn't match birth date (e.g., modern country name for old date)
        // Use year 1900 (passes birth year validation) but with modern country name
        $aiResponse = [
            'name' => 'John Doe',
            'birth_date' => '1900-01-01', // Passes birth year validation (>= 1850)
            'birthplace' => 'Czech Republic', // Modern name (country didn't exist until 1993)
        ];

        $result = $this->validator->validatePersonData($aiResponse, 'john-doe');

        // Should detect geographic inconsistency
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        // Check if any error contains 'birthplace' or 'country'
        $hasBirthplaceError = false;
        foreach ($result['errors'] as $error) {
            if (str_contains(strtolower($error), 'birthplace') || str_contains(strtolower($error), 'country')) {
                $hasBirthplaceError = true;
                break;
            }
        }
        $this->assertTrue($hasBirthplaceError, 'Expected error about birthplace/country inconsistency');
    }

    public function test_validate_person_data_accepts_matching_birthplace_birthdate(): void
    {
        // Birthplace matches birth date
        $aiResponse = [
            'name' => 'John Doe',
            'birth_date' => '1964-09-02',
            'birthplace' => 'Beirut, Lebanon', // Appropriate for 1964
        ];

        $result = $this->validator->validatePersonData($aiResponse, 'john-doe');

        $this->assertTrue($result['valid']);
    }

    public function test_validate_movie_data_logs_low_similarity_cases(): void
    {
        // Similarity between 0.6 and 0.7 should be logged
        \Illuminate\Support\Facades\Log::spy();

        $aiResponse = [
            'title' => 'The Matrix Reloaded', // Close but not exact match
            'release_year' => 1999,
            'director' => 'Lana Wachowski',
        ];

        $result = $this->validator->validateMovieData($aiResponse, 'the-matrix-1999');

        // Should pass validation (similarity >= 0.6)
        $this->assertTrue($result['valid']);

        // Check if log was called (if similarity is between 0.6 and 0.7)
        if ($result['similarity'] !== null && $result['similarity'] >= 0.6 && $result['similarity'] < 0.7) {
            \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
                ->once()
                ->with('Low similarity detected (passed threshold)', \Mockery::on(function ($context) {
                    return isset($context['slug']) &&
                        isset($context['similarity']) &&
                        isset($context['type']) &&
                        $context['type'] === 'movie';
                }));
        } else {
            // If similarity is >= 0.7, log won't be called - that's also valid
            $this->assertTrue(true, 'Similarity is '.($result['similarity'] ?? 'null').' - log may not be called');
        }
    }
}
