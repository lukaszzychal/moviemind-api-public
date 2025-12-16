<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminBasicAuth
{
    /**
     * Handle an incoming request.
     *
     * Basic Authentication for Admin API endpoints.
     * Bypasses authentication in configured environments (local, staging).
     * Requires Basic Auth in production with authorized email and password.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentEnv = config('app.env');
        $bypassEnvironments = $this->getBypassEnvironments();

        // Bypass authentication in configured environments
        if (in_array($currentEnv, $bypassEnvironments, true)) {
            return $next($request);
        }

        // Production requires Basic Auth - enforce even if bypass is configured
        if ($currentEnv === 'production') {
            $this->enforceProductionAuth();
        }

        $username = $request->getUser();
        $password = $request->getPassword();

        $authorizedEmails = $this->getAuthorizedEmails();
        $expectedPassword = $this->getPassword();

        // Check if credentials are provided
        if (empty($username) || empty($password)) {
            return $this->unauthorized();
        }

        // Check if email is authorized
        if (empty($authorizedEmails) || ! in_array(mb_strtolower($username), $authorizedEmails, true)) {
            Log::warning('Admin API access denied - unauthorized email', [
                'email' => $username,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->unauthorized();
        }

        // Check password
        if (empty($expectedPassword) || ! hash_equals($expectedPassword, $password)) {
            Log::warning('Admin API access denied - invalid password', [
                'email' => $username,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->unauthorized();
        }

        Log::info('Admin API access granted', [
            'email' => $username,
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        return $next($request);
    }

    /**
     * Get environments that can bypass authentication.
     */
    private function getBypassEnvironments(): array
    {
        return collect(config('admin.auth.bypass_environments', []))
            ->map(fn ($env) => trim((string) $env))
            ->filter()
            ->all();
    }

    /**
     * Get authorized email addresses.
     */
    private function getAuthorizedEmails(): array
    {
        return collect(config('admin.auth.allowed_emails', []))
            ->map(fn ($email) => mb_strtolower(trim($email)))
            ->filter()
            ->all();
    }

    /**
     * Get expected password for Basic Auth.
     */
    private function getPassword(): string
    {
        return (string) config('admin.auth.basic_auth_password', '');
    }

    /**
     * Enforce authentication in production even if bypass is configured.
     */
    private function enforceProductionAuth(): void
    {
        $authorizedEmails = $this->getAuthorizedEmails();
        $password = $this->getPassword();

        if (empty($authorizedEmails)) {
            Log::error('Admin API security misconfiguration: ADMIN_ALLOWED_EMAILS is required in production');
        }

        if (empty($password)) {
            Log::error('Admin API security misconfiguration: ADMIN_BASIC_AUTH_PASSWORD is required in production');
        }
    }

    /**
     * Return 401 Unauthorized response with Basic Auth challenge.
     */
    private function unauthorized(): Response
    {
        return response('Unauthorized', 401, [
            'WWW-Authenticate' => 'Basic realm="Admin API"',
        ]);
    }
}
