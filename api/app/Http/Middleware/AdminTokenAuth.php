<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bypassEnvs = config('admin.auth.bypass_environments', []);

        if (is_array($bypassEnvs) && app()->environment($bypassEnvs)) {
            return $next($request);
        }

        $token = $request->header('X-Admin-Token') ?? $request->bearerToken();
        $validToken = config('admin.api_token');

        if (! $validToken || $token !== $validToken) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
