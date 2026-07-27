<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller, Database, View};

final class DashboardController extends Controller
{
    public function index(): string
    {
        $this->requireAuth();
        $pdo = Database::pdo();

        $count = static fn (string $sql): int => (int)$pdo->query($sql)->fetchColumn();

        return View::render('dashboard', [
            'products' => $count('SELECT COUNT(*) FROM products'),
            'rssItems' => $count('SELECT COUNT(*) FROM rss_items'),
            'videos' => $count('SELECT COUNT(*) FROM video_assets'),
            'unposted' => $count("SELECT COUNT(*) FROM posts WHERE status <> 'posted'"),
            'posted' => $count("SELECT COUNT(*) FROM posts WHERE status = 'posted'"),
            'templates' => $count('SELECT COUNT(*) FROM post_templates'),
        ]);
    }
}
