<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    // HorizonServiceProvider: Skip in testing to prevent PHP 8.3 stack overflow
    // Horizon is not needed for tests and causes compilation issues
    ...(app()->environment('testing') ? [] : [App\Providers\HorizonServiceProvider::class]),
];
