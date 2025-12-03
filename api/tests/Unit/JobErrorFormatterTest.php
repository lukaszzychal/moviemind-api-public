<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\JobErrorType;
use App\Services\JobErrorFormatter;
use RuntimeException;
use Tests\TestCase;

class JobErrorFormatterTest extends TestCase
{
    private JobErrorFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new JobErrorFormatter;
    }

    public function test_detects_not_found_error(): void
    {
        $exception = new RuntimeException('Movie not found: test-movie-123');
        $result = $this->formatter->formatError($exception, 'test-movie-123', 'MOVIE');

        $this->assertSame(JobErrorType::NOT_FOUND->value, $result['type']);
        $this->assertStringContainsString('not found', $result['message']);
        $this->assertStringContainsString('does not exist', $result['user_message']);
        $this->assertSame('Movie not found: test-movie-123', $result['technical_message']);
    }

    public function test_detects_ai_api_error(): void
    {
        $exception = new RuntimeException('AI API returned error: Rate limit exceeded');
        $result = $this->formatter->formatError($exception, 'test-movie-123', 'MOVIE');

        $this->assertSame(JobErrorType::AI_API_ERROR->value, $result['type']);
        $this->assertStringContainsString('AI API', $result['message']);
        $this->assertStringContainsString('temporarily unavailable', $result['user_message']);
        $this->assertSame('AI API returned error: Rate limit exceeded', $result['technical_message']);
    }

    public function test_detects_validation_error(): void
    {
        $exception = new RuntimeException('AI data validation failed: Title does not match slug');
        $result = $this->formatter->formatError($exception, 'test-movie-123', 'MOVIE');

        $this->assertSame(JobErrorType::VALIDATION_ERROR->value, $result['type']);
        $this->assertStringContainsString('validation', $result['message']);
        $this->assertStringContainsString('validation checks', $result['user_message']);
        $this->assertSame('AI data validation failed: Title does not match slug', $result['technical_message']);
    }

    public function test_detects_unknown_error(): void
    {
        $exception = new RuntimeException('Database connection failed');
        $result = $this->formatter->formatError($exception, 'test-movie-123', 'MOVIE');

        $this->assertSame(JobErrorType::UNKNOWN_ERROR->value, $result['type']);
        $this->assertStringContainsString('unexpected error', $result['message']);
        $this->assertStringContainsString('unexpected error', $result['user_message']);
        $this->assertSame('Database connection failed', $result['technical_message']);
    }

    public function test_formats_error_for_movie(): void
    {
        $exception = new RuntimeException('Movie not found: test-movie-123');
        $result = $this->formatter->formatError($exception, 'test-movie-123', 'MOVIE');

        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('technical_message', $result);
        $this->assertArrayHasKey('user_message', $result);
        $this->assertStringContainsString('movie', strtolower($result['user_message']));
    }

    public function test_formats_error_for_person(): void
    {
        $exception = new RuntimeException('Person not found: test-person-123');
        $result = $this->formatter->formatError($exception, 'test-person-123', 'PERSON');

        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('technical_message', $result);
        $this->assertArrayHasKey('user_message', $result);
        $this->assertStringContainsString('person', strtolower($result['user_message']));
    }

    public function test_not_found_case_insensitive(): void
    {
        $exception = new RuntimeException('MOVIE NOT FOUND: test-movie-123');
        $result = $this->formatter->formatError($exception, 'test-movie-123', 'MOVIE');

        $this->assertSame(JobErrorType::NOT_FOUND->value, $result['type']);
    }

    public function test_ai_api_error_case_insensitive(): void
    {
        $exception = new RuntimeException('ai api returned error: timeout');
        $result = $this->formatter->formatError($exception, 'test-movie-123', 'MOVIE');

        $this->assertSame(JobErrorType::AI_API_ERROR->value, $result['type']);
    }
}
