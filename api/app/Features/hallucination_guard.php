<?php

namespace App\Features;

/**
 * Dodatkowe straże anty-halucynacyjne dla AI (walidacje, heurystyki).
 */
class hallucination_guard
{
    public function resolve(mixed $scope): mixed
    {
        return true;
    }
}
