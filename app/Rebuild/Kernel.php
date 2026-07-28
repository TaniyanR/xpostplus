<?php

declare(strict_types=1);

namespace App\Rebuild;

use PDO;

final class Kernel
{
    private PDO $pdo;
    private string $base;

    public function __construct()
    {
        $this->base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $this->startSession();
        $this->pdo = $this->connect();
        $this->migrate();
    }

    public function run(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($this->base !== '' && $this->base !== '/' && str_starts_with($path, $this->base)) {
            $path = substr($path, strlen($this->base)) ?: '/';
        }

        $routes = [
            '/' => ['ダッシュボード', fn () => $this->dashboard()],
            '/api-posts' => ['API投稿', fn () => $this->sourcePage('api')],
            '/api-templates' => ['APIテンプレート', fn () => $this->templatePage('api')],
            '/rss-posts' => ['RSS投稿', fn () => $this->sourcePage('rss')],
            '/rss-templates' => ['RSSテンプレート', fn () => $this->templatePage('rss')],
            '/videos' => ['動画投稿', fn () => $this->sourcePage('video')],
            '/video-templates' => ['動画テンプレート', fn () => $this->templatePage('video')],
            '/posts' => ['投稿管理', fn () => $this->posts()],
            '/settings' => ['設定', fn () => $this->settings()],
            '/logout' => ['ログアウト', fn () => $this->logout()],
        ];

        if (!isset($routes[$path])) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        [$title, $handler] = $routes[$path];
        $this->layout($title, $path, $handler());
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('xpostplus_rebuild');
            session_start();
        }
    }

    private function connect(): PDO
    {
        $root = dirname(__DIR__, 2);
        $storage = $root . '/storage';
        if (!is_dir($storage)) {
            mkdir($storage, 0755, true);
        }

        $driver = getenv('DB_DRIVER') ?: 'sqlite';
        if ($driver === 'mysql') {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $name = getenv('DB_DATABASE') ?: 'xpostplus';
            $user = getenv('DB_USERNAME') ?: 'root';
            $pass = getenv('DB_PASSWORD') ?: '';
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass);
        } else {
            $pdo = new PDO('sqlite:' . $storage . '/xpostplus.sqlite');
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    private function migrate(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $id = $driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $text = $driver === 'mysql' ? 'LONGTEXT' : 'TEXT';

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS source_settings (id {$id}, source_type VARCHAR(20) NOT NULL, setting_key VARCHAR(100) NOT NULL, setting_value {$text}, updated_at DATETIME NOT NULL, UNIQUE(source_type, setting_key))");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS source_items (id {$id}, source_type VARCHAR(20) NOT NULL, service VARCHAR(30), external_id VARCHAR(190), title VARCHAR(500) NOT NULL, source_url VARCHAR(1000), media_url VARCHAR(1000), image_url VARCHAR(1000), raw_json {$text}, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS templates (id {$id}, source_type VARCHAR(20) NOT NULL, name VARCHAR(190) NOT NULL, body {$text} NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS posts (id {$id}, source_type VARCHAR(20) NOT NULL, source_item_id INTEGER, template_id INTEGER, title VARCHAR(500), body {$text} NOT NULL, media_url VARCHAR(1000), status VARCHAR(20) NOT NULL DEFAULT 'draft', copied_at DATETIME, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (id {$id}, action VARCHAR(100) NOT NULL, message {$text}, created_at DATETIME NOT NULL)");

        foreach (['api' => '標準API投稿', 'rss' => '標準RSS投稿', 'video' => '標準動画投稿'] as $type => $name) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM templates WHERE source_type = ?');
            $stmt->execute([$type]);
            if ((int)$stmt->fetchColumn() === 0) {
                $body = "{title}\n\n{url}\n{hashtags}";
                $now = date('Y-m-d H:i:s');
                $this->pdo->prepare('INSERT INTO templates (source_type, name, body, sort_order, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)')
                    ->execute([$type, $name, $body, $now, $now]);
            }
        }
    }

    private function dashboard(): string
    {
        $counts = [];
        foreach (['api', 'rss', 'video'] as $type) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM source_items WHERE source_type = ?');
            $stmt->execute([$type]);
            $counts[$type] = (int)$stmt->fetchColumn();
        }
        $draft = (int)$this->pdo->query("SELECT COUNT(*) FROM posts WHERE status='draft'")->fetchColumn();
        $posted = (int)$this->pdo->query("SELECT COUNT(*) FROM posts WHERE status='posted'")->fetchColumn();

        return '<section class="page-head"><h1>ダッシュボード</h1><p>X投稿用の素材取得、投稿作成、コピー済み管理を行います。</p></section>'
            . '<section class="stat-grid">'
            . $this->stat('API素材', $counts['api'], '/api-posts')
            . $this->stat('RSS記事', $counts['rss'], '/rss-posts')
            . $this->stat('動画素材', $counts['video'], '/videos')
            . $this->stat('未投稿', $draft, '/posts')
            . $this->stat('投稿済み', $posted, '/posts?status=posted')
            . '</section>';
    }

    private function sourcePage(string $type): string
    {
        $labels = [
            'api' => ['API投稿', 'FANZA・DUGA・SOKUMIRUから素材を取得します。'],
            'rss' => ['RSS投稿', '登録したRSSから記事を一括取得します。'],
            'video' => ['動画投稿', '動画ページURLまたはMP4 URLから素材を登録し、加工します。'],
        ];
        [$title, $description] = $labels[$type];

        $stmt = $this->pdo->prepare('SELECT * FROM templates WHERE source_type = ? ORDER BY sort_order, id LIMIT 3');
        $stmt->execute([$type]);
        $templates = $stmt->fetchAll();

        $templateHtml = '';
        foreach ($templates as $template) {
            $templateHtml .= '<option value="' . (int)$template['id'] . '">' . $this->e($template['name']) . '</option>';
        }

        $sourceForm = match ($type) {
            'api' => '<div class="source-options"><label><input type="checkbox" checked> FANZA</label><label><input type="checkbox" checked> DUGA</label><label><input type="checkbox" checked> SOKUMIRU</label></div><label>キーワード<input type="text" placeholder="空欄なら新着"></label><button class="primary" type="button">選択したAPIから一括取得</button>',
            'rss' => '<p>登録済みRSSを選択してまとめて取得します。</p><button class="primary" type="button">登録RSSから一括取得</button>',
            default => '<label>動画ページURLまたはMP4 URL<input type="url" placeholder="https://..."></label><button class="primary" type="button">動画を取得</button>',
        };

        return '<section class="page-head"><h1>' . $title . '</h1><p>' . $description . '</p></section>'
            . '<section class="work-grid"><article class="panel"><h2>1. 素材を取得</h2>' . $sourceForm . '</article>'
            . '<article class="panel"><h2>2. 投稿設定</h2><label>テンプレート<select>' . $templateHtml . '</select></label><button class="primary" type="button">選択した素材から投稿を作成</button></article></section>'
            . '<section class="panel"><h2>取得済み素材</h2><div class="empty">まだ素材はありません。上の取得ボタンから追加してください。</div></section>';
    }

    private function templatePage(string $type): string
    {
        $labels = [
            'api' => ['APIテンプレート', 'API投稿で使用するテンプレートを管理します。'],
            'rss' => ['RSSテンプレート', 'RSS投稿で使用するテンプレートを管理します。'],
            'video' => ['動画テンプレート', '動画投稿で使用するテンプレートを管理します。'],
        ];
        [$title, $description] = $labels[$type];

        $stmt = $this->pdo->prepare('SELECT * FROM templates WHERE source_type = ? ORDER BY sort_order, id LIMIT 3');
        $stmt->execute([$type]);
        $templates = $stmt->fetchAll();

        $cards = '';
        foreach ($templates as $index => $template) {
            $cards .= '<article class="template-card">'
                . '<div class="template-card-head"><div><span class="template-number">テンプレート' . ($index + 1) . '</span><h2>' . $this->e($template['name']) . '</h2></div>'
                . '<div class="button-row compact"><button class="secondary" type="button">編集</button><button class="danger" type="button">削除</button></div></div>'
                . '<pre>' . $this->e($template['body']) . '</pre>'
                . '</article>';
        }

        $count = count($templates);
        $addButton = $count < 3
            ? '<button class="primary" type="button">新規テンプレートを追加</button>'
            : '<button class="primary" type="button" disabled>最大3件まで登録済みです</button>';

        return '<section class="page-head page-head-actions"><div><h1>' . $title . '</h1><p>' . $description . '</p></div>'
            . '<div class="template-count">登録数 <strong>' . $count . ' / 3件</strong></div></section>'
            . '<section class="panel template-guide"><div><h2>利用できるショートコード</h2><p><code>{title}</code> タイトル　<code>{url}</code> URL　<code>{hashtags}</code> ハッシュタグ</p></div>' . $addButton . '</section>'
            . '<section class="template-grid">' . $cards . '</section>';
    }

    private function posts(): string
    {
        $posts = $this->pdo->query('SELECT * FROM posts ORDER BY id DESC')->fetchAll();
        if (!$posts) {
            return '<section class="page-head"><h1>投稿管理</h1><p>API・RSS・動画から作成した投稿をまとめて管理します。</p></section><section class="panel"><div class="empty"><strong>まだ投稿はありません。</strong><p>各投稿ページで素材を取得し、テンプレートを選んで投稿を作成してください。</p><div class="button-row"><a class="button" href="' . $this->url('/api-posts') . '">API投稿へ</a><a class="button" href="' . $this->url('/rss-posts') . '">RSS投稿へ</a><a class="button" href="' . $this->url('/videos') . '">動画投稿へ</a></div></div></section>';
        }
        return '<section class="page-head"><h1>投稿管理</h1><p>コピーすると自動で投稿済みになります。</p></section><section class="panel"><p>投稿一覧を表示します。</p></section>';
    }

    private function settings(): string
    {
        return '<section class="page-head"><h1>設定</h1><p>DB・ログイン・各取得元の接続設定を管理します。</p></section><section class="panel"><h2>データベース</h2><p>必要なテーブルはアクセス時に自動作成されます。SQLの手動実行は不要です。</p></section>';
    }

    private function logout(): string
    {
        $_SESSION = [];
        session_regenerate_id(true);

        return '<section class="page-head"><h1>ログアウト</h1><p>管理画面からログアウトしました。</p></section><section class="panel"><a class="button" href="' . $this->url('/') . '">管理画面へ戻る</a></section>';
    }

    private function stat(string $label, int $count, string $path): string
    {
        return '<a class="stat" href="' . $this->url($path) . '"><span>' . $this->e($label) . '</span><strong>' . $count . '</strong></a>';
    }

    private function layout(string $title, string $path, string $content): void
    {
        $groups = [
            'API' => [
                '/api-posts' => 'API投稿',
                '/api-templates' => 'APIテンプレート',
            ],
            'RSS' => [
                '/rss-posts' => 'RSS投稿',
                '/rss-templates' => 'RSSテンプレート',
            ],
            '動画' => [
                '/videos' => '動画投稿',
                '/video-templates' => '動画テンプレート',
            ],
        ];

        $nav = '<a class="' . ($path === '/' ? 'active' : '') . '" href="' . $this->url('/') . '">ダッシュボード</a>';
        foreach ($groups as $group => $items) {
            $nav .= '<div class="nav-group"><span class="nav-heading">' . $group . '</span>';
            foreach ($items as $url => $label) {
                $nav .= '<a class="' . ($url === $path ? 'active' : '') . '" href="' . $this->url($url) . '">' . $label . '</a>';
            }
            $nav .= '</div>';
        }
        $nav .= '<a class="' . ($path === '/posts' ? 'active' : '') . '" href="' . $this->url('/posts') . '">投稿管理</a>'
            . '<a class="' . ($path === '/settings' ? 'active' : '') . '" href="' . $this->url('/settings') . '">設定</a>'
            . '<a class="logout-link" href="' . $this->url('/logout') . '">ログアウト</a>';

        echo '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $this->e($title) . ' | XPostPlus</title><link rel="stylesheet" href="' . $this->url('/assets/css/rebuild.css') . '"></head><body><div class="app"><aside><h1>XPostPlus</h1><nav>' . $nav . '</nav></aside><main><header><strong>' . $this->e($title) . '</strong></header><div class="content">' . $content . '</div></main></div></body></html>';
    }

    private function url(string $path): string
    {
        return ($this->base ?: '') . $path;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
