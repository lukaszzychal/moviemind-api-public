<?php

namespace App\Features;

/**
 * Cache biografii osób w Redis.
 */
class redis_cache_bios
{
    public function resolve(mixed $scope): mixed
    {
        return true;
    }
}


