<?php

namespace App\Features;

/**
 * Wymaga ręcznej moderacji treści przed publikacją.
 */
class human_moderation_required
{
    public function resolve(mixed $scope): mixed
    {
        return false;
    }
}
