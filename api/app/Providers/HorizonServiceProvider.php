<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
     *
     * Security rules:
     * - Local and staging environments can bypass authorization
     * - Production MUST require authorization (allowed_emails must be set)
     * - Production MUST NOT be in bypass_environments
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            $currentEnv = config('app.env');
            $bypassEnvironments = collect(config('horizon.auth.bypass_environments', []))
                ->map(fn ($env) => trim((string) $env))
                ->filter()
                ->all();

            // Security safeguard: Production should NEVER bypass authorization
            // Even if accidentally configured, we enforce authentication in production
            if ($currentEnv === 'production' && in_array('production', $bypassEnvironments, true)) {
                Log::warning('Horizon: Production environment is in bypass_environments. This is a security risk!', [
                    'environment' => $currentEnv,
                    'bypass_environments' => $bypassEnvironments,
                ]);
                // Force authentication even if production is in bypass list
                $bypassEnvironments = array_filter($bypassEnvironments, fn ($env) => $env !== 'production');
            }

            if (in_array($currentEnv, $bypassEnvironments, true)) {
                return true;
            }

            $authorizedEmails = collect(config('horizon.auth.allowed_emails', []))
                ->map(fn ($email) => mb_strtolower(trim($email)))
                ->filter()
                ->all();

            // Security safeguard: Production MUST have authorized emails configured
            if ($currentEnv === 'production' && empty($authorizedEmails)) {
                Log::error('Horizon: Production environment requires HORIZON_ALLOWED_EMAILS to be set!', [
                    'environment' => $currentEnv,
                ]);

                return false;
            }

            if (empty($authorizedEmails)) {
                return false;
            }

            return in_array(mb_strtolower(optional($user)->email ?? ''), $authorizedEmails, true);
        });
    }
}
