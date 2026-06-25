<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    public function __construct(private Router $router) {}
    public function run(): void
    {
        Schema::migrate();
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($base && $base !== '/' && str_starts_with($path, $base)) $path = substr($path, strlen($base)) ?: '/';
        $handler = $this->router->resolve($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
        if (!$handler) { http_response_code(404); echo '404 Not Found'; return; }
        [$class, $method] = $handler;
        echo (new $class())->$method();
    }
}
