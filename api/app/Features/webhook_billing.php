<?php

namespace App\Features;

/**
 * Włącza webhooki billingowe (RapidAPI/Stripe itp.).
 */
class webhook_billing
{
    public function resolve(mixed $scope): mixed
    {
        return false;
    }
}
