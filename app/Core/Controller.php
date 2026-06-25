<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            redirect('/login');
        }
    }
    protected function userCount(): int
    {
        return (int)Database::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}
