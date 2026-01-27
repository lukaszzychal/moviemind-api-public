<?php

declare(strict_types=1);

return [
    'default' => env('PENNANT_DEFAULT_STORE', 'database'),

    'stores' => [
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', null),
            'table' => 'features',
        ],
    ],
];
