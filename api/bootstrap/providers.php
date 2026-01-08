<?php

$providers = [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
];

// HorizonServiceProvider: Skip in testing to prevent PHP 8.3 stack overflow
// Horizon is not needed for tests and causes compilation issues
// Check APP_ENV directly as app() is not available at this point
if (($_ENV['APP_ENV'] ?? env('APP_ENV', 'local')) !== 'testing') {
    $providers[] = App\Providers\HorizonServiceProvider::class;
}

return $providers;
