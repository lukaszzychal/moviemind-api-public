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
            $bypassEnvironments = collect(config('horizon.auth.bypass_environments', []))
                ->map(fn ($env) => trim((string) $env))
                ->filter()
                ->all();

            if (in_array(config('app.env'), $bypassEnvironments, true)) {
                return true;
            }

            $authorizedEmails = collect(config('horizon.auth.allowed_emails', []))
                ->map(fn ($email) => mb_strtolower(trim($email)))
                ->filter()
                ->all();

            if (empty($authorizedEmails)) {
                return false;
            }

            return in_array(mb_strtolower(optional($user)->email ?? ''), $authorizedEmails, true);
        });
    }
}
