<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    /**
     * Get the number of attempts to allow before being locked out.
     * We increase this significantly for local development to avoid test failures.
     */
    protected function getRateLimitAttempts(\Illuminate\Http\Request $request): int
    {
        return 1000;
    }

    /**
     * Override throttle key to ensure we don't hit existing locks.
     */
    protected function getThrottleKey(\Illuminate\Http\Request $request): string
    {
        return parent::getThrottleKey($request).'_v2';
    }
}
