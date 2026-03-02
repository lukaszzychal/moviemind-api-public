<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HorizonBasicAuth
{
    /**
     * Handle an incoming request.
     *
     * Basic Authentication for Horizon dashboard.
     * Bypasses authentication in configured environments (local, staging).
     * Requires Basic Auth in production with authorized email and password.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentEnv = config('app.env');
        $bypassEnvironments = collect(config('horizon.auth.bypass_environments', []))
            ->map(fn ($env) => trim((string) $env))
            ->filter()
            ->all();

        // Bypass authentication in configured environments
        if (in_array($currentEnv, $bypassEnvironments, true)) {
            return $next($request);
        }

        // Production requires Basic Auth
        $username = $request->getUser();
        $password = $request->getPassword();

        $authorizedEmails = collect(config('horizon.auth.allowed_emails', []))
            ->map(fn ($email) => mb_strtolower(trim($email)))
            ->filter()
            ->all();

        $expectedPassword = config('horizon.auth.basic_auth_password');

        // Check if credentials are provided
        if (empty($username) || empty($password)) {
            return $this->unauthorized();
        }

        // Check if email is authorized
        if (empty($authorizedEmails) || ! in_array(mb_strtolower($username), $authorizedEmails, true)) {
            return $this->unauthorized();
        }

        // Check password
        if (empty($expectedPassword) || ! hash_equals($expectedPassword, $password)) {
            return $this->unauthorized();
        }

        // Mark request as authenticated via Basic Auth for Gate to check
        $request->attributes->set('horizon.basic_auth_verified', true);
        $request->attributes->set('horizon.basic_auth_email', mb_strtolower($username));

        \Illuminate\Support\Facades\Log::info('HorizonBasicAuth: Middleware passed, setting attributes', [
            'email' => mb_strtolower($username),
            'attributes_set' => true,
        ]);

        return $next($request);
    }

    /**
     * Return 401 Unauthorized response with Basic Auth challenge.
     */
    private function unauthorized(): Response
    {
        return response('Unauthorized', 401, [
            'WWW-Authenticate' => 'Basic realm="Horizon Dashboard"',
        ]);
    }
}
