<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Accept-Language');

        if ($header) {
            $languages = explode(',', $header);
            $locale = trim(explode(';', $languages[0])[0]);

            $baseLocale = substr(trim($locale), 0, 2);

            if (in_array(strtolower($baseLocale), ['pl', 'de'])) {
                App::setLocale(strtolower($baseLocale));
            } else {
                App::setLocale('en');
            }
        }

        return $next($request);
    }
}
