<?php

declare(strict_types=1);

namespace App\Features;

/**
 * Enables billing webhooks (Stripe, PayPal, etc.).
 * Note: RapidAPI webhooks have been removed. This feature is for future billing providers.
 */
class webhook_billing extends BaseFeature {}
