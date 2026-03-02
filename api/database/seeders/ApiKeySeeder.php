<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Seeder for demo API keys.
 *
 * Creates demo API keys for each subscription plan (Free, Pro, Enterprise).
 * These keys are for portfolio/demo purposes only.
 *
 * Note: In production, API keys would be created through the admin panel
 * or via billing provider integration (Stripe, PayPal, etc.).
 */
class ApiKeySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only seed in non-production environments
        if (app()->environment('production', 'staging')) {
            $this->command->warn('ApiKeySeeder: Skipping demo API keys in production/staging environment');

            return;
        }

        $apiKeyService = app(\App\Services\ApiKeyService::class);

        $freePlan = SubscriptionPlan::where('name', 'free')->first();
        $proPlan = SubscriptionPlan::where('name', 'pro')->first();
        $enterprisePlan = SubscriptionPlan::where('name', 'enterprise')->first();

        if ($freePlan === null || $proPlan === null || $enterprisePlan === null) {
            $this->command->error('ApiKeySeeder: Subscription plans not found. Run SubscriptionPlanSeeder first.');

            return;
        }

        // Create demo API keys for each plan
        $this->createDemoKey($apiKeyService, 'Demo Free Plan Key', $freePlan->id);
        $this->createDemoKey($apiKeyService, 'Demo Pro Plan Key', $proPlan->id);
        $this->createDemoKey($apiKeyService, 'Demo Enterprise Plan Key', $enterprisePlan->id);

        $this->command->info('ApiKeySeeder: Created demo API keys for Free, Pro, and Enterprise plans.');
        $this->command->warn('Note: Plaintext API keys are only shown once during creation. Check logs or database for key prefixes.');
    }

    /**
     * Create a demo API key.
     */
    private function createDemoKey(\App\Services\ApiKeyService $apiKeyService, string $name, string $planId): void
    {
        // Check if key already exists
        $existing = \App\Models\ApiKey::where('name', $name)
            ->where('plan_id', $planId)
            ->first();

        if ($existing !== null) {
            $this->command->info("ApiKeySeeder: Demo key '{$name}' already exists, skipping.");

            return;
        }

        $result = $apiKeyService->createKey(
            name: $name,
            planId: $planId
        );

        $this->command->info("ApiKeySeeder: Created demo API key '{$name}' with prefix '{$result['apiKey']->key_prefix}'");
        $this->command->warn("Plaintext key: {$result['key']} (save this - it won't be shown again!)");
    }
}
