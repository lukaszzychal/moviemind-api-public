<?php

namespace App\Features;

/**
 * Automatyczna detekcja języka użytkownika/żądania.
 */
class locale_auto_detect
{
    public function resolve(mixed $scope): mixed
    {
        return false;
    }
}
