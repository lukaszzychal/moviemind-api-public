<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\SubscriptionPlan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for plan-based feature gating.
 *
 * Verifies if the authenticated API key's plan includes a specific feature tag.
 */
class PlanFeatureMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // Get API key from request attributes (set by ApiKeyAuth middleware)
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey === null || ! ($apiKey instanceof ApiKey)) {
            // Logic handled by previous middleware, but if we are here and strict mode is needed:
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API Key required for this feature.',
            ], 401);
        }

        // Get subscription plan
        /** @var SubscriptionPlan|null $plan */
        $plan = $apiKey->plan;

        if ($plan === null) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'No subscription plan associated with this API key.',
            ], 403);
        }

        // Check for specific feature tag
        if (! $plan->hasFeature($feature)) {
            Log::warning('Plan feature check failed', [
                'api_key_id' => $apiKey->id,
                'plan_id' => $plan->id,
                'required_feature' => $feature,
                'available_features' => $plan->features,
            ]);

            return response()->json([
                'error' => 'Forbidden',
                'message' => "Your subscription plan does not include the '{$feature}' feature.",
            ], 403);
        }

        return $next($request);
    }
}
