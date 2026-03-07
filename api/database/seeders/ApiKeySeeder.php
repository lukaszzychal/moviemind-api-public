<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Seeder for demo API keys.
 *
 * Creates demo API keys for each subscription plan (Free, Pro, Enterprise).
 * The Free plan key is marked as public (is_public=true) and its plaintext
 * is stored so it can be displayed on the welcome endpoint for portfolio/demo purposes.
 *
 * Note: In production, regular API keys are NEVER stored in plaintext.
 * This is an exception only for the public demo key.
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

        // Free plan key is PUBLIC — plaintext stored for welcome endpoint
        $this->createDemoKey($apiKeyService, 'Demo Free Plan Key', $freePlan->id, isPublic: true);
        $this->createDemoKey($apiKeyService, 'Demo Pro Plan Key', $proPlan->id, isPublic: false);
        $this->createDemoKey($apiKeyService, 'Demo Enterprise Plan Key', $enterprisePlan->id, isPublic: false);

        $this->command->info('ApiKeySeeder: Created demo API keys for Free, Pro, and Enterprise plans.');
        $this->command->info('The Free plan demo key is public and visible on the welcome endpoint (/).');
    }

    /**
     * Create a demo API key.
     */
    private function createDemoKey(
        \App\Services\ApiKeyService $apiKeyService,
        string $name,
        string $planId,
        bool $isPublic = false,
    ): void {
        // Check if key already exists
        $existing = ApiKey::where('name', $name)
            ->where('plan_id', $planId)
            ->first();

        if ($existing !== null) {
            $this->command->info("ApiKeySeeder: Demo key '{$name}' already exists, skipping.");

            return;
        }

        $result = $apiKeyService->createKey(
            name: $name,
            planId: $planId,
        );

        // For public keys: store plaintext so welcome endpoint can display it
        if ($isPublic) {
            $result['apiKey']->update([
                'is_public' => true,
                'public_plaintext_key' => $result['key'],
            ]);
            $this->command->info("ApiKeySeeder: Created PUBLIC demo key '{$name}' → {$result['key']}");
        } else {
            $this->command->info("ApiKeySeeder: Created demo API key '{$name}' with prefix '{$result['apiKey']->key_prefix}'");
            $this->command->warn("Plaintext key: {$result['key']} (save this - it won't be shown again!)");
        }
    }
}
