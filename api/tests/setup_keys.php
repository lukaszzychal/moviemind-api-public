<?php

declare(strict_types=1);

/**
 * E2E helper: create API keys and output JSON for Playwright specs (e.g. api-security, movies happy path).
 * Run from container: docker compose exec -T php php tests/setup_keys.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $apiKeyService = app(\App\Services\ApiKeyService::class);
    $proPlan = \App\Models\SubscriptionPlan::where('name', 'pro')->first();
    $freePlan = \App\Models\SubscriptionPlan::where('name', 'free')->first();

    if ($proPlan === null || $freePlan === null) {
        echo json_encode(['error' => 'Subscription plans not found. Run seeders first.']);
        exit(1);
    }

    $proResult = $apiKeyService->createKey('E2E Pro Key', $proPlan->id);
    $freeResult = $apiKeyService->createKey('E2E Free Key', $freePlan->id);

    $output = [
        'key' => $proResult['key'],
        'id' => $proResult['apiKey']->id,
        'limited_key' => $freeResult['key'],
    ];

    echo json_encode($output);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
