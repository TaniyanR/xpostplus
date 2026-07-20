<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    public function __construct(private Router $router) {}

    public function run(): void
    {
        $this->sendSecurityHeaders();
        Schema::migrate();
        $this->expireIdleSession();

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($base && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        $handler = $this->router->resolve($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
        if (!$handler) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        [$class, $method] = $handler;
        echo (new $class())->$method();
    }

    private function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
        header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; media-src 'self' https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        header('Cache-Control: no-store, private');
    }

    private function expireIdleSession(): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }

        $last = (int)($_SESSION['last_activity'] ?? time());
        if (time() - $last > 3600) {
            $_SESSION = [];
            session_destroy();
            redirect('/login');
        }

        $_SESSION['last_activity'] = time();
    }
}
