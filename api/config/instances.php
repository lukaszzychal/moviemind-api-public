<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Instance URLs for Modular Monolith Scaling
    |--------------------------------------------------------------------------
    |
    | Configure URLs for all instances in your Modular Monolith setup.
    | This is used by the /admin/instances endpoint to monitor all instances.
    |
    | Format: ['instance-id' => 'http://host:port']
    |
    | Example:
    | 'urls' => [
    |     'api-1' => 'http://api-1:8000',
    |     'api-2' => 'http://api-2:8000',
    |     'api-3' => 'http://api-3:8000',
    | ],
    |
    | Alternative: Use INSTANCE_URLS environment variable
    | Format: INSTANCE_URLS=api-1:http://api-1:8000,api-2:http://api-2:8000
    |
    */

    'urls' => env('INSTANCE_URLS') ? [] : [
        // Add your instance URLs here
        // 'api-1' => 'http://api-1:8000',
        // 'api-2' => 'http://api-2:8000',
    ],
];
