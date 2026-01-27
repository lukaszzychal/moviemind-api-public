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
            // 1. Always allow access in local/bypass environments
            $bypassEnvs = explode(',', env('HORIZON_AUTH_BYPASS_ENVS', 'local,staging'));

            if (in_array(app()->environment(), $bypassEnvs)) {
                return true;
            }

            // 2. Check if Basic Auth middleware verified the request
            // The HorizonBasicAuth middleware sets this attribute if credentials were valid
            $request = request();
            if ($request && $request->attributes->get('horizon.basic_auth_verified')) {
                return true;
            }

            // 3. Fallback: Check if application user (e.g. Filament Admin) is authorized
            $allowedEmails = explode(',', env('HORIZON_ALLOWED_EMAILS', ''));

            return in_array(optional($user)->email, $allowedEmails);
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
