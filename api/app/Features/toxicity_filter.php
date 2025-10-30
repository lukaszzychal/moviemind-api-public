<?php

namespace App\Features;

/**
 * Filtr treści toksycznych/NSFW.
 */
class toxicity_filter
{
    public function resolve(mixed $scope): mixed
    {
        return true;
    }
}
