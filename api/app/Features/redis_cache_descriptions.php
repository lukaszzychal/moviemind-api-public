<?php

namespace App\Features;

/**
 * Cache opisów filmów w Redis.
 */
class redis_cache_descriptions
{
    public function resolve(mixed $scope): mixed
    {
        return true;
    }
}


