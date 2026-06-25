<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/Helpers.php';

$config = require dirname(__DIR__, 2) . '/config/app.php';
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_name($config['session_name']);
session_start();

if (!is_dir(dirname(__DIR__, 2) . '/storage')) {
    mkdir(dirname(__DIR__, 2) . '/storage', 0755, true);
}

set_exception_handler(function (Throwable $e) use ($config): void {
    http_response_code(500);
    error_log($e->getMessage());
    if ($config['env'] === 'local') {
        echo '<pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }
    echo 'Application error.';
});
