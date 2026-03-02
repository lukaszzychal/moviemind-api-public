<?php

declare(strict_types=1);

return [
    'default' => env('PENNANT_DEFAULT_STORE', 'database'),

    'stores' => [
        'array' => [
            'driver' => 'array',
        ],
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', null),
            'table' => 'features',
        ],
    ],
];
