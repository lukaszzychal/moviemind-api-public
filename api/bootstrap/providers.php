<?php

$providers = [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
];

// HorizonServiceProvider: Skip in testing to prevent PHP 8.3 stack overflow
// Horizon is not needed for tests and causes compilation issues
// Use getenv() which works before .env is loaded
$appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'local');
if ($appEnv !== 'testing') {
    $providers[] = App\Providers\HorizonServiceProvider::class;
}

return $providers;
