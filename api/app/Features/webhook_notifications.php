<?php

declare(strict_types=1);

namespace App\Features;

/**
 * Enables notification webhooks (incoming and outgoing).
 *
 * When enabled:
 * - Allows external systems to send notification webhooks to MovieMind API
 * - Sends webhooks to external systems when events occur
 */
class webhook_notifications extends BaseFeature {}
