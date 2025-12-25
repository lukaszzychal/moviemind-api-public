<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'free',
                'display_name' => 'Free',
                'description' => 'Free plan with basic read-only access. Limited to 100 requests per month.',
                'monthly_limit' => 100,
                'rate_limit_per_minute' => 10,
                'features' => ['read'],
                'price_monthly' => null,
                'price_yearly' => null,
                'is_active' => true,
            ],
            [
                'name' => 'pro',
                'display_name' => 'Pro',
                'description' => 'Pro plan with AI generation and advanced features. 10,000 requests per month.',
                'monthly_limit' => 10000,
                'rate_limit_per_minute' => 100,
                'features' => ['read', 'generate', 'context_tags'],
                'price_monthly' => 9.99,
                'price_yearly' => 99.99,
                'is_active' => true,
            ],
            [
                'name' => 'enterprise',
                'display_name' => 'Enterprise',
                'description' => 'Enterprise plan with unlimited requests and all features including webhooks and analytics.',
                'monthly_limit' => 0, // 0 = unlimited
                'rate_limit_per_minute' => 1000,
                'features' => ['read', 'generate', 'context_tags', 'webhooks', 'analytics'],
                'price_monthly' => 99.00,
                'price_yearly' => 990.00,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::updateOrCreate(
                ['name' => $planData['name']],
                $planData
            );
        }
    }
}
