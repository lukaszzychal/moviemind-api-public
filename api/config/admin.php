<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Admin API Authorization
    |--------------------------------------------------------------------------
    |
    | Configure which environments may bypass Admin API authorization and which
    | user emails are permitted to access admin endpoints in protected contexts.
    |
    */

    'auth' => [
        'bypass_environments' => array_filter(
            array_map('trim', explode(',', env('ADMIN_AUTH_BYPASS_ENVS', 'local,staging')))
        ),
        'allowed_emails' => array_filter(
            array_map('trim', explode(',', env('ADMIN_ALLOWED_EMAILS', '')))
        ),
        'basic_auth_password' => env('ADMIN_BASIC_AUTH_PASSWORD'),
    ],
];
