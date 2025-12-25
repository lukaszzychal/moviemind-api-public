<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SubscriptionPlan;

/**
 * Service for managing subscription plans.
 */
class PlanService
{
    /**
     * Get a plan by ID.
     *
     * @return SubscriptionPlan|null The plan if found, null otherwise
     */
    public function getPlan(string $planId): ?SubscriptionPlan
    {
        return SubscriptionPlan::find($planId);
    }

    /**
     * Get a plan by name (e.g., 'free', 'pro', 'enterprise').
     *
     * @return SubscriptionPlan|null The plan if found, null otherwise
     */
    public function getPlanByName(string $name): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('name', $name)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get the default plan (Free).
     *
     * @return SubscriptionPlan|null The default plan, or null if not found
     */
    public function getDefaultPlan(): ?SubscriptionPlan
    {
        return $this->getPlanByName('free');
    }

    /**
     * Check if a plan can use a specific feature.
     *
     * @param  SubscriptionPlan|null  $plan  The plan to check
     * @param  string  $feature  The feature name (e.g., 'read', 'generate', 'webhooks')
     * @return bool True if the plan has access to the feature
     */
    public function canUseFeature(?SubscriptionPlan $plan, string $feature): bool
    {
        if ($plan === null) {
            return false;
        }

        return $plan->hasFeature($feature);
    }

    /**
     * Get the rate limit for a plan and endpoint.
     *
     * @param  SubscriptionPlan|null  $plan  The plan
     * @param  string|null  $endpoint  The endpoint (optional, for future per-endpoint limits)
     * @return int The rate limit per minute
     */
    public function getRateLimit(?SubscriptionPlan $plan, ?string $endpoint = null): int
    {
        if ($plan === null) {
            // Default rate limit for keys without a plan
            return 10;
        }

        // Future: per-endpoint rate limits could be stored in plan.features or a separate table
        return $plan->rate_limit_per_minute;
    }

    /**
     * Get the monthly limit for a plan.
     *
     * @param  SubscriptionPlan|null  $plan  The plan
     * @return int The monthly limit (0 = unlimited)
     */
    public function getMonthlyLimit(?SubscriptionPlan $plan): int
    {
        if ($plan === null) {
            // Default monthly limit for keys without a plan
            return 100;
        }

        return $plan->monthly_limit;
    }
}
