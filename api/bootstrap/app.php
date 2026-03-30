<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies (Docker/Nginx)
        $middleware->trustProxies(at: '*');
        // Enable CORS for browser clients (frontend on separate origin).
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        // Add request-id and correlation-id middleware globally
        $middleware->append(\App\Http\Middleware\RequestIdMiddleware::class);
        $middleware->append(\App\Http\Middleware\SetLocale::class);
        // Security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, HSTS when HTTPS)
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);

        $middleware->alias([
            'horizon.basic' => \App\Http\Middleware\HorizonBasicAuth::class,
            'admin.basic' => \App\Http\Middleware\AdminBasicAuth::class,
            'admin.token' => \App\Http\Middleware\AdminTokenAuth::class,
            'adaptive.rate.limit' => \App\Http\Middleware\AdaptiveRateLimit::class,
            'api.key.auth' => \App\Http\Middleware\ApiKeyAuth::class,
            'plan.rate.limit' => \App\Http\Middleware\PlanBasedRateLimit::class,
            'plan.feature' => \App\Http\Middleware\PlanFeatureMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
