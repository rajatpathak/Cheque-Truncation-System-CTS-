<?php

return [
    'default' => env('DB_CONNECTION', 'oracle'),

    'connections' => [
        // Primary Oracle DB — DC Chennai
        'oracle' => [
            'driver'   => 'oracle',
            'host'     => env('DB_HOST'),
            'port'     => env('DB_PORT', '1521'),
            'database' => env('DB_DATABASE', 'CTSDB'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'charset'  => 'AL32UTF8',
            'prefix'   => '',
            'prefix_schema' => '',
            'edition'  => 'ora$base',
            'server_version' => '11g',
        ],

        // DR Oracle DB — Hyderabad (failover)
        'oracle_dr' => [
            'driver'   => 'oracle',
            'host'     => env('DB_DR_HOST'),
            'port'     => env('DB_DR_PORT', '1521'),
            'database' => env('DB_DR_DATABASE', 'CTSDB'),
            'username' => env('DB_DR_USERNAME'),
            'password' => env('DB_DR_PASSWORD'),
            'charset'  => 'AL32UTF8',
        ],

        // Legacy CTS DB (read-only — for migration)
        'oracle_legacy' => [
            'driver'   => 'oracle',
            'host'     => env('ORACLE_LEGACY_HOST'),
            'port'     => '1521',
            'database' => env('ORACLE_LEGACY_DATABASE', 'LEGACY_CTS'),
            'username' => env('ORACLE_LEGACY_USER'),
            'password' => env('ORACLE_LEGACY_PASSWORD'),
            'charset'  => 'AL32UTF8',
            'read_only'=> true,
        ],

        // CBS/Finacle Mirror (read-only account validation)
        'cbs_mirror' => [
            'driver'   => 'oracle',
            'host'     => env('CBS_MIRROR_HOST'),
            'port'     => '1521',
            'database' => env('CBS_MIRROR_DATABASE'),
            'username' => env('CBS_MIRROR_USERNAME'),
            'password' => env('CBS_MIRROR_PASSWORD'),
            'charset'  => 'AL32UTF8',
        ],
    ],

    'redis' => [
        'client'  => env('REDIS_CLIENT', 'predis'),
        'options' => ['cluster' => env('REDIS_CLUSTER', 'redis')],

        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

        'sessions' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_SESSION_DB', '2'),
        ],
    ],

    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
];
