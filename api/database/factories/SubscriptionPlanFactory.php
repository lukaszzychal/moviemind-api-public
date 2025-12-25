<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(),
            'display_name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'monthly_limit' => fake()->numberBetween(100, 10000),
            'rate_limit_per_minute' => fake()->numberBetween(10, 100),
            'features' => ['read'],
            'price_monthly' => fake()->randomFloat(2, 0, 100),
            'price_yearly' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the plan is free.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'free',
            'display_name' => 'Free',
            'monthly_limit' => 100,
            'rate_limit_per_minute' => 10,
            'features' => ['read'],
            'price_monthly' => null,
            'price_yearly' => null,
        ]);
    }

    /**
     * Indicate that the plan is pro.
     */
    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'pro',
            'display_name' => 'Pro',
            'monthly_limit' => 10000,
            'rate_limit_per_minute' => 100,
            'features' => ['read', 'generate', 'context_tags'],
            'price_monthly' => 9.99,
            'price_yearly' => 99.99,
        ]);
    }

    /**
     * Indicate that the plan is enterprise.
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'enterprise',
            'display_name' => 'Enterprise',
            'monthly_limit' => 0, // Unlimited
            'rate_limit_per_minute' => 1000,
            'features' => ['read', 'generate', 'context_tags', 'webhooks', 'analytics'],
            'price_monthly' => 99.00,
            'price_yearly' => 990.00,
        ]);
    }
}
