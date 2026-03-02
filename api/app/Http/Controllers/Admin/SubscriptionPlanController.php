<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionPlanController extends Controller
{
    /**
     * List all subscription plans.
     */
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::all()->map(function (SubscriptionPlan $plan) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'display_name' => $plan->display_name,
                'features' => $plan->features,
                'is_active' => $plan->is_active,
                'monthly_limit' => $plan->monthly_limit,
                'rate_limit_per_minute' => $plan->rate_limit_per_minute,
            ];
        });

        return response()->json([
            'data' => $plans,
            'count' => $plans->count(),
        ]);
    }

    /**
     * Show a specific subscription plan.
     */
    public function show(string $id): JsonResponse
    {
        $plan = $this->findPlan($id);

        if ($plan === null) {
            return response()->json([
                'error' => 'Not found',
                'message' => 'Subscription plan not found.',
            ], 404);
        }

        return response()->json([
            'id' => $plan->id,
            'name' => $plan->name,
            'display_name' => $plan->display_name,
            'features' => $plan->features,
            'is_active' => $plan->is_active,
            'monthly_limit' => $plan->monthly_limit,
            'rate_limit_per_minute' => $plan->rate_limit_per_minute,
        ]);
    }

    /**
     * Get features of a plan.
     */
    public function getFeatures(string $id): JsonResponse
    {
        $plan = $this->findPlan($id);
        if (! $plan) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'features' => $plan->features ?? [],
        ]);
    }

    /**
     * Add a feature to a plan.
     */
    public function addFeature(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'feature' => ['required', 'string', 'max:50'],
        ]);

        $plan = $this->findPlan($id);
        if (! $plan) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $feature = $validated['feature'];
        $plan->addFeature($feature);

        return response()->json([
            'message' => "Feature '{$feature}' added to plan '{$plan->name}'.",
            'features' => $plan->features,
        ]);
    }

    /**
     * Remove a feature from a plan.
     */
    public function removeFeature(string $id, string $feature): JsonResponse
    {
        $plan = $this->findPlan($id);
        if (! $plan) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $plan->removeFeature($feature);

        return response()->json([
            'message' => "Feature '{$feature}' removed from plan '{$plan->name}'.",
            'features' => $plan->features,
        ]);
    }

    /**
     * Helper to find plan by ID or name.
     */
    private function findPlan(string $id): ?SubscriptionPlan
    {
        if (Str::isUuid($id)) {
            return SubscriptionPlan::find($id);
        }

        return SubscriptionPlan::where('name', $id)->first();
    }
}
