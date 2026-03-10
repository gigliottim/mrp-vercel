<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'default' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_HOST', 'postgresql'),
            'port' => env('DB_PGSQL_PORT', '5432'),
            'database' => env('DB_PGSQL_DATABASE', 'mrp_demo'),
            'username' => env('DB_PGSQL_USERNAME', 'mrp'),
            'password' => env('DB_PGSQL_PASSWORD', 'ojp9Q6aYT3KHDE8sMS2u'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_HOST', 'postgresql'),
            'port' => env('DB_PGSQL_PORT', '5432'),
            'database' => env('DB_PGSQL_DATABASE', 'mrp_demo'),
            'username' => env('DB_PGSQL_USERNAME', 'mrp'),
            'password' => env('DB_PGSQL_PASSWORD', 'ojp9Q6aYT3KHDE8sMS2u'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],
        'mrp_auth' => [
            'driver' => 'pgsql',
            'host' => env('DB_AUTH_HOST', env('DB_PGSQL_HOST', 'postgresql')),
            'port' => env('DB_AUTH_PORT', env('DB_PGSQL_PORT', '5432')),
            'database' => env('DB_AUTH_DATABASE', 'mrp_auth'),
            'username' => env('DB_AUTH_USERNAME', env('DB_PGSQL_USERNAME', 'mrp')),
            'password' => env('DB_AUTH_PASSWORD', env('DB_PGSQL_PASSWORD', 'ojp9Q6aYT3KHDE8sMS2u')),
            'charset' => 'utf8',
            'schema' => 'public',
            'options' => [],
        ],
        'tenant' => [
            'driver' => 'pgsql',
            'host' => env('DB_TENANT_HOST', env('DB_PGSQL_HOST', 'postgresql')),
            'port' => env('DB_TENANT_PORT', env('DB_PGSQL_PORT', '5432')),
            'database' => env('DB_TENANT_DATABASE', 'mrp_demo'),
            'username' => env('DB_TENANT_USERNAME', env('DB_PGSQL_USERNAME', 'mrp')),
            'password' => env('DB_TENANT_PASSWORD', env('DB_PGSQL_PASSWORD', 'ojp9Q6aYT3KHDE8sMS2u')),
            'charset' => 'utf8',
            'schema' => 'public',
            'options' => [],
        ],
    ],
];
