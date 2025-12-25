<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add request-id and correlation-id middleware globally
        $middleware->append(\App\Http\Middleware\RequestIdMiddleware::class);

        $middleware->alias([
            'horizon.basic' => \App\Http\Middleware\HorizonBasicAuth::class,
            'admin.basic' => \App\Http\Middleware\AdminBasicAuth::class,
            'adaptive.rate.limit' => \App\Http\Middleware\AdaptiveRateLimit::class,
            'rapidapi.auth' => \App\Http\Middleware\RapidApiAuth::class,
            'plan.rate.limit' => \App\Http\Middleware\PlanBasedRateLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
