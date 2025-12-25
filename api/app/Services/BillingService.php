<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling billing and subscription management.
 *
 * Responsibilities:
 * - Create, update, and cancel subscriptions
 * - Synchronize plans from RapidAPI
 * - Handle idempotency for webhooks
 */
class BillingService
{
    public function __construct(
        private readonly RapidApiService $rapidApiService
    ) {}

    /**
     * Create a new subscription.
     *
     * @param  string|null  $rapidApiUserId  RapidAPI user identifier
     * @param  string  $planName  Internal plan name (free, pro, enterprise)
     * @param  string|null  $apiKeyId  Associated API key ID (optional)
     * @param  string|null  $idempotencyKey  Idempotency key to prevent duplicates
     * @return Subscription The created subscription
     */
    public function createSubscription(
        ?string $rapidApiUserId,
        string $planName,
        ?string $apiKeyId = null,
        ?string $idempotencyKey = null
    ): Subscription {
        // Check for existing subscription with same idempotency key
        if ($idempotencyKey !== null) {
            $existing = Subscription::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                Log::info('Subscription creation skipped - duplicate idempotency key', [
                    'idempotency_key' => $idempotencyKey,
                    'existing_subscription_id' => $existing->id,
                ]);

                return $existing;
            }
        }

        $plan = SubscriptionPlan::where('name', $planName)->firstOrFail();

        return DB::transaction(function () use ($rapidApiUserId, $plan, $apiKeyId, $idempotencyKey) {
            $subscription = Subscription::create([
                'api_key_id' => $apiKeyId,
                'rapidapi_user_id' => $rapidApiUserId,
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'idempotency_key' => $idempotencyKey,
            ]);

            Log::info('Subscription created', [
                'subscription_id' => $subscription->id,
                'rapidapi_user_id' => $rapidApiUserId,
                'plan' => $plan->name,
            ]);

            return $subscription;
        });
    }

    /**
     * Update an existing subscription.
     *
     * @param  string  $subscriptionId  Subscription ID
     * @param  string  $planName  New plan name
     * @param  string|null  $idempotencyKey  Idempotency key to prevent duplicates
     * @return Subscription The updated subscription
     */
    public function updateSubscription(
        string $subscriptionId,
        string $planName,
        ?string $idempotencyKey = null
    ): Subscription {
        // Check for existing update with same idempotency key
        if ($idempotencyKey !== null) {
            $existing = Subscription::where('idempotency_key', $idempotencyKey)
                ->where('id', '!=', $subscriptionId)
                ->first();
            if ($existing !== null) {
                Log::info('Subscription update skipped - duplicate idempotency key', [
                    'idempotency_key' => $idempotencyKey,
                    'existing_subscription_id' => $existing->id,
                ]);

                return Subscription::findOrFail($subscriptionId);
            }
        }

        $plan = SubscriptionPlan::where('name', $planName)->firstOrFail();
        $subscription = Subscription::findOrFail($subscriptionId);

        return DB::transaction(function () use ($subscription, $plan, $planName, $idempotencyKey) {
            $subscription->update([
                'plan_id' => $plan->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            Log::info('Subscription updated', [
                'subscription_id' => $subscription->id,
                'new_plan' => $planName,
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Cancel a subscription.
     *
     * @param  string  $subscriptionId  Subscription ID
     * @param  string|null  $idempotencyKey  Idempotency key to prevent duplicates
     * @return Subscription The cancelled subscription
     */
    public function cancelSubscription(
        string $subscriptionId,
        ?string $idempotencyKey = null
    ): Subscription {
        // Check for existing cancellation with same idempotency key
        if ($idempotencyKey !== null) {
            $existing = Subscription::where('idempotency_key', $idempotencyKey)
                ->where('id', '!=', $subscriptionId)
                ->first();
            if ($existing !== null && $existing->isCancelled()) {
                Log::info('Subscription cancellation skipped - duplicate idempotency key', [
                    'idempotency_key' => $idempotencyKey,
                    'existing_subscription_id' => $existing->id,
                ]);

                return Subscription::findOrFail($subscriptionId);
            }
        }

        $subscription = Subscription::findOrFail($subscriptionId);

        return DB::transaction(function () use ($subscription, $idempotencyKey) {
            $subscription->cancel();
            if ($idempotencyKey !== null) {
                $subscription->update(['idempotency_key' => $idempotencyKey]);
            }

            Log::info('Subscription cancelled', [
                'subscription_id' => $subscription->id,
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Synchronize plan from RapidAPI.
     *
     * Maps RapidAPI plan to internal plan and updates or creates subscription.
     *
     * @param  string|null  $rapidApiUserId  RapidAPI user identifier
     * @param  string  $rapidApiPlan  RapidAPI plan (basic, pro, ultra)
     * @param  string|null  $apiKeyId  Associated API key ID (optional)
     * @param  string|null  $idempotencyKey  Idempotency key
     * @return Subscription The synchronized subscription
     */
    public function syncPlanFromRapidApi(
        ?string $rapidApiUserId,
        string $rapidApiPlan,
        ?string $apiKeyId = null,
        ?string $idempotencyKey = null
    ): Subscription {
        $mappedPlan = $this->rapidApiService->mapRapidApiPlan($rapidApiPlan);

        if ($mappedPlan === null) {
            throw new \InvalidArgumentException("Unknown RapidAPI plan: {$rapidApiPlan}");
        }

        // Try to find existing subscription
        $subscription = null;
        if ($rapidApiUserId !== null) {
            $subscription = Subscription::where('rapidapi_user_id', $rapidApiUserId)
                ->where('status', 'active')
                ->first();
        } elseif ($apiKeyId !== null) {
            $subscription = Subscription::where('api_key_id', $apiKeyId)
                ->where('status', 'active')
                ->first();
        }

        if ($subscription !== null) {
            return $this->updateSubscription($subscription->id, $mappedPlan, $idempotencyKey);
        }

        return $this->createSubscription($rapidApiUserId, $mappedPlan, $apiKeyId, $idempotencyKey);
    }

    /**
     * Find subscription by RapidAPI user ID.
     *
     * @return Subscription|null The subscription or null if not found
     */
    public function findByRapidApiUserId(string $rapidApiUserId): ?Subscription
    {
        return Subscription::where('rapidapi_user_id', $rapidApiUserId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Find subscription by API key ID.
     *
     * @return Subscription|null The subscription or null if not found
     */
    public function findByApiKeyId(string $apiKeyId): ?Subscription
    {
        return Subscription::where('api_key_id', $apiKeyId)
            ->where('status', 'active')
            ->first();
    }
}
