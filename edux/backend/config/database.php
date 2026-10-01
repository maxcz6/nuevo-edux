<?php

return [

    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [

        'pgsql' => [
            'driver'         => 'pgsql',
            'url'            => env('DATABASE_URL'),
            'host'           => env('DB_HOST', 'postgres'),
            'port'           => env('DB_PORT', '5432'),
            'database'       => env('APP_ENV') === 'testing' ? env('DB_TEST_DATABASE', 'modux_test_db') : env('DB_DATABASE', 'modux_db'),
            'username'       => env('DB_USERNAME', 'modux_user'),
            'password'       => env('DB_PASSWORD', 'modux_password'),
            'charset'        => 'utf8',
            'prefix'         => '',
            'prefix_indexes' => true,
            'search_path'    => 'public',
            'sslmode'        => 'prefer',
        ],

    ],

    'migrations' => [
        'table'  => 'migrations',
        'update_date_on_publish' => true,
    ],

];
