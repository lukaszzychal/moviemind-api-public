<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            $bypassEnvs = config('horizon.auth.bypass_environments', []);
            $allowedEmails = config('horizon.auth.allowed_emails', []);
            $env = config('app.env', app()->environment());

            // 1. Production never bypasses: always require auth (allowed_emails or Basic Auth)
            if ($env === 'production') {
                $request = request();
                if ($request && $request->attributes->get('horizon.basic_auth_verified')) {
                    return true;
                }

                $email = optional($user)->email ?? null;
                if ($email === null) {
                    return false;
                }

                $allowedEmails = array_map('strtolower', $allowedEmails);

                return in_array(strtolower($email), $allowedEmails);
            }

            // 2. Non-production: allow bypass environments
            if (in_array($env, $bypassEnvs)) {
                return true;
            }

            // 3. Check Basic Auth middleware
            $request = request();
            if ($request && $request->attributes->get('horizon.basic_auth_verified')) {
                return true;
            }

            // 4. Check allowed emails
            $email = optional($user)->email ?? null;
            if ($email === null) {
                return false;
            }
            $allowedEmails = array_map('strtolower', $allowedEmails);

            return in_array(strtolower($email), $allowedEmails);
        });
    }

    /**
     * Configure the Horizon authorization services.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(function ($request) {
            // We defer entirely to the Gate, which handles environment checks and Basic Auth
            return Gate::check('viewHorizon', [$request->user()]);
        });
    }
}
