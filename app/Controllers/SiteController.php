<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Database, View};
use function App\Core\{flash, redirect, verify_csrf};

final class SiteController extends Controller
{
    private function prepareTable(): void
    {
        $pdo = Database::pdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $auto = $driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $pdo->exec("CREATE TABLE IF NOT EXISTS rss_sites (id $auto, name VARCHAR(190) NOT NULL, site_url VARCHAR(1000) NOT NULL, rss_url VARCHAR(1000) NOT NULL, fixed_hashtags VARCHAR(1000), enabled INTEGER NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
    }

    public function index(): string
    {
        $this->requireAuth();
        $this->prepareTable();
        $sites = Database::pdo()->query('SELECT * FROM rss_sites ORDER BY id DESC')->fetchAll();
        return View::render('sites/index', ['sites' => $sites]);
    }

    public function store(): string
    {
        $this->requireAuth();
        verify_csrf();
        $this->prepareTable();

        $name = trim((string)($_POST['name'] ?? ''));
        $siteUrl = trim((string)($_POST['site_url'] ?? ''));
        $rssUrl = trim((string)($_POST['rss_url'] ?? ''));
        $hashtags = trim((string)($_POST['fixed_hashtags'] ?? ''));

        if ($name === '' || !filter_var($siteUrl, FILTER_VALIDATE_URL) || !filter_var($rssUrl, FILTER_VALIDATE_URL)) {
            flash('error', 'サイト名、サイトURL、RSS URLを正しく入力してください。');
            redirect('/rss-posts');
        }

        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare('INSERT INTO rss_sites (name, site_url, rss_url, fixed_hashtags, enabled, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)')
            ->execute([$name, $siteUrl, $rssUrl, $hashtags, $now, $now]);
        flash('success', 'RSSサイトを登録しました。');
        redirect('/rss-posts');
    }

    public function delete(): string
    {
        $this->requireAuth();
        verify_csrf();
        $this->prepareTable();
        Database::pdo()->prepare('DELETE FROM rss_sites WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('success', 'RSSサイトを削除しました。');
        redirect('/rss-posts');
    }
}
