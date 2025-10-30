<?php

namespace App\Features;

/**
 * Komunikaty deprecjacji API v1 (stopniowy rollout).
 */
class api_v1_deprecation_notice
{
    public function resolve(mixed $scope): mixed
    {
        return false;
    }
}
