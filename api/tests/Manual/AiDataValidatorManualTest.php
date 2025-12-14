<?php

declare(strict_types=1);

/**
 * Manual Test Script for AiDataValidator (TASK-038 Faza 2)
 *
 * Usage:
 *   php artisan tinker
 *   >>> require 'tests/Manual/AiDataValidatorManualTest.php';
 *   >>> runManualTests();
 *
 * Or run directly:
 *   php tests/Manual/AiDataValidatorManualTest.php
 */

use App\Services\AiDataValidator;

if (php_sapi_name() === 'cli' && ! defined('LARAVEL_START')) {
    require __DIR__.'/../../vendor/autoload.php';
    $app = require_once __DIR__.'/../../bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

function runManualTests(): void
{
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  Manual Tests: AiDataValidator - TASK-038 (Faza 2)\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "\n";

    $validator = app(AiDataValidator::class);

    // Test 1: Director-Genre Mismatch
    echo "📋 Test 1: Director-Genre Mismatch\n";
    echo "───────────────────────────────────────────────────────────────\n";
    $test1 = [
        'title' => 'The Matrix',
        'release_year' => 1999,
        'director' => 'Lana Wachowski', // Known for Action/Sci-Fi
        'genres' => ['Romance', 'Comedy'], // Doesn't match
    ];
    $result1 = $validator->validateMovieData($test1, 'the-matrix-1999');
    echo "Input: Director=Lana Wachowski, Genres=Romance, Comedy\n";
    echo 'Result: '.($result1['valid'] ? '✅ VALID' : '❌ INVALID')."\n";
    if (! empty($result1['errors'])) {
        echo 'Errors: '.implode(', ', $result1['errors'])."\n";
    }
    echo "\n";

    // Test 2: Director-Genre Match
    echo "📋 Test 2: Director-Genre Match\n";
    echo "───────────────────────────────────────────────────────────────\n";
    $test2 = [
        'title' => 'The Matrix',
        'release_year' => 1999,
        'director' => 'Lana Wachowski', // Known for Action/Sci-Fi
        'genres' => ['Action', 'Sci-Fi'], // Matches
    ];
    $result2 = $validator->validateMovieData($test2, 'the-matrix-1999');
    echo "Input: Director=Lana Wachowski, Genres=Action, Sci-Fi\n";
    echo 'Result: '.($result2['valid'] ? '✅ VALID' : '❌ INVALID')."\n";
    if (! empty($result2['errors'])) {
        echo 'Errors: '.implode(', ', $result2['errors'])."\n";
    }
    echo "\n";

    // Test 3: Genre-Year Inconsistency
    echo "📋 Test 3: Genre-Year Inconsistency\n";
    echo "───────────────────────────────────────────────────────────────\n";
    $test3 = [
        'title' => 'Some Movie',
        'release_year' => 1920,
        'director' => 'Some Director',
        'genres' => ['Cyberpunk', 'Post-Apocalyptic'], // Genres that didn't exist in 1920
    ];
    $result3 = $validator->validateMovieData($test3, 'some-movie-1920');
    echo "Input: Year=1920, Genres=Cyberpunk, Post-Apocalyptic\n";
    echo 'Result: '.($result3['valid'] ? '✅ VALID' : '❌ INVALID')."\n";
    if (! empty($result3['errors'])) {
        echo 'Errors: '.implode(', ', $result3['errors'])."\n";
    }
    echo "\n";

    // Test 4: Genre-Year Consistency
    echo "📋 Test 4: Genre-Year Consistency\n";
    echo "───────────────────────────────────────────────────────────────\n";
    $test4 = [
        'title' => 'Some Movie',
        'release_year' => 1920,
        'director' => 'Some Director',
        'genres' => ['Drama', 'Silent'], // Appropriate for 1920
    ];
    $result4 = $validator->validateMovieData($test4, 'some-movie-1920');
    echo "Input: Year=1920, Genres=Drama, Silent\n";
    echo 'Result: '.($result4['valid'] ? '✅ VALID' : '❌ INVALID')."\n";
    if (! empty($result4['errors'])) {
        echo 'Errors: '.implode(', ', $result4['errors'])."\n";
    }
    echo "\n";

    // Test 5: Birthplace-Birthdate Mismatch (Person)
    echo "📋 Test 5: Birthplace-Birthdate Mismatch (Person)\n";
    echo "───────────────────────────────────────────────────────────────\n";
    $test5 = [
        'name' => 'John Doe',
        'birth_date' => '1900-01-01',
        'birthplace' => 'Czech Republic', // Country didn't exist until 1993
    ];
    $result5 = $validator->validatePersonData($test5, 'john-doe');
    echo "Input: Birth Date=1900-01-01, Birthplace=Czech Republic\n";
    echo 'Result: '.($result5['valid'] ? '✅ VALID' : '❌ INVALID')."\n";
    if (! empty($result5['errors'])) {
        echo 'Errors: '.implode(', ', $result5['errors'])."\n";
    }
    echo "\n";

    // Test 6: Birthplace-Birthdate Match (Person)
    echo "📋 Test 6: Birthplace-Birthdate Match (Person)\n";
    echo "───────────────────────────────────────────────────────────────\n";
    $test6 = [
        'name' => 'John Doe',
        'birth_date' => '1964-09-02',
        'birthplace' => 'Beirut, Lebanon', // Appropriate for 1964
    ];
    $result6 = $validator->validatePersonData($test6, 'john-doe');
    echo "Input: Birth Date=1964-09-02, Birthplace=Beirut, Lebanon\n";
    echo 'Result: '.($result6['valid'] ? '✅ VALID' : '❌ INVALID')."\n";
    if (! empty($result6['errors'])) {
        echo 'Errors: '.implode(', ', $result6['errors'])."\n";
    }
    echo "\n";

    // Test 7: Low Similarity Logging (should log but pass)
    echo "📋 Test 7: Low Similarity Logging\n";
    echo "───────────────────────────────────────────────────────────────\n";
    $test7 = [
        'title' => 'The Matrix Reloaded', // Close but not exact match
        'release_year' => 1999,
        'director' => 'Lana Wachowski',
    ];
    echo "Input: Title='The Matrix Reloaded', Slug='the-matrix-1999'\n";
    echo "Note: Check logs for 'Low similarity detected' if similarity is 0.6-0.7\n";
    $result7 = $validator->validateMovieData($test7, 'the-matrix-1999');
    echo 'Result: '.($result7['valid'] ? '✅ VALID' : '❌ INVALID')."\n";
    echo 'Similarity: '.($result7['similarity'] !== null ? number_format($result7['similarity'], 2) : 'N/A')."\n";
    if ($result7['similarity'] !== null && $result7['similarity'] >= 0.6 && $result7['similarity'] < 0.7) {
        echo "⚠️  This case should be logged (similarity between 0.6 and 0.7)\n";
    }
    echo "\n";

    // Summary
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  Summary\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo '✅ Test 1 (Director-Genre Mismatch): '.($result1['valid'] ? 'FAILED (should be invalid)' : 'PASSED')."\n";
    echo '✅ Test 2 (Director-Genre Match): '.($result2['valid'] ? 'PASSED' : 'FAILED (should be valid)')."\n";
    echo '✅ Test 3 (Genre-Year Inconsistency): '.($result3['valid'] ? 'FAILED (should be invalid)' : 'PASSED')."\n";
    echo '✅ Test 4 (Genre-Year Consistency): '.($result4['valid'] ? 'PASSED' : 'FAILED (should be valid)')."\n";
    echo '✅ Test 5 (Birthplace-Birthdate Mismatch): '.($result5['valid'] ? 'FAILED (should be invalid)' : 'PASSED')."\n";
    echo '✅ Test 6 (Birthplace-Birthdate Match): '.($result6['valid'] ? 'PASSED' : 'FAILED (should be valid)')."\n";
    echo '✅ Test 7 (Low Similarity Logging): '.($result7['valid'] ? 'PASSED' : 'FAILED')."\n";
    echo "\n";
    echo "📝 Check application logs for 'Low similarity detected' messages\n";
    echo "   Command: tail -f storage/logs/laravel.log | grep 'Low similarity'\n";
    echo "\n";
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    runManualTests();
}
