<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for handling billing webhooks.
 *
 * Note: This controller is prepared for future billing providers (Stripe, PayPal, etc.).
 * RapidAPI webhooks have been removed as part of portfolio migration.
 *
 * For portfolio/demo: Subscriptions are managed locally via API keys in the admin panel.
 * This endpoint is kept for future use when integrating with billing providers.
 */
class BillingWebhookController
{
    public function __construct(
        private readonly BillingService $billingService
    ) {}

    /**
     * Handle incoming billing webhook.
     *
     * Note: Currently not implemented. Prepared for future billing providers (Stripe, PayPal).
     * RapidAPI webhooks have been removed.
     */
    public function handle(Request $request): JsonResponse
    {
        // Webhook signature validation and processing would be implemented here for future providers
        // For now, return 501 Not Implemented
        return response()->json([
            'error' => 'Billing webhooks are not currently implemented',
            'message' => 'This endpoint is prepared for future billing providers (Stripe, PayPal, etc.). For portfolio/demo, subscriptions are managed locally via API keys.',
        ], 501);
    }
}
