<?php

namespace App\Features;

/**
 * Dodatkowe ograniczenia szybkości dla planu Free.
 */
class rate_limit_free_plan
{
    public function resolve(mixed $scope): mixed
    {
        return true;
    }
}


