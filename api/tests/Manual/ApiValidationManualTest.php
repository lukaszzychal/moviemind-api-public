<?php

declare(strict_types=1);

/**
 * Manual API Test Script for AiDataValidator (TASK-038 Faza 2)
 *
 * This script tests validation through the API endpoint.
 * It requires:
 * - Feature flag 'hallucination_guard' to be active
 * - Feature flag 'ai_description_generation' to be active
 * - AI_SERVICE=mock (to control AI responses)
 * - Horizon or queue worker running
 *
 * Usage:
 *   php tests/Manual/ApiValidationManualTest.php
 */
if (php_sapi_name() === 'cli' && ! defined('LARAVEL_START')) {
    require __DIR__.'/../../vendor/autoload.php';
    $app = require_once __DIR__.'/../../bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

function runApiValidationTests(): void
{
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  API Validation Tests: AiDataValidator - TASK-038 (Faza 2)\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "\n";

    // Check feature flags
    $hallucinationGuard = \Laravel\Pennant\Feature::active('hallucination_guard');
    $aiDescriptionGeneration = \Laravel\Pennant\Feature::active('ai_description_generation');
    $aiService = env('AI_SERVICE', 'mock');

    echo "📋 Configuration Check\n";
    echo "───────────────────────────────────────────────────────────────\n";
    echo 'hallucination_guard: '.($hallucinationGuard ? '✅ ON' : '❌ OFF')."\n";
    echo 'ai_description_generation: '.($aiDescriptionGeneration ? '✅ ON' : '❌ OFF')."\n";
    echo "AI_SERVICE: {$aiService}\n";
    echo "\n";

    if (! $hallucinationGuard) {
        echo "⚠️  WARNING: Feature flag 'hallucination_guard' is OFF. Validation will not run.\n";
        echo "   Activate with: php artisan tinker\n";
        echo "   >>> Laravel\\Pennant\\Feature::activate('hallucination_guard');\n";
        echo "\n";
    }

    if (! $aiDescriptionGeneration) {
        echo "⚠️  WARNING: Feature flag 'ai_description_generation' is OFF. Generation will not work.\n";
        echo "   Activate with: php artisan tinker\n";
        echo "   >>> Laravel\\Pennant\\Feature::activate('ai_description_generation');\n";
        echo "\n";
    }

    if ($aiService !== 'mock') {
        echo "⚠️  WARNING: AI_SERVICE is set to '{$aiService}', not 'mock'.\n";
        echo "   For controlled testing, set AI_SERVICE=mock in .env\n";
        echo "   Current tests may use real AI (costs money, unpredictable results)\n";
        echo "\n";
    }

    echo "📋 Test Instructions\n";
    echo "───────────────────────────────────────────────────────────────\n";
    echo "To test validation through API:\n";
    echo "\n";
    echo "1. Ensure Horizon or queue worker is running:\n";
    echo "   php artisan horizon\n";
    echo "   OR\n";
    echo "   php artisan queue:work\n";
    echo "\n";
    echo "2. Make a request to generate a movie:\n";
    echo "   curl -X POST http://127.0.0.1:8000/api/v1/generate \\\n";
    echo "     -H 'Content-Type: application/json' \\\n";
    echo "     -d '{\n";
    echo "       \"entity_type\": \"MOVIE\",\n";
    echo "       \"slug\": \"test-movie-1999\",\n";
    echo "       \"locale\": \"pl-PL\"\n";
    echo "     }'\n";
    echo "\n";
    echo "3. Check job status:\n";
    echo "   curl http://127.0.0.1:8000/api/v1/jobs/{job_id}\n";
    echo "\n";
    echo "4. Check logs for validation errors:\n";
    echo "   tail -f storage/logs/laravel.log | grep -i 'validation failed'\n";
    echo "\n";
    echo "5. Check Horizon dashboard for failed jobs:\n";
    echo "   http://127.0.0.1:8000/horizon\n";
    echo "\n";
    echo "📋 Expected Behavior\n";
    echo "───────────────────────────────────────────────────────────────\n";
    echo "When validation fails (e.g., director-genre mismatch):\n";
    echo "  - Job status should be 'FAILED'\n";
    echo "  - Log should contain: 'AI data validation failed'\n";
    echo "  - Error message should describe the validation issue\n";
    echo "\n";
    echo "When validation passes:\n";
    echo "  - Job status should be 'DONE'\n";
    echo "  - Movie description should be created\n";
    echo "\n";
    echo "When similarity is low (0.6-0.7):\n";
    echo "  - Validation should pass\n";
    echo "  - Log should contain: 'Low similarity detected'\n";
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "\n";
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    runApiValidationTests();
}
