<?php

declare(strict_types=1);

namespace App\Features;

class ai_generation_baseline_locking extends BaseFeature
{
    public function defaultState(): bool
    {
        return false;
    }
}
