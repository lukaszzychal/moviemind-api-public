<?php

namespace App\Features;

/**
 * Polling statusów zadań (jobs) po publicznym API.
 */
class public_jobs_polling
{
    public function resolve(mixed $scope): mixed
    {
        return true;
    }
}


