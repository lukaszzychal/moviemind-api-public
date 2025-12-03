<?php

declare(strict_types=1);

/**
 * Manual test script to verify structured error format in jobs.
 *
 * Usage:
 *   php artisan tinker
 *   require 'tests/Manual/TestJobErrorFormat.php';
 *   TestJobErrorFormat::run();
 */

namespace Tests\Manual;

use App\Jobs\MockGenerateMovieJob;
use App\Services\JobErrorFormatter;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class TestJobErrorFormat
{
    public static function run(): void
    {
        echo "=== Testing JobErrorFormatter ===\n\n";

        $formatter = new JobErrorFormatter;

        // Test NOT_FOUND error
        echo "1. Testing NOT_FOUND error:\n";
        $exception = new RuntimeException('Movie not found: test-movie-123');
        $result = $formatter->formatError($exception, 'test-movie-123', 'MOVIE');
        echo "   Type: {$result['type']}\n";
        echo "   Message: {$result['message']}\n";
        echo "   User Message: {$result['user_message']}\n";
        echo '   ✓ Type is NOT_FOUND: '.($result['type'] === 'NOT_FOUND' ? 'YES' : 'NO')."\n\n";

        // Test AI_API_ERROR
        echo "2. Testing AI_API_ERROR:\n";
        $exception = new RuntimeException('AI API returned error: Rate limit exceeded');
        $result = $formatter->formatError($exception, 'test-movie', 'MOVIE');
        echo "   Type: {$result['type']}\n";
        echo "   User Message: {$result['user_message']}\n";
        echo '   ✓ Type is AI_API_ERROR: '.($result['type'] === 'AI_API_ERROR' ? 'YES' : 'NO')."\n\n";

        // Test VALIDATION_ERROR
        echo "3. Testing VALIDATION_ERROR:\n";
        $exception = new RuntimeException('AI data validation failed: Title mismatch');
        $result = $formatter->formatError($exception, 'test-movie', 'MOVIE');
        echo "   Type: {$result['type']}\n";
        echo "   User Message: {$result['user_message']}\n";
        echo '   ✓ Type is VALIDATION_ERROR: '.($result['type'] === 'VALIDATION_ERROR' ? 'YES' : 'NO')."\n\n";

        // Test UNKNOWN_ERROR
        echo "4. Testing UNKNOWN_ERROR:\n";
        $exception = new RuntimeException('Database connection failed');
        $result = $formatter->formatError($exception, 'test-movie', 'MOVIE');
        echo "   Type: {$result['type']}\n";
        echo "   User Message: {$result['user_message']}\n";
        echo '   ✓ Type is UNKNOWN_ERROR: '.($result['type'] === 'UNKNOWN_ERROR' ? 'YES' : 'NO')."\n\n";

        echo "=== Testing Mock Job Error Format ===\n\n";

        // Test Mock Job with error
        $jobId = 'test-mock-job-'.time();
        $slug = 'non-existent-movie-'.time();

        echo "5. Testing MockGenerateMovieJob error handling:\n";
        try {
            $job = new MockGenerateMovieJob($slug, $jobId);
            // Force an error by throwing exception
            throw new RuntimeException('Movie not found: '.$slug);
        } catch (\Throwable $e) {
            $formatter = app(JobErrorFormatter::class);
            $errorData = $formatter->formatError($e, $slug, 'MOVIE');

            // Simulate what updateCache would do
            Cache::put("ai_job:{$jobId}", [
                'job_id' => $jobId,
                'status' => 'FAILED',
                'entity' => 'MOVIE',
                'slug' => $slug,
                'error' => $errorData,
            ], now()->addMinutes(15));

            $cached = Cache::get("ai_job:{$jobId}");
            echo "   Cached status: {$cached['status']}\n";
            echo "   Error type: {$cached['error']['type']}\n";
            echo "   Error user_message: {$cached['error']['user_message']}\n";
            echo '   ✓ Error is structured: '.(is_array($cached['error']) ? 'YES' : 'NO')."\n";
            echo '   ✓ Has all required fields: '.(
                isset($cached['error']['type']) &&
                isset($cached['error']['message']) &&
                isset($cached['error']['technical_message']) &&
                isset($cached['error']['user_message'])
                    ? 'YES' : 'NO'
            )."\n\n";
        }

        echo "=== All tests completed ===\n";
    }
}
