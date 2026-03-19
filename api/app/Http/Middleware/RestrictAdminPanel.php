<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictAdminPanel
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sprawdź, czy panel admina (Filament) jest włączony w środowisku (domyślnie tak)
        // Pozwala to na wyłączenie panelu w instancji publicznego API (np. na Railway: ADMIN_PANEL_ENABLED=false)
        if (env('ADMIN_PANEL_ENABLED', true) === false || env('ADMIN_PANEL_ENABLED', true) === 'false') {
            abort(404);
        }

        return $next($request);
    }
}
