<?php
return [
    'driver' => getenv('DB_DRIVER') ?: 'sqlite',
    'sqlite_path' => getenv('SQLITE_PATH') ?: dirname(__DIR__) . '/storage/database.sqlite',
    'mysql' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'xpostplus',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
];
