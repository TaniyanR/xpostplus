<?php

declare(strict_types=1);

namespace App\Rebuild;

use PDO;

final class Kernel
{
    private ?PDO $pdo = null;
    private string $databaseError = '';
    private string $base;

    public function __construct()
    {
        $this->base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $this->startSession();
        try {
            $this->pdo = $this->connect();
            $this->migrate();
        } catch (\Throwable $e) {
            $this->databaseError = $e->getMessage();
        }
    }

    public function run(): void
    {
        $this->sendSecurityHeaders();
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($this->base !== '' && $this->base !== '/' && str_starts_with($path, $this->base)) {
            $path = substr($path, strlen($this->base)) ?: '/';
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($this->pdo === null) {
            if ($path === '/setup/database' && $method === 'POST') {
                $this->verifyCsrf();
                $this->saveDatabaseSettings('/setup/database', '/login');
            }
            $this->databaseSetupPage();
            return;
        }
        $this->expireIdleSession();

        if ($path === '/login') {
            if (!empty($_SESSION['user_id'])) {
                $this->redirect('/');
            }
            if ($method === 'POST') {
                $this->handleLogin();
            }
            $this->loginPage();
            return;
        }

        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }

        if (str_starts_with($path, '/media/')) {
            $this->serveMedia(substr($path, 7));
        }

        if ($path === '/password') {
            if ($method === 'POST') {
                $this->handlePasswordChange();
            }
            $this->layout('パスワード変更', $path, $this->passwordPage());
            return;
        }

        if ($path === '/logout') {
            if ($method !== 'POST') {
                $this->redirect('/');
            }
            $this->handleLogout();
        }

        if ($method === 'POST') {
            $this->verifyCsrf();
            match ($path) {
                '/settings/api' => $this->saveApiSettings(),
                '/settings/api-test' => $this->testApiSettings(),
                '/settings/database' => $this->saveDatabaseSettings('/settings', '/settings'),
                '/settings/profile' => $this->saveProfile(),
                '/rss-feeds/save' => $this->saveRssFeed(),
                '/rss-feeds/delete' => $this->deleteRssFeed(),
                '/rss-posts/fetch' => $this->fetchRssItems(),
                '/api-posts/fetch' => $this->fetchApiItems(),
                '/templates/save' => $this->saveTemplate(),
                '/templates/delete' => $this->deleteTemplate(),
                '/videos/analyze' => $this->analyzeVideo(),
                '/videos/edit' => $this->editVideo(),
                '/posts/generate' => $this->generatePost(),
                '/posts/save' => $this->savePost(),
                '/posts/copy' => $this->copyPost(),
                '/posts/delete' => $this->deletePosts(),
                '/source-items/delete' => $this->deleteSourceItems(),
                default => $this->notFound(),
            };
        }

        $routes = [
            '/' => ['ダッシュボード', fn () => $this->dashboard()],
            '/api-posts' => ['API投稿', fn () => $this->apiPage()],
            '/api-templates' => ['APIテンプレート', fn () => $this->templatePage('api')],
            '/rss-posts' => ['RSS投稿', fn () => $this->rssPage()],
            '/rss-templates' => ['RSSテンプレート', fn () => $this->templatePage('rss')],
            '/videos' => ['動画投稿', fn () => $this->videoPage()],
            '/video-templates' => ['動画テンプレート', fn () => $this->templatePage('video')],
            '/posts' => ['投稿管理', fn () => $this->posts()],
            '/settings' => ['設定', fn () => $this->settings()],
        ];

        if (!isset($routes[$path])) {
            $this->notFound();
        }

        [$title, $handler] = $routes[$path];
        $this->layout($title, $path, $handler());
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');
            session_name('xpostplus_session');
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

        $config = $this->databaseConfig();
        $pdo = new PDO(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [PDO::ATTR_EMULATE_PREPARES => false]
        );

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    private function migrate(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $id = $driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $text = $driver === 'mysql' ? 'LONGTEXT' : 'TEXT';

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS xpp_migrations (id {$id}, version INTEGER NOT NULL UNIQUE, applied_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS xpp_api_settings (id {$id}, service VARCHAR(30) NOT NULL UNIQUE, credentials {$text} NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, tested_at DATETIME, test_status VARCHAR(20), test_message VARCHAR(500), updated_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS xpp_rss_feeds (id {$id}, name VARCHAR(190) NOT NULL, feed_url VARCHAR(1000) NOT NULL UNIQUE, enabled INTEGER NOT NULL DEFAULT 1, last_fetched_at DATETIME, last_error VARCHAR(1000), created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS xpp_source_items (id {$id}, source_type VARCHAR(20) NOT NULL, service VARCHAR(30), external_id VARCHAR(190), feed_id INTEGER, title VARCHAR(500) NOT NULL, description {$text}, source_url VARCHAR(1000), affiliate_url VARCHAR(1000), image_url VARCHAR(1000), media_url VARCHAR(1000), actress VARCHAR(500), genre VARCHAR(500), published_at DATETIME, raw_json {$text}, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS xpp_source_media (id {$id}, source_item_id INTEGER NOT NULL, media_type VARCHAR(30) NOT NULL, media_url VARCHAR(1000) NOT NULL, local_path VARCHAR(1000), sort_order INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS xpp_video_jobs (id {$id}, source_item_id INTEGER, input_type VARCHAR(20) NOT NULL, input_url VARCHAR(1000) NOT NULL, source_path VARCHAR(1000), output_path VARCHAR(1000), status VARCHAR(30) NOT NULL DEFAULT 'pending', progress INTEGER NOT NULL DEFAULT 0, start_seconds DECIMAL(12,3), end_seconds DECIMAL(12,3), aspect_ratio VARCHAR(10) NOT NULL DEFAULT 'original', quality VARCHAR(20) NOT NULL DEFAULT 'standard', muted INTEGER NOT NULL DEFAULT 0, source_size BIGINT, output_size BIGINT, error_message {$text}, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS xpp_templates (id {$id}, source_type VARCHAR(20) NOT NULL, name VARCHAR(190) NOT NULL, body {$text} NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS xpp_posts (id {$id}, source_type VARCHAR(20) NOT NULL, source_item_id INTEGER, template_id INTEGER, title VARCHAR(500) NOT NULL, body {$text} NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', copied_at DATETIME, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS xpp_post_media (id {$id}, post_id INTEGER NOT NULL, media_type VARCHAR(30) NOT NULL, media_url VARCHAR(1000), local_path VARCHAR(1000), sort_order INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS xpp_activity_logs (id {$id}, user_id INTEGER, action VARCHAR(100) NOT NULL, target_type VARCHAR(50), target_id INTEGER, message {$text}, created_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (id {$id}, name VARCHAR(100) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id {$id}, email VARCHAR(190) NOT NULL, ip_address VARCHAR(64) NOT NULL, attempted_at DATETIME NOT NULL)");
        $this->ensureColumns('xpp_api_settings', ['service' => 'VARCHAR(30) NULL', 'credentials' => 'LONGTEXT NULL', 'enabled' => 'INTEGER NOT NULL DEFAULT 1', 'tested_at' => 'DATETIME NULL', 'test_status' => 'VARCHAR(20) NULL', 'test_message' => 'VARCHAR(500) NULL', 'updated_at' => 'DATETIME NULL']);
        $this->ensureColumns('xpp_rss_feeds', ['name' => 'VARCHAR(190) NULL', 'feed_url' => 'VARCHAR(1000) NULL', 'enabled' => 'INTEGER NOT NULL DEFAULT 1', 'last_fetched_at' => 'DATETIME NULL', 'last_error' => 'VARCHAR(1000) NULL', 'created_at' => 'DATETIME NULL', 'updated_at' => 'DATETIME NULL']);
        $this->ensureColumns('xpp_source_items', ['source_type' => 'VARCHAR(20) NULL', 'service' => 'VARCHAR(30) NULL', 'external_id' => 'VARCHAR(190) NULL', 'feed_id' => 'INTEGER NULL', 'title' => 'VARCHAR(500) NULL', 'description' => 'LONGTEXT NULL', 'source_url' => 'VARCHAR(1000) NULL', 'affiliate_url' => 'VARCHAR(1000) NULL', 'image_url' => 'VARCHAR(1000) NULL', 'media_url' => 'VARCHAR(1000) NULL', 'actress' => 'VARCHAR(500) NULL', 'genre' => 'VARCHAR(500) NULL', 'published_at' => 'DATETIME NULL', 'raw_json' => 'LONGTEXT NULL', 'created_at' => 'DATETIME NULL', 'updated_at' => 'DATETIME NULL']);
        $this->ensureColumns('xpp_source_media', ['source_item_id' => 'INTEGER NULL', 'media_type' => 'VARCHAR(30) NULL', 'media_url' => 'VARCHAR(1000) NULL', 'local_path' => 'VARCHAR(1000) NULL', 'sort_order' => 'INTEGER NOT NULL DEFAULT 0', 'created_at' => 'DATETIME NULL']);
        $this->ensureColumns('xpp_video_jobs', ['source_item_id' => 'INTEGER NULL', 'input_type' => 'VARCHAR(20) NULL', 'input_url' => 'VARCHAR(1000) NULL', 'source_path' => 'VARCHAR(1000) NULL', 'output_path' => 'VARCHAR(1000) NULL', 'status' => "VARCHAR(30) NOT NULL DEFAULT 'pending'", 'progress' => 'INTEGER NOT NULL DEFAULT 0', 'start_seconds' => 'DECIMAL(12,3) NULL', 'end_seconds' => 'DECIMAL(12,3) NULL', 'aspect_ratio' => "VARCHAR(10) NOT NULL DEFAULT 'original'", 'quality' => "VARCHAR(20) NOT NULL DEFAULT 'standard'", 'muted' => 'INTEGER NOT NULL DEFAULT 0', 'source_size' => 'BIGINT NULL', 'output_size' => 'BIGINT NULL', 'error_message' => 'LONGTEXT NULL', 'created_at' => 'DATETIME NULL', 'updated_at' => 'DATETIME NULL']);
        $this->ensureColumns('xpp_templates', ['source_type' => 'VARCHAR(20) NULL', 'name' => 'VARCHAR(190) NULL', 'body' => 'LONGTEXT NULL', 'sort_order' => 'INTEGER NOT NULL DEFAULT 0', 'created_at' => 'DATETIME NULL', 'updated_at' => 'DATETIME NULL']);
        $this->ensureColumns('xpp_posts', ['source_type' => 'VARCHAR(20) NULL', 'source_item_id' => 'INTEGER NULL', 'template_id' => 'INTEGER NULL', 'title' => 'VARCHAR(500) NULL', 'body' => 'LONGTEXT NULL', 'status' => "VARCHAR(20) NOT NULL DEFAULT 'draft'", 'copied_at' => 'DATETIME NULL', 'created_at' => 'DATETIME NULL', 'updated_at' => 'DATETIME NULL']);
        $this->ensureColumns('xpp_post_media', ['post_id' => 'INTEGER NULL', 'media_type' => 'VARCHAR(30) NULL', 'media_url' => 'VARCHAR(1000) NULL', 'local_path' => 'VARCHAR(1000) NULL', 'sort_order' => 'INTEGER NOT NULL DEFAULT 0', 'created_at' => 'DATETIME NULL']);
        $this->ensureColumns('xpp_activity_logs', ['user_id' => 'INTEGER NULL', 'action' => 'VARCHAR(100) NULL', 'target_type' => 'VARCHAR(50) NULL', 'target_id' => 'INTEGER NULL', 'message' => 'LONGTEXT NULL', 'created_at' => 'DATETIME NULL']);
        $this->createIndex('idx_login_attempts_lookup', 'login_attempts', 'email, ip_address, attempted_at');
        $this->createIndex('idx_xpp_items_type_created', 'xpp_source_items', 'source_type, created_at');
        $this->createIndex('idx_xpp_items_external', 'xpp_source_items', 'source_type, service, external_id');
        $this->createIndex('idx_xpp_items_source_url', 'xpp_source_items', 'source_url');
        $this->createIndex('idx_xpp_media_item', 'xpp_source_media', 'source_item_id, sort_order');
        $this->createIndex('idx_xpp_video_status', 'xpp_video_jobs', 'status, created_at');
        $this->createIndex('idx_xpp_templates_type', 'xpp_templates', 'source_type, sort_order');
        $this->createIndex('idx_xpp_posts_status', 'xpp_posts', 'status, created_at');
        $this->createIndex('idx_xpp_post_media_post', 'xpp_post_media', 'post_id, sort_order');
        $migration = $this->pdo->prepare('SELECT COUNT(*) FROM xpp_migrations WHERE version=1');
        $migration->execute();
        if ((int)$migration->fetchColumn() === 0) {
            $this->pdo->prepare('INSERT INTO xpp_migrations(version,applied_at) VALUES(1,?)')->execute([$this->now()]);
        }

        foreach (['api' => '標準API投稿', 'rss' => '標準RSS投稿', 'video' => '標準動画投稿'] as $type => $name) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM xpp_templates WHERE source_type = ?');
            $stmt->execute([$type]);
            if ((int)$stmt->fetchColumn() === 0) {
                $body = "{title}\n\n{url}\n{hashtags}";
                $now = date('Y-m-d H:i:s');
                $this->pdo->prepare('INSERT INTO xpp_templates (source_type, name, body, sort_order, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)')
                    ->execute([$type, $name, $body, $now, $now]);
            }
        }
    }

    private function dashboard(): string
    {
        return '<section class="page-head dashboard-head"><h1>作業を選択</h1><p>素材の種類ごとに「投稿」と「テンプレート」をまとめています。</p></section>'
            . '<section class="dashboard-source-grid">'
            . $this->dashboardGroup('API', 'FANZA・DUGA・SOKUMIRUの商品を取得', '/api-posts', '/api-templates')
            . $this->dashboardGroup('RSS', '登録したRSSから記事を取得', '/rss-posts', '/rss-templates')
            . $this->dashboardGroup('動画', '動画を取得・編集して素材を作成', '/videos', '/video-templates')
            . '</section>'
            . '<section class="dashboard-management">'
            . $this->guideCard('投稿管理', '作成した投稿のコピー・編集・削除を行います。', '/posts')
            . $this->guideCard('設定', 'DB・API・ログイン情報を設定します。', '/settings')
            . '</section>';
    }

    private function apiPage(): string
    {
        $items = $this->items('api');
        $statuses = $this->apiStatuses();
        $checks = '';
        foreach (['fanza' => 'FANZA', 'duga' => 'DUGA', 'sokumiru' => 'SOKUMIRU'] as $key => $label) {
            $status = $statuses[$key] ?? '未設定';
            $checks .= '<label><input type="checkbox" name="services[]" value="' . $key . '"' . ($status === '未設定' ? ' disabled' : '') . '> ' . $label . '<small>' . $this->e($status) . '</small></label>';
        }
        return $this->flashHtml()
            . '<section class="page-head"><h1>API投稿</h1><p>APIから商品を取得し、必要な素材を選んで投稿を作成します。</p></section>'
            . '<section class="panel"><form method="post" action="' . $this->url('/api-posts/fetch') . '">' . $this->csrfField()
            . '<div class="source-options">' . $checks . '</div><label>キーワード<input name="keyword" type="text" maxlength="190"></label>'
            . '<div class="button-row left"><button class="primary" type="submit">取得する</button><a class="secondary" href="' . $this->url('/settings') . '">API設定を開く</a></div></form></section>'
            . $this->itemList('api', $items);
    }

    private function rssPage(): string
    {
        $feeds = $this->pdo->query('SELECT * FROM xpp_rss_feeds ORDER BY id DESC')->fetchAll();
        $rows = '';
        foreach ($feeds as $feed) {
            $rows .= '<tr><td>' . $this->e($feed['name']) . '</td><td class="url-cell">' . $this->e($feed['feed_url']) . '</td><td>'
                . '<form method="post" action="' . $this->url('/rss-feeds/delete') . '" onsubmit="return confirm(\'削除しますか？\')">' . $this->csrfField() . '<input type="hidden" name="id" value="' . (int)$feed['id'] . '"><button class="danger">削除</button></form></td></tr>';
        }
        $feedOptions = '';
        foreach ($feeds as $feed) {
            $feedOptions .= '<label><input type="checkbox" name="feed_ids[]" value="' . (int)$feed['id'] . '" checked> ' . $this->e($feed['name']) . '</label>';
        }
        return $this->flashHtml()
            . '<section class="page-head"><h1>RSS投稿</h1><p>RSSを登録・取得し、必要な記事から投稿を作成します。</p></section>'
            . '<section class="work-grid"><article class="panel"><h2>RSS登録</h2><form method="post" action="' . $this->url('/rss-feeds/save') . '">' . $this->csrfField()
            . '<label>RSS名<input name="name" required maxlength="190"></label><label>RSS URL<input name="feed_url" type="url" required maxlength="1000"></label><button class="primary">登録</button></form></article>'
            . '<article class="panel"><h2>RSS取得</h2><form method="post" action="' . $this->url('/rss-posts/fetch') . '">' . $this->csrfField()
            . '<div class="check-list">' . ($feedOptions ?: '<p>先にRSSを登録してください。</p>') . '</div><button class="primary"' . (!$feeds ? ' disabled' : '') . '>選択したRSSを取得</button></form></article></section>'
            . '<section class="panel"><h2>登録済みRSS</h2><div class="table-wrap"><table><thead><tr><th>名前</th><th>URL</th><th>操作</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="3">未登録です。</td></tr>') . '</tbody></table></div></section>'
            . $this->itemList('rss', $this->items('rss'));
    }

    private function videoPage(): string
    {
        $jobs = $this->pdo->query('SELECT * FROM xpp_video_jobs ORDER BY id DESC LIMIT 20')->fetchAll();
        $cards = '';
        foreach ($jobs as $job) {
            $media = $job['output_path'] ?: $job['source_path'];
            $cards .= '<article class="video-card"><h3>動画 #' . (int)$job['id'] . '</h3><p>状態：' . $this->e($job['status']) . '</p>'
                . ($media ? '<video controls preload="metadata" src="' . $this->e($this->mediaUrl($media)) . '"></video>' : '')
                . '<form method="post" action="' . $this->url('/videos/edit') . '">' . $this->csrfField() . '<input type="hidden" name="job_id" value="' . (int)$job['id'] . '">'
                . '<label>投稿素材のタイトル<input name="title" required maxlength="500" value="編集動画 #' . (int)$job['id'] . '"></label><div class="form-grid"><label>開始（秒）<input name="start_seconds" type="number" min="0" step="0.1" value="0"></label><label>終了（秒）<input name="end_seconds" type="number" min="0" step="0.1"></label>'
                . '<label>縦横比<select name="aspect_ratio"><option value="original">元の比率</option><option value="16:9">16:9</option><option value="1:1">1:1</option><option value="9:16">9:16</option></select></label>'
                . '<label>画質<select name="quality"><option value="high">高画質</option><option value="standard" selected>標準</option><option value="small">容量優先</option></select></label></div>'
                . '<label class="inline-check"><input type="checkbox" name="muted" value="1"> 音声を消す</label><button class="primary">編集を実行</button></form></article>';
        }
        return $this->flashHtml()
            . '<section class="page-head"><h1>動画投稿</h1><p>公開動画ページまたはMP4 URLを解析し、編集して投稿素材を作ります。</p></section>'
            . '<section class="panel"><form method="post" action="' . $this->url('/videos/analyze') . '" enctype="multipart/form-data">' . $this->csrfField()
            . '<label>動画ページURLまたはMP4 URL<input name="video_url" type="url" required maxlength="1000"></label>'
            . '<p class="help">ログイン・DRMが必要な動画には対応しません。解析できない場合はMP4 URLを直接入力してください。</p><button class="primary">解析してダウンロード</button></form></section>'
            . '<section class="video-grid">' . ($cards ?: '<div class="empty">動画素材はありません。</div>') . '</section>'
            . $this->itemList('video', $this->items('video'));
    }

    private function templatePage(string $type): string
    {
        $labels = [
            'api' => ['APIテンプレート', 'API投稿で使用するテンプレートを管理します。'],
            'rss' => ['RSSテンプレート', 'RSS投稿で使用するテンプレートを管理します。'],
            'video' => ['動画テンプレート', '動画投稿で使用するテンプレートを管理します。'],
        ];
        [$title, $description] = $labels[$type];

        $stmt = $this->pdo->prepare('SELECT * FROM xpp_templates WHERE source_type = ? ORDER BY sort_order, id LIMIT 3');
        $stmt->execute([$type]);
        $templates = $stmt->fetchAll();

        $cards = '';
        foreach ($templates as $index => $template) {
            $cards .= '<article class="template-card">'
                . '<div class="template-card-head"><div><span class="template-number">テンプレート' . ($index + 1) . '</span><h2>' . $this->e($template['name']) . '</h2></div>'
                . '<form method="post" action="' . $this->url('/templates/delete') . '" onsubmit="return confirm(\'削除しますか？\')">' . $this->csrfField() . '<input type="hidden" name="id" value="' . (int)$template['id'] . '"><input type="hidden" name="source_type" value="' . $type . '"><button class="danger">削除</button></form></div>'
                . '<form method="post" action="' . $this->url('/templates/save') . '">' . $this->csrfField() . '<input type="hidden" name="id" value="' . (int)$template['id'] . '"><input type="hidden" name="source_type" value="' . $type . '">'
                . '<label>テンプレート名<input name="name" value="' . $this->e($template['name']) . '" required maxlength="190"></label><label>本文<textarea name="body" rows="7" required>' . $this->e($template['body']) . '</textarea></label><button class="primary">保存</button></form>'
                . '</article>';
        }

        $count = count($templates);
        $addButton = $count < 3
            ? '<details class="new-template"><summary class="primary">新規テンプレートを追加</summary><form method="post" action="' . $this->url('/templates/save') . '">' . $this->csrfField() . '<input type="hidden" name="source_type" value="' . $type . '"><label>テンプレート名<input name="name" required maxlength="190"></label><label>本文<textarea name="body" rows="7" required>{title}' . "\n\n" . '{url}' . "\n" . '{hashtags}</textarea></label><button class="primary">登録</button></form></details>'
            : '<button class="primary" type="button" disabled>最大3件まで登録済みです</button>';

        return $this->flashHtml() . '<section class="page-head page-head-actions"><div><h1>' . $title . '</h1><p>' . $description . '</p></div>'
            . '<div class="template-count">登録数 <strong>' . $count . ' / 3件</strong></div></section>'
            . '<section class="panel template-guide"><div><h2>利用できるショートコード</h2><p><code>{title}</code> タイトル　<code>{url}</code> URL　<code>{hashtags}</code> ハッシュタグ</p></div>' . $addButton . '</section>'
            . '<section class="template-grid">' . $cards . '</section>';
    }

    private function posts(): string
    {
        $status = ($_GET['status'] ?? 'draft') === 'posted' ? 'posted' : 'draft';
        $stmt = $this->pdo->prepare('SELECT p.*, t.name template_name FROM xpp_posts p LEFT JOIN xpp_templates t ON t.id=p.template_id WHERE p.status=? ORDER BY p.id DESC');
        $stmt->execute([$status]);
        $posts = $stmt->fetchAll();
        if (!$posts) {
            return $this->flashHtml() . '<section class="page-head"><h1>投稿管理</h1><p>API・RSS・動画から作成した投稿をまとめて管理します。</p></section>' . $this->postTabs($status) . '<section class="panel"><div class="empty"><strong>対象の投稿はありません。</strong></div></section>';
        }
        $rows = '';
        foreach ($posts as $post) {
            $copyLabel = $status === 'posted' ? '再コピー' : 'コピー';
            $mediaStmt = $this->pdo->prepare('SELECT * FROM xpp_post_media WHERE post_id=? ORDER BY sort_order,id');
            $mediaStmt->execute([(int)$post['id']]);
            $mediaHtml = '';
            foreach ($mediaStmt->fetchAll() as $media) {
                $src = $media['local_path'] ? $this->mediaUrl($media['local_path']) : $media['media_url'];
                if (!$src) continue;
                $preview = $media['media_type'] === 'video' ? '<video controls preload="metadata" src="' . $this->e($src) . '"></video>' : '<img src="' . $this->e($src) . '" loading="lazy" alt="">';
                $mediaHtml .= '<div class="post-media">' . $preview . '<a class="secondary" href="' . $this->e($src) . '" target="_blank" rel="noopener">メディアを開く</a></div>';
            }
            $rows .= '<article class="post-card"><label class="select-box"><input form="bulk-delete" type="checkbox" name="ids[]" value="' . (int)$post['id'] . '"></label>'
                . '<div class="post-main"><div class="post-meta"><span>' . $this->e(strtoupper($post['source_type'])) . '</span><span>' . $this->e($post['template_name'] ?? 'テンプレートなし') . '</span><span>' . $this->e($post['created_at']) . '</span></div>'
                . $mediaHtml . '<form method="post" action="' . $this->url('/posts/save') . '">' . $this->csrfField() . '<input type="hidden" name="id" value="' . (int)$post['id'] . '"><input type="hidden" name="status" value="' . $status . '"><label>タイトル<input name="title" value="' . $this->e($post['title']) . '" required></label><label>投稿本文<textarea name="body" rows="6" required>' . $this->e($post['body']) . '</textarea></label><button class="secondary">編集を保存</button></form></div>'
                . '<div class="post-actions"><form class="copy-form" method="post" action="' . $this->url('/posts/copy') . '">' . $this->csrfField() . '<input type="hidden" name="id" value="' . (int)$post['id'] . '"><button class="primary" data-copy-text="' . $this->e($post['body']) . '">' . $copyLabel . '</button></form>'
                . '<form method="post" action="' . $this->url('/posts/delete') . '" onsubmit="return confirm(\'この投稿を削除しますか？\')">' . $this->csrfField() . '<input type="hidden" name="id" value="' . (int)$post['id'] . '"><button class="danger">削除</button></form></div></article>';
        }
        return $this->flashHtml() . '<section class="page-head"><h1>投稿管理</h1><p>コピーすると自動で投稿済みになります。</p></section>' . $this->postTabs($status)
            . '<form id="bulk-delete" method="post" action="' . $this->url('/posts/delete') . '" onsubmit="return confirm(\'選択した投稿を削除しますか？\')">' . $this->csrfField() . '<button class="danger">選択した投稿を削除</button></form><section class="post-list">' . $rows . '</section>';
    }

    private function settings(): string
    {
        $cards = '';
        $fields = [
            'fanza' => ['FANZA', ['api_id' => 'API ID', 'affiliate_id' => 'アフィリエイトID']],
            'duga' => ['DUGA', ['appid' => 'アプリケーションID', 'agentid' => '代理店ID', 'bannerid' => 'バナーID']],
            'sokumiru' => ['SOKUMIRU', ['api_key' => 'API KEY', 'affiliate_id' => 'アフィリエイトID']],
        ];
        foreach ($fields as $service => [$label, $inputs]) {
            $saved = $this->apiCredentials($service);
            $form = '';
            foreach ($inputs as $key => $caption) {
                $type = $key === 'endpoint' ? 'url' : 'password';
                $placeholder = isset($saved[$key]) && $saved[$key] !== '' ? '保存済み（変更する場合のみ入力）' : '';
                $form .= '<label>' . $caption . '<input type="' . $type . '" name="credentials[' . $key . ']" placeholder="' . $this->e($placeholder) . '"></label>';
            }
            $cards .= '<article class="panel"><h2>' . $label . '</h2><form method="post" action="' . $this->url('/settings/api') . '">' . $this->csrfField() . '<input type="hidden" name="service" value="' . $service . '">' . $form . '<label class="inline-check"><input type="checkbox" name="enabled" value="1" checked> 有効</label><button class="primary">保存</button></form>'
                . '<form method="post" action="' . $this->url('/settings/api-test') . '">' . $this->csrfField() . '<input type="hidden" name="service" value="' . $service . '"><button class="secondary">接続テスト</button></form></article>';
        }
        $db = $this->databaseConfig();
        $userStmt = $this->pdo->prepare('SELECT name,email FROM users WHERE id=?');
        $userStmt->execute([(int)$_SESSION['user_id']]);
        $user = $userStmt->fetch() ?: ['name' => '', 'email' => ''];
        return $this->flashHtml() . '<section class="page-head"><h1>設定</h1><p>DB・API・ログイン情報を管理します。</p></section><section class="settings-grid">' . $cards . '</section>'
            . '<section class="panel"><h2>MariaDB設定</h2><p>保存前に接続テストを行います。パスワードは変更する場合だけ入力してください。</p><form method="post" action="' . $this->url('/settings/database') . '">' . $this->csrfField()
            . '<div class="form-grid"><label>DB名<input name="database" value="' . $this->e($db['database']) . '" required></label><label>DBユーザー名<input name="username" value="' . $this->e($db['username']) . '" required></label></div><label>DBパスワード<input name="password" type="password" placeholder="保存済み（変更する場合のみ入力）"></label><button class="primary">接続テストして保存</button></form></section>'
            . '<section class="panel password-panel"><h2>ログイン情報</h2><form method="post" action="' . $this->url('/settings/profile') . '">' . $this->csrfField()
            . '<label>ユーザー名<input name="name" value="' . $this->e($user['name']) . '" required maxlength="100"></label><label>メールアドレス<input name="email" type="email" value="' . $this->e($user['email']) . '" required maxlength="190"></label><button class="primary">ログイン情報を保存</button></form></section>'
            . $this->passwordPage();
    }

    private function saveProfile(): never
    {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('ユーザー名とメールアドレスを正しく入力してください。', '/settings');
        }
        try {
            $this->pdo->prepare('UPDATE users SET name=?,email=?,updated_at=? WHERE id=?')->execute([$name, $email, $this->now(), (int)$_SESSION['user_id']]);
        } catch (\PDOException) {
            $this->fail('そのメールアドレスは既に使用されています。', '/settings');
        }
        $_SESSION['user_name'] = $name;
        $this->success('ログイン情報を保存しました。', '/settings');
    }

    private function saveApiSettings(): never
    {
        $service = (string)($_POST['service'] ?? '');
        $required = [
            'fanza' => ['api_id', 'affiliate_id'],
            'duga' => ['appid', 'agentid', 'bannerid'],
            'sokumiru' => ['api_key', 'affiliate_id'],
        ];
        if (!isset($required[$service])) {
            $this->fail('未対応のAPIです。', '/settings');
        }
        $current = $this->apiCredentials($service);
        $input = is_array($_POST['credentials'] ?? null) ? $_POST['credentials'] : [];
        foreach ($required[$service] as $key) {
            $value = trim((string)($input[$key] ?? ''));
            if ($value !== '') {
                $current[$key] = $value;
            }
            if (empty($current[$key])) {
                $this->fail('必須項目をすべて入力してください。', '/settings');
            }
        }
        try {
            $payload = $this->encryptCredentials($current);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), '/settings');
        }
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $now = $this->now();
        $exists = $this->pdo->prepare('SELECT id FROM xpp_api_settings WHERE service=?');
        $exists->execute([$service]);
        if ($exists->fetchColumn()) {
            $this->pdo->prepare('UPDATE xpp_api_settings SET credentials=?,enabled=?,updated_at=? WHERE service=?')->execute([$payload, $enabled, $now, $service]);
        } else {
            $this->pdo->prepare('INSERT INTO xpp_api_settings(service,credentials,enabled,updated_at) VALUES(?,?,?,?)')->execute([$service, $payload, $enabled, $now]);
        }
        $this->log('api_settings_saved', 'api', null, $service);
        $this->success('API設定を保存しました。', '/settings');
    }

    private function databaseConfig(): array
    {
        $path = dirname(__DIR__, 2) . '/storage/config/database.json';
        $saved = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
        return [
            'host' => 'localhost',
            'port' => '3306',
            'database' => (string)($saved['database'] ?? getenv('DB_DATABASE') ?: ''),
            'username' => (string)($saved['username'] ?? getenv('DB_USERNAME') ?: ''),
            'password' => (string)($saved['password'] ?? getenv('DB_PASSWORD') ?: ''),
        ];
    }

    private function saveDatabaseSettings(string $failurePath, string $successPath): never
    {
        $current = $this->databaseConfig();
        $config = [
            'host' => 'localhost',
            'port' => '3306',
            'database' => trim((string)($_POST['database'] ?? '')),
            'username' => trim((string)($_POST['username'] ?? '')),
            'password' => (string)($_POST['password'] ?? '') !== '' ? (string)$_POST['password'] : $current['password'],
        ];
        if ($config['database'] === '' || $config['username'] === '') {
            $this->fail('MariaDB設定を正しく入力してください。', $failurePath);
        }
        try {
            new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4", $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
        } catch (\PDOException) {
            $this->fail('MariaDBへ接続できません。DB名・DBユーザー名・DBパスワードを確認してください。', $failurePath);
        }
        $dir = dirname(__DIR__, 2) . '/storage/config';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            $this->fail('DB設定の保存フォルダを作成できません。', $failurePath);
        }
        $path = $dir . '/database.json';
        if (file_put_contents($path, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) {
            $this->fail('DB設定を保存できません。', $failurePath);
        }
        @chmod($path, 0600);
        $this->success('MariaDB設定を保存しました。不足するテーブル・カラム・インデックスを自動作成します。', $successPath);
    }

    private function databaseSetupPage(): void
    {
        $db = $this->databaseConfig();
        $error = $this->pullFlash('error');
        $notice = $error !== '' ? $error : 'MariaDBへ接続できません。最初に接続情報を設定してください。';
        echo '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>DB初期設定 | XPostPlus</title><link rel="stylesheet" href="' . $this->asset('/assets/css/rebuild.css') . '"></head><body class="login-body"><main class="login-shell setup-shell"><section class="login-card"><h1>XPostPlus</h1><p>MariaDB初期設定</p><div class="notice error">' . $this->e($notice) . '</div>'
            . '<form method="post" action="' . $this->url('/setup/database') . '">' . $this->csrfField()
            . '<label>DB名<input name="database" value="' . $this->e($db['database']) . '" required></label><label>DBユーザー名<input name="username" value="' . $this->e($db['username']) . '" required></label>'
            . '<label>DBパスワード<input name="password" type="password" autocomplete="new-password"></label><button class="primary login-button">接続テストして保存</button></form>'
            . '<p class="help">DB自体はサーバー側で作成してください。接続成功後、アプリ用の不足テーブル・カラム・インデックスは自動作成されます。</p></section></main></body></html>';
    }

    private function testApiSettings(): never
    {
        $service = (string)($_POST['service'] ?? '');
        try {
            $items = $this->requestApi($service, '', 1);
            $message = '接続に成功しました。取得件数：' . count($items);
            $status = 'success';
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $status = 'error';
        }
        $this->pdo->prepare('UPDATE xpp_api_settings SET tested_at=?,test_status=?,test_message=? WHERE service=?')
            ->execute([$this->now(), $status, mb_substr($message, 0, 500), $service]);
        $status === 'success' ? $this->success($message, '/settings') : $this->fail($message, '/settings');
    }

    private function fetchApiItems(): never
    {
        $services = array_values(array_intersect((array)($_POST['services'] ?? []), ['fanza', 'duga', 'sokumiru']));
        if (!$services) {
            $this->fail('取得対象を選択してください。', '/api-posts');
        }
        $keyword = trim((string)($_POST['keyword'] ?? ''));
        $count = 0;
        try {
            foreach ($services as $service) {
                foreach ($this->requestApi($service, $keyword, 100) as $item) {
                    $this->upsertItem('api', $service, $item);
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), '/api-posts');
        }
        $this->success($count . '件の商品を取得しました。', '/api-posts');
    }

    private function requestApi(string $service, string $keyword, int $hits): array
    {
        $credentials = $this->apiCredentials($service);
        if (!$credentials) {
            throw new \RuntimeException(strtoupper($service) . 'のAPI設定がありません。');
        }
        if ($service === 'fanza') {
            $url = 'https://api.dmm.com/affiliate/v3/ItemList?' . http_build_query([
                'api_id' => $credentials['api_id'], 'affiliate_id' => $credentials['affiliate_id'],
                'site' => 'FANZA', 'service' => 'digital', 'floor' => 'videoa',
                'hits' => min(100, $hits), 'sort' => 'date', 'keyword' => $keyword, 'output' => 'json',
            ]);
            $data = $this->httpJson($url);
            if ((int)($data['result']['status'] ?? 0) !== 200) {
                throw new \RuntimeException('FANZA APIがエラーを返しました。');
            }
            $rows = $this->normalizeRows($data['result']['items'] ?? [], 'content_id');
            return array_map(fn(array $i): array => [
                'external_id' => (string)($i['content_id'] ?? $i['product_id'] ?? ''),
                'title' => (string)($i['title'] ?? ''), 'description' => '',
                'source_url' => (string)($i['URL'] ?? ''), 'affiliate_url' => (string)($i['affiliateURL'] ?? ''),
                'image_url' => (string)($i['imageURL']['large'] ?? $i['imageURL']['list'] ?? ''),
                'media_url' => (string)($i['sampleMovieURL']['size_720_480'] ?? $i['sampleMovieURL']['size_644_414'] ?? ''),
                'actress' => $this->names($i['iteminfo']['actress'] ?? []),
                'genre' => $this->names($i['iteminfo']['genre'] ?? []),
                'published_at' => $this->dateValue($i['date'] ?? null), 'raw' => $i,
                'images' => (array)($i['sampleImageURL']['sample_l']['image'] ?? []),
            ], $rows);
        }
        if ($service === 'duga') {
            $url = 'https://affapi.duga.jp/search?' . http_build_query([
                'version' => '1.2', 'appid' => $credentials['appid'], 'agentid' => $credentials['agentid'],
                'bannerid' => $credentials['bannerid'], 'format' => 'json', 'keyword' => $keyword,
                'hits' => min(100, $hits), 'adult' => 1, 'sort' => 'new',
            ]);
            $data = $this->httpJson($url);
            $rows = $data['items']['item'] ?? $data['items'] ?? [];
            $rows = $this->normalizeRows($rows, 'productid');
            return array_map(function (array $row): array {
                $i = (array)($row['item'] ?? $row);
                $performers = (array)($i['performer']['data'] ?? []);
                $categories = (array)($i['category']['data'] ?? []);
                return [
                    'external_id' => (string)($i['productid'] ?? ''), 'title' => (string)($i['title'] ?? ''),
                    'description' => (string)($i['caption'] ?? ''), 'source_url' => (string)($i['url'] ?? ''),
                    'affiliate_url' => (string)($i['affiliateurl'] ?? ''),
                    'image_url' => (string)($i['jacketimage']['large'] ?? $i['posterimage']['large'] ?? ''),
                    'media_url' => (string)($i['samplemovie']['midium']['movie'] ?? ''),
                    'actress' => $this->names($performers), 'genre' => $this->names($categories),
                    'published_at' => $this->dateValue($i['opendate'] ?? $i['releasedate'] ?? null), 'raw' => $i,
                    'images' => array_values(array_filter((array)($i['thumbnail']['image'] ?? []), 'is_string')),
                ];
            }, $rows);
        }
        if ($service === 'sokumiru') {
            $url = 'https://sokmil-ad.com/api/v1/Item?' . http_build_query([
                'affiliate_id' => $credentials['affiliate_id'], 'api_key' => $credentials['api_key'],
                'output' => 'json', 'hits' => min(100, $hits), 'sort' => 'date', 'category' => 'av', 'keyword' => $keyword,
            ]);
            $data = $this->httpJson($url);
            if ((int)($data['result']['status'] ?? 0) !== 200) {
                throw new \RuntimeException('SOKUMIRU APIがエラーを返しました。');
            }
            $rows = $this->normalizeRows($data['result']['items'] ?? [], 'id');
            return array_map(fn(array $i): array => [
                'external_id' => (string)($i['id'] ?? ''), 'title' => (string)($i['title'] ?? ''),
                'description' => '', 'source_url' => (string)($i['URL'] ?? ''),
                'affiliate_url' => (string)($i['affiliateURL'] ?? ''),
                'image_url' => (string)($i['imageURL']['large'] ?? $i['imageURL']['list'] ?? ''),
                'media_url' => (string)($i['sampleMovieURL']['url'] ?? ''),
                'actress' => $this->names($i['iteminfo']['actor'] ?? []),
                'genre' => $this->names($i['iteminfo']['genre'] ?? []),
                'published_at' => $this->dateValue($i['date'] ?? null), 'raw' => $i,
                'images' => array_values(array_filter((array)($i['sampleImageURL']['image'] ?? []), 'is_string')),
            ], $rows);
        }
        throw new \RuntimeException('未対応のAPIです。');
    }

    private function saveRssFeed(): never
    {
        $name = trim((string)($_POST['name'] ?? ''));
        $url = trim((string)($_POST['feed_url'] ?? ''));
        $this->assertPublicUrl($url);
        if ($name === '') {
            $this->fail('RSS名を入力してください。', '/rss-posts');
        }
        try {
            $this->pdo->prepare('INSERT INTO xpp_rss_feeds(name,feed_url,enabled,created_at,updated_at) VALUES(?,?,1,?,?)')->execute([$name, $url, $this->now(), $this->now()]);
        } catch (\PDOException) {
            $this->fail('同じRSS URLは登録できません。', '/rss-posts');
        }
        $this->success('RSSを登録しました。', '/rss-posts');
    }

    private function deleteRssFeed(): never
    {
        $this->pdo->prepare('DELETE FROM xpp_rss_feeds WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        $this->success('RSSを削除しました。', '/rss-posts');
    }

    private function fetchRssItems(): never
    {
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['feed_ids'] ?? []))));
        if (!$ids) {
            $this->fail('RSSを選択してください。', '/rss-posts');
        }
        $count = 0;
        foreach ($ids as $id) {
            $stmt = $this->pdo->prepare('SELECT * FROM xpp_rss_feeds WHERE id=? AND enabled=1');
            $stmt->execute([$id]);
            $feed = $stmt->fetch();
            if (!$feed) {
                continue;
            }
            try {
                $xml = $this->httpGet((string)$feed['feed_url'], 5_000_000);
                $parsed = @simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
                if (!$parsed) {
                    throw new \RuntimeException('RSSを解析できません。');
                }
                $entries = isset($parsed->channel->item) ? $parsed->channel->item : $parsed->entry;
                foreach ($entries as $entry) {
                    $link = isset($entry->link['href']) ? (string)$entry->link['href'] : (string)$entry->link;
                    $item = ['external_id' => sha1($link), 'title' => (string)$entry->title,
                        'description' => strip_tags((string)($entry->description ?? $entry->summary ?? $entry->content)),
                        'source_url' => $link, 'affiliate_url' => '', 'image_url' => $this->rssImage($entry),
                        'media_url' => '', 'actress' => '', 'genre' => '', 'published_at' => $this->dateValue((string)($entry->pubDate ?? $entry->published ?? $entry->updated)),
                        'raw' => json_decode(json_encode($entry), true) ?: [], 'images' => []];
                    $this->upsertItem('rss', 'rss', $item, $id);
                    $count++;
                }
                $this->pdo->prepare('UPDATE xpp_rss_feeds SET last_fetched_at=?,last_error=NULL WHERE id=?')->execute([$this->now(), $id]);
            } catch (\Throwable $e) {
                $this->pdo->prepare('UPDATE xpp_rss_feeds SET last_error=? WHERE id=?')->execute([mb_substr($e->getMessage(), 0, 1000), $id]);
            }
        }
        $this->success($count . '件の記事を取得しました。', '/rss-posts');
    }

    private function saveTemplate(): never
    {
        $type = (string)($_POST['source_type'] ?? '');
        if (!in_array($type, ['api', 'rss', 'video'], true)) {
            $this->notFound();
        }
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        if ($name === '' || $body === '') {
            $this->fail('名前と本文を入力してください。', '/' . ($type === 'video' ? 'video' : $type) . '-templates');
        }
        if ($id > 0) {
            $this->pdo->prepare('UPDATE xpp_templates SET name=?,body=?,updated_at=? WHERE id=? AND source_type=?')->execute([$name, $body, $this->now(), $id, $type]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM xpp_templates WHERE source_type=?');
            $stmt->execute([$type]);
            if ((int)$stmt->fetchColumn() >= 3) {
                $this->fail('テンプレートは最大3件です。', '/' . ($type === 'video' ? 'video' : $type) . '-templates');
            }
            $this->pdo->prepare('INSERT INTO xpp_templates(source_type,name,body,sort_order,created_at,updated_at) VALUES(?,?,?,99,?,?)')->execute([$type, $name, $body, $this->now(), $this->now()]);
        }
        $this->success('テンプレートを保存しました。', '/' . ($type === 'video' ? 'video' : $type) . '-templates');
    }

    private function deleteTemplate(): never
    {
        $type = (string)($_POST['source_type'] ?? '');
        $this->pdo->prepare('DELETE FROM xpp_templates WHERE id=? AND source_type=?')->execute([(int)($_POST['id'] ?? 0), $type]);
        $this->success('テンプレートを削除しました。', '/' . ($type === 'video' ? 'video' : $type) . '-templates');
    }

    private function analyzeVideo(): never
    {
        $url = trim((string)($_POST['video_url'] ?? ''));
        try {
            $this->assertPublicUrl($url);
            $mp4 = preg_match('/\.mp4(?:$|\?)/i', $url) ? $url : $this->extractMp4($url);
            $dir = dirname(__DIR__, 2) . '/storage/media/videos';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException('動画保存フォルダを作成できません。');
            }
            $path = $dir . '/' . bin2hex(random_bytes(12)) . '.mp4';
            $size = $this->downloadFile($mp4, $path, 536_870_912);
            $now = $this->now();
            $this->pdo->prepare('INSERT INTO xpp_video_jobs(input_type,input_url,source_path,status,progress,source_size,created_at,updated_at) VALUES(?,?,?,\'downloaded\',100,?,?,?)')
                ->execute([$mp4 === $url ? 'mp4' : 'page', $url, $path, $size, $now, $now]);
            $this->success('動画をダウンロードしました。編集条件を指定してください。', '/videos');
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), '/videos');
        }
    }

    private function editVideo(): never
    {
        $id = (int)($_POST['job_id'] ?? 0);
        $stmt = $this->pdo->prepare('SELECT * FROM xpp_video_jobs WHERE id=?');
        $stmt->execute([$id]);
        $job = $stmt->fetch();
        if (!$job || !is_file((string)$job['source_path'])) {
            $this->fail('元動画が見つかりません。', '/videos');
        }
        $ffmpeg = trim((string)@shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg === '') {
            $this->fail('FFmpegがインストールされていません。サーバー管理画面でFFmpegを有効にしてください。', '/videos');
        }
        $start = max(0, (float)($_POST['start_seconds'] ?? 0));
        $end = max(0, (float)($_POST['end_seconds'] ?? 0));
        if ($end > 0 && $end <= $start) {
            $this->fail('終了時間は開始時間より後にしてください。', '/videos');
        }
        $ratio = in_array($_POST['aspect_ratio'] ?? '', ['original', '16:9', '1:1', '9:16'], true) ? (string)$_POST['aspect_ratio'] : 'original';
        $quality = in_array($_POST['quality'] ?? '', ['high', 'standard', 'small'], true) ? (string)$_POST['quality'] : 'standard';
        $muted = isset($_POST['muted']) ? 1 : 0;
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            $this->fail('投稿素材のタイトルを入力してください。', '/videos');
        }
        $output = dirname((string)$job['source_path']) . '/' . bin2hex(random_bytes(12)) . '-edited.mp4';
        $filters = [
            '16:9' => 'scale=1280:720:force_original_aspect_ratio=increase,crop=1280:720',
            '1:1' => 'scale=1080:1080:force_original_aspect_ratio=increase,crop=1080:1080',
            '9:16' => 'scale=720:1280:force_original_aspect_ratio=increase,crop=720:1280',
        ];
        $crf = ['high' => '19', 'standard' => '23', 'small' => '28'][$quality];
        $command = escapeshellarg($ffmpeg) . ' -y -ss ' . escapeshellarg((string)$start) . ' -i ' . escapeshellarg((string)$job['source_path']);
        if ($end > 0) {
            $command .= ' -t ' . escapeshellarg((string)($end - $start));
        }
        if (isset($filters[$ratio])) {
            $command .= ' -vf ' . escapeshellarg($filters[$ratio]);
        }
        $command .= ' -c:v libx264 -preset medium -crf ' . $crf . ' -pix_fmt yuv420p -movflags +faststart';
        $command .= $muted ? ' -an' : ' -c:a aac -b:a 128k';
        $command .= ' ' . escapeshellarg($output) . ' 2>&1';
        exec($command, $lines, $code);
        if ($code !== 0 || !is_file($output)) {
            $message = implode("\n", array_slice($lines, -10));
            $this->pdo->prepare('UPDATE xpp_video_jobs SET status=\'failed\',error_message=?,updated_at=? WHERE id=?')->execute([$message, $this->now(), $id]);
            $this->fail('動画編集に失敗しました。' . $message, '/videos');
        }
        $this->pdo->prepare('UPDATE xpp_video_jobs SET output_path=?,status=\'completed\',start_seconds=?,end_seconds=?,aspect_ratio=?,quality=?,muted=?,output_size=?,updated_at=? WHERE id=?')
            ->execute([$output, $start, $end ?: null, $ratio, $quality, $muted, filesize($output), $this->now(), $id]);
        $item = ['external_id' => 'video-' . $id, 'title' => $title, 'description' => '', 'source_url' => $job['input_url'], 'affiliate_url' => '', 'image_url' => '', 'media_url' => $this->mediaUrl($output), 'actress' => '', 'genre' => '', 'published_at' => $this->now(), 'raw' => [], 'images' => []];
        $this->upsertItem('video', 'video', $item);
        $this->success('動画編集が完了しました。', '/videos');
    }

    private function generatePost(): never
    {
        $itemId = (int)($_POST['source_item_id'] ?? 0);
        $templateId = (int)($_POST['template_id'] ?? 0);
        $itemStmt = $this->pdo->prepare('SELECT * FROM xpp_source_items WHERE id=?');
        $itemStmt->execute([$itemId]);
        $item = $itemStmt->fetch();
        $tplStmt = $this->pdo->prepare('SELECT * FROM xpp_templates WHERE id=? AND source_type=?');
        $tplStmt->execute([$templateId, $item['source_type'] ?? '']);
        $template = $tplStmt->fetch();
        if (!$item || !$template) {
            $this->fail('素材またはテンプレートが見つかりません。', '/posts');
        }
        $hashtags = $this->hashtags((string)$item['title'], (string)$item['actress']);
        $replace = ['{title}' => $item['title'], '{url}' => $item['affiliate_url'] ?: $item['source_url'], '{article_url}' => $item['source_url'], '{affiliate_url}' => $item['affiliate_url'], '{image_url}' => $item['image_url'], '{sample_movie_url}' => $item['media_url'], '{hashtags}' => $hashtags, '{service}' => $item['service'], '{actress}' => $item['actress'], '{genre}' => $item['genre']];
        $body = strtr((string)$template['body'], $replace);
        $now = $this->now();
        $this->pdo->prepare('INSERT INTO xpp_posts(source_type,source_item_id,template_id,title,body,status,created_at,updated_at) VALUES(?,?,?,?,?,\'draft\',?,?)')
            ->execute([$item['source_type'], $itemId, $templateId, $item['title'], $body, $now, $now]);
        $postId = (int)$this->pdo->lastInsertId();
        foreach ([['image', $item['image_url'], null], ['video', $item['media_url'], null]] as [$type, $url, $local]) {
            if ($url) {
                $this->pdo->prepare('INSERT INTO xpp_post_media(post_id,media_type,media_url,local_path,sort_order,created_at) VALUES(?,?,?,?,0,?)')->execute([$postId, $type, $url, $local, $now]);
            }
        }
        $this->success('投稿を作成しました。', '/posts');
    }

    private function savePost(): never
    {
        $this->pdo->prepare('UPDATE xpp_posts SET title=?,body=?,updated_at=? WHERE id=?')->execute([trim((string)$_POST['title']), trim((string)$_POST['body']), $this->now(), (int)$_POST['id']]);
        $this->success('投稿を更新しました。', '/posts?status=' . ((string)($_POST['status'] ?? 'draft')));
    }

    private function copyPost(): never
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->pdo->prepare('UPDATE xpp_posts SET status=\'posted\',copied_at=?,updated_at=? WHERE id=?')->execute([$this->now(), $this->now(), $id]);
        $this->success('投稿文をコピーし、投稿済みに変更しました。', '/posts?status=posted');
    }

    private function deletePosts(): never
    {
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [$_POST['id'] ?? 0]))));
        if ($ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $this->pdo->prepare("DELETE FROM xpp_post_media WHERE post_id IN ({$marks})")->execute($ids);
            $this->pdo->prepare("DELETE FROM xpp_posts WHERE id IN ({$marks})")->execute($ids);
        }
        $this->success('投稿を削除しました。', '/posts');
    }

    private function items(string $type): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM xpp_source_items WHERE source_type=? ORDER BY id DESC LIMIT 100');
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }

    private function itemList(string $type, array $items): string
    {
        $stmt = $this->pdo->prepare('SELECT * FROM xpp_templates WHERE source_type=? ORDER BY sort_order,id LIMIT 3');
        $stmt->execute([$type]);
        $templates = $stmt->fetchAll();
        $cards = '';
        foreach ($items as $item) {
            $options = '';
            foreach ($templates as $template) {
                $options .= '<option value="' . (int)$template['id'] . '">' . $this->e($template['name']) . '</option>';
            }
            $cards .= '<article class="item-card"><label class="select-box"><input form="source-bulk-delete-' . $type . '" type="checkbox" name="ids[]" value="' . (int)$item['id'] . '"></label>'
                . ($item['image_url'] ? '<img src="' . $this->e($item['image_url']) . '" loading="lazy" alt="">' : '')
                . '<div><small>' . $this->e(strtoupper((string)$item['service'])) . '</small><h3>' . $this->e($item['title']) . '</h3><p>' . $this->e(mb_substr((string)$item['description'], 0, 180)) . '</p>'
                . '<form method="post" action="' . $this->url('/posts/generate') . '">' . $this->csrfField() . '<input type="hidden" name="source_item_id" value="' . (int)$item['id'] . '"><label>テンプレート<select name="template_id" required>' . $options . '</select></label><button class="primary"' . (!$templates ? ' disabled' : '') . '>投稿作成</button></form>'
                . '<form class="item-delete" method="post" action="' . $this->url('/source-items/delete') . '" onsubmit="return confirm(\'この素材を削除しますか？\')">' . $this->csrfField() . '<input type="hidden" name="source_type" value="' . $type . '"><input type="hidden" name="ids[]" value="' . (int)$item['id'] . '"><button class="danger">個別削除</button></form></div></article>';
        }
        $actions = $items ? '<form id="source-bulk-delete-' . $type . '" method="post" action="' . $this->url('/source-items/delete') . '" onsubmit="return confirm(\'選択した素材を削除しますか？\')">' . $this->csrfField() . '<input type="hidden" name="source_type" value="' . $type . '"><div class="button-row left"><button type="button" class="secondary" data-select-all=".item-card input[name=&quot;ids[]&quot;]">全選択</button><button class="danger">選択した素材を一括削除</button></div></form>' : '';
        return '<section class="panel item-section"><h2>取得済み素材</h2>' . $actions . '<div class="item-grid">' . ($cards ?: '<div class="empty">まだ素材はありません。</div>') . '</div></section>';
    }

    private function deleteSourceItems(): never
    {
        $type = in_array($_POST['source_type'] ?? '', ['api', 'rss', 'video'], true) ? (string)$_POST['source_type'] : 'api';
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
        if ($ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $this->pdo->beginTransaction();
            try {
                $postStmt = $this->pdo->prepare("SELECT id FROM xpp_posts WHERE source_item_id IN ({$marks})");
                $postStmt->execute($ids);
                $postIds = array_map('intval', array_column($postStmt->fetchAll(), 'id'));
                if ($postIds) {
                    $postMarks = implode(',', array_fill(0, count($postIds), '?'));
                    $this->pdo->prepare("DELETE FROM xpp_post_media WHERE post_id IN ({$postMarks})")->execute($postIds);
                    $this->pdo->prepare("DELETE FROM xpp_posts WHERE id IN ({$postMarks})")->execute($postIds);
                }
                $this->pdo->prepare("DELETE FROM xpp_source_media WHERE source_item_id IN ({$marks})")->execute($ids);
                $this->pdo->prepare("DELETE FROM xpp_source_items WHERE id IN ({$marks}) AND source_type=?")->execute([...$ids, $type]);
                $this->pdo->commit();
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                $this->fail('素材を削除できませんでした。', $type === 'video' ? '/videos' : '/' . $type . '-posts');
            }
        }
        $this->success('選択した素材を削除しました。', $type === 'video' ? '/videos' : '/' . $type . '-posts');
    }

    private function upsertItem(string $type, string $service, array $item, ?int $feedId = null): int
    {
        if (($item['title'] ?? '') === '' || ($item['external_id'] ?? '') === '') {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM xpp_source_items WHERE source_type=? AND service=? AND external_id=?');
        $stmt->execute([$type, $service, $item['external_id']]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        $values = [$feedId, $item['title'], $item['description'] ?? '', $item['source_url'] ?? '', $item['affiliate_url'] ?? '', $item['image_url'] ?? '', $item['media_url'] ?? '', $item['actress'] ?? '', $item['genre'] ?? '', $item['published_at'] ?? null, json_encode($item['raw'] ?? [], JSON_UNESCAPED_UNICODE), $this->now()];
        if ($id) {
            $this->pdo->prepare('UPDATE xpp_source_items SET feed_id=?,title=?,description=?,source_url=?,affiliate_url=?,image_url=?,media_url=?,actress=?,genre=?,published_at=?,raw_json=?,updated_at=? WHERE id=?')->execute([...$values, $id]);
        } else {
            $this->pdo->prepare('INSERT INTO xpp_source_items(source_type,service,external_id,feed_id,title,description,source_url,affiliate_url,image_url,media_url,actress,genre,published_at,raw_json,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$type, $service, $item['external_id'], ...$values, $this->now()]);
            $id = (int)$this->pdo->lastInsertId();
        }
        $this->pdo->prepare('DELETE FROM xpp_source_media WHERE source_item_id=?')->execute([$id]);
        foreach (array_values(array_unique((array)($item['images'] ?? []))) as $order => $image) {
            if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
                try {
                    $this->pdo->prepare('INSERT INTO xpp_source_media(source_item_id,media_type,media_url,sort_order,created_at) VALUES(?,\'image\',?,?,?)')->execute([$id, $image, $order, $this->now()]);
                } catch (\PDOException) {
                }
            }
        }
        return $id;
    }

    private function apiStatuses(): array
    {
        $rows = $this->pdo->query('SELECT service,enabled,test_status FROM xpp_api_settings')->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['service']] = !(int)$row['enabled'] ? '無効' : (($row['test_status'] ?? '') === 'success' ? '設定済み・接続正常' : '設定済み・未確認');
        }
        return $out;
    }

    private function apiCredentials(string $service): array
    {
        $stmt = $this->pdo->prepare('SELECT credentials FROM xpp_api_settings WHERE service=?');
        $stmt->execute([$service]);
        $payload = (string)($stmt->fetchColumn() ?: '');
        return $payload === '' ? [] : $this->decryptCredentials($payload);
    }

    private function encryptCredentials(array $data): string
    {
        if (!extension_loaded('sodium')) {
            throw new \RuntimeException('API暗号化にはPHP Sodium拡張が必要です。');
        }
        $appKey = (string)(getenv('APP_KEY') ?: '');
        if (strlen($appKey) < 32) {
            throw new \RuntimeException('APP_KEYに32文字以上のランダム文字列を設定してください。');
        }
        $key = sodium_crypto_generichash($appKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'enc:v1:' . base64_encode($nonce . sodium_crypto_secretbox(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $nonce, $key));
    }

    private function decryptCredentials(string $payload): array
    {
        if (!str_starts_with($payload, 'enc:v1:')) {
            return json_decode($payload, true) ?: [];
        }
        $decoded = base64_decode(substr($payload, 7), true);
        $appKey = (string)(getenv('APP_KEY') ?: '');
        if ($decoded === false || strlen($appKey) < 32) {
            throw new \RuntimeException('API設定を復号できません。APP_KEYを確認してください。');
        }
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, sodium_crypto_generichash($appKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        if ($plain === false) {
            throw new \RuntimeException('API設定を復号できません。');
        }
        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    }

    private function httpJson(string $url): array
    {
        $data = json_decode($this->httpGet($url, 10_000_000), true);
        if (!is_array($data)) {
            throw new \RuntimeException('APIレスポンスがJSONではありません。');
        }
        return $data;
    }

    private function httpGet(string $url, int $maxBytes): string
    {
        $this->assertPublicUrl($url);
        $context = stream_context_create(['http' => ['timeout' => 20, 'follow_location' => 0, 'ignore_errors' => true, 'header' => "User-Agent: XPostPlus/1.0\r\nAccept: application/json, application/xml, text/xml, text/html\r\n"], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $handle = @fopen($url, 'rb', false, $context);
        if (!$handle) {
            throw new \RuntimeException('外部URLへ接続できません。');
        }
        $body = stream_get_contents($handle, $maxBytes + 1);
        fclose($handle);
        if ($body === false || strlen($body) > $maxBytes) {
            throw new \RuntimeException('取得データが大きすぎます。');
        }
        return $body;
    }

    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            throw new \RuntimeException('有効なHTTPまたはHTTPS URLを入力してください。');
        }
        $ips = gethostbynamel((string)$parts['host']) ?: [];
        if (!$ips) {
            throw new \RuntimeException('URLのホストを確認できません。');
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \RuntimeException('ローカルネットワークのURLは指定できません。');
            }
        }
    }

    private function extractMp4(string $pageUrl): string
    {
        $html = $this->httpGet($pageUrl, 5_000_000);
        if (!preg_match_all('~https?://[^\s"\'<>]+\.mp4(?:\?[^\s"\'<>]*)?~i', $html, $matches) || empty($matches[0][0])) {
            throw new \RuntimeException('動画ページからMP4 URLを確認できません。MP4 URLを直接入力してください。');
        }
        $url = html_entity_decode($matches[0][0], ENT_QUOTES | ENT_HTML5);
        $this->assertPublicUrl($url);
        return $url;
    }

    private function downloadFile(string $url, string $path, int $maxBytes): int
    {
        $this->assertPublicUrl($url);
        $context = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 0, 'header' => "User-Agent: XPostPlus/1.0\r\n"], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $input = @fopen($url, 'rb', false, $context);
        $output = @fopen($path, 'wb');
        if (!$input || !$output) {
            if (is_resource($input)) fclose($input);
            if (is_resource($output)) fclose($output);
            throw new \RuntimeException('動画をダウンロードできません。');
        }
        $bytes = 0;
        try {
            while (!feof($input)) {
                $chunk = fread($input, 1_048_576);
                if ($chunk === false) throw new \RuntimeException('動画の読み込みに失敗しました。');
                $bytes += strlen($chunk);
                if ($bytes > $maxBytes) throw new \RuntimeException('動画が512MBを超えています。');
                if (fwrite($output, $chunk) === false) throw new \RuntimeException('動画を保存できません。');
            }
        } catch (\Throwable $e) {
            fclose($input); fclose($output); @unlink($path); throw $e;
        }
        fclose($input); fclose($output);
        return $bytes;
    }

    private function rssImage(\SimpleXMLElement $entry): string
    {
        foreach ($entry->children('media', true) as $child) {
            $url = (string)($child['url'] ?? '');
            if ($url !== '') return $url;
        }
        $html = (string)($entry->description ?? $entry->content ?? '');
        return preg_match('/<img[^>]+src=["\']([^"\']+)/i', $html, $m) ? html_entity_decode($m[1]) : '';
    }

    private function names(mixed $rows): string
    {
        if (!is_array($rows)) return '';
        if (isset($rows['name'])) $rows = [$rows];
        return implode('、', array_values(array_filter(array_map(fn($row) => is_array($row) ? (string)($row['name'] ?? '') : '', $rows))));
    }

    private function normalizeRows(mixed $rows, string $identityKey): array
    {
        if (!is_array($rows)) {
            return [];
        }
        if (array_key_exists($identityKey, $rows)) {
            return [$rows];
        }
        return array_values(array_filter($rows, 'is_array'));
    }

    private function dateValue(mixed $value): ?string
    {
        if (!$value) return null;
        $time = strtotime((string)$value);
        return $time ? date('Y-m-d H:i:s', $time) : null;
    }

    private function hashtags(string $title, string $actress): string
    {
        preg_match_all('/[\p{L}\p{N}ー]{2,20}/u', $title . ' ' . $actress, $matches);
        $words = array_slice(array_values(array_unique($matches[0] ?? [])), 0, 6);
        return implode(' ', array_map(fn($word) => '#' . $word, $words));
    }

    private function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . $this->e($this->csrfToken()) . '">';
    }

    private function flashHtml(): string
    {
        $success = $this->pullFlash('success');
        $error = $this->pullFlash('error');
        return ($success ? '<div class="notice success">' . $this->e($success) . '</div>' : '') . ($error ? '<div class="notice error">' . $this->e($error) . '</div>' : '');
    }

    private function success(string $message, string $path): never
    {
        $this->flash('success', $message);
        $this->redirect($path);
    }

    private function fail(string $message, string $path): never
    {
        $this->flash('error', $message);
        $this->redirect($path);
    }

    private function postTabs(string $status): string
    {
        return '<nav class="tabs"><a class="' . ($status === 'draft' ? 'active' : '') . '" href="' . $this->url('/posts?status=draft') . '">未投稿</a><a class="' . ($status === 'posted' ? 'active' : '') . '" href="' . $this->url('/posts?status=posted') . '">投稿済み</a></nav>';
    }

    private function mediaUrl(string $path): string
    {
        $root = dirname(__DIR__, 2) . '/storage/media/';
        return str_starts_with($path, $root) ? $this->url('/media/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)))) : $path;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function log(string $action, ?string $targetType, ?int $targetId, string $message): void
    {
        $this->pdo->prepare('INSERT INTO xpp_activity_logs(user_id,action,target_type,target_id,message,created_at) VALUES(?,?,?,?,?,?)')->execute([(int)($_SESSION['user_id'] ?? 0), $action, $targetType, $targetId, $message, $this->now()]);
    }

    private function notFound(): never
    {
        http_response_code(404);
        exit('404 Not Found');
    }

    private function serveMedia(string $relative): never
    {
        $base = realpath(dirname(__DIR__, 2) . '/storage/media');
        $file = realpath(dirname(__DIR__, 2) . '/storage/media/' . ltrim($relative, '/'));
        if ($base === false || $file === false || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
            $this->notFound();
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        header('Content-Disposition: inline; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    }

    private function loginPage(): void
    {
        $error = $this->pullFlash('error');
        $first = (int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
        $errorHtml = $error !== '' ? '<div class="notice error">' . $this->e($error) . '</div>' : '';
        $notice = $first ? '<div class="notice">初期ID：<strong>admin</strong><br>初期パスワード：<strong>password</strong></div>' : '';
        $fields = '<label>ユーザー名またはメールアドレス<input name="login" type="text" autocomplete="username" required autofocus></label><label>パスワード<input name="password" type="password" autocomplete="current-password" required></label>';
        $heading = '管理画面へログインしてください。';
        $button = 'ログイン';
        echo '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ログイン | XPostPlus</title><link rel="stylesheet" href="' . $this->asset('/assets/css/rebuild.css') . '"></head><body class="login-body"><main class="login-shell"><section class="login-card"><h1>XPostPlus</h1><p>' . $heading . '</p>' . $errorHtml
            . $notice . '<form method="post" action="' . $this->url('/login') . '"><input type="hidden" name="csrf_token" value="' . $this->e($this->csrfToken()) . '">' . $fields
            . '<button class="primary login-button" type="submit">' . $button . '</button></form></section></main></body></html>';
    }

    private function handleLogin(): void
    {
        $this->verifyCsrf();
        $login = trim((string)($_POST['login'] ?? ''));
        $login = function_exists('mb_strtolower') ? mb_strtolower($login) : strtolower($login);
        $password = (string)($_POST['password'] ?? '');
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 64);
        $first = (int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;

        if ($login === '' || $password === '') {
            $this->flash('error', 'ユーザー名とパスワードを入力してください。');
            $this->redirect('/login');
        }

        $this->pdo->prepare('DELETE FROM login_attempts WHERE attempted_at <= ?')
            ->execute([date('Y-m-d H:i:s', time() - 86400)]);

        if ($first) {
            if ($login !== 'admin' || $password !== 'password') {
                $this->flash('error', '初回はID「admin」、パスワード「password」でログインしてください。');
                $this->redirect('/login');
            }
            $now = date('Y-m-d H:i:s');
            $this->pdo->prepare('INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
                ->execute(['admin', 'admin@localhost', password_hash('password', PASSWORD_DEFAULT), $now, $now]);
        }

        $attemptKey = substr($login, 0, 190);
        $attempt = $this->pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip_address = ? AND attempted_at > ?');
        $attempt->execute([$attemptKey, $ip, date('Y-m-d H:i:s', time() - 900)]);
        if ((int)$attempt->fetchColumn() >= 5) {
            $this->flash('error', 'ログイン試行回数が多すぎます。15分後に再試行してください。');
            $this->redirect('/login');
        }

        $column = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';
        $statement = $this->pdo->prepare("SELECT * FROM users WHERE {$column} = ?");
        $statement->execute([$login]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            $this->pdo->prepare('INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, ?)')
                ->execute([$attemptKey, $ip, date('Y-m-d H:i:s')]);
            usleep(random_int(200000, 500000));
            $this->flash('error', 'ユーザー名またはパスワードが違います。');
            $this->redirect('/login');
        }

        $this->pdo->prepare('DELETE FROM login_attempts WHERE email = ? AND ip_address = ?')->execute([$attemptKey, $ip]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = (string)$user['name'];
        $_SESSION['last_activity'] = time();

        $this->redirect('/');
    }

    private function passwordPage(): string
    {
        $error = $this->pullFlash('error');
        $errorHtml = $error !== '' ? '<div class="notice error">' . $this->e($error) . '</div>' : '';

        return '<section class="page-head"><h1>パスワード変更</h1><p>安全のため、12文字以上の新しいパスワードを設定してください。</p></section>'
            . '<section class="panel password-panel">' . $errorHtml
            . '<form method="post" action="' . $this->url('/password') . '"><input type="hidden" name="csrf_token" value="' . $this->e($this->csrfToken()) . '">'
            . '<label>新しいパスワード<input name="password" type="password" minlength="12" autocomplete="new-password" required></label>'
            . '<label>新しいパスワード（確認）<input name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></label>'
            . '<button class="primary" type="submit">パスワードを変更</button></form></section>';
    }

    private function handlePasswordChange(): void
    {
        $this->verifyCsrf();
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirmation'] ?? '');
        if (strlen($password) < 12) {
            $this->flash('error', 'パスワードは12文字以上で入力してください。');
            $this->redirect('/password');
        }
        if (!hash_equals($password, $confirmation)) {
            $this->flash('error', '確認用パスワードが一致しません。');
            $this->redirect('/password');
        }

        $this->pdo->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), (int)$_SESSION['user_id']]);
        session_regenerate_id(true);
        $this->redirect('/');
    }

    private function handleLogout(): void
    {
        $this->verifyCsrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
        $this->redirect('/login');
    }

    private function stat(string $label, int $count, string $path): string
    {
        return '<a class="stat" href="' . $this->url($path) . '"><span>' . $this->e($label) . '</span><strong>' . $count . '</strong></a>';
    }

    private function guideCard(string $title, string $description, string $path): string
    {
        return '<a class="guide-card" href="' . $this->url($path) . '"><h2>' . $this->e($title) . '</h2><p>' . $this->e($description) . '</p><span>開く →</span></a>';
    }

    private function dashboardGroup(string $title, string $description, string $postPath, string $templatePath): string
    {
        return '<article class="dashboard-source-card"><div class="dashboard-source-title"><span>' . $this->e($title) . '</span><p>' . $this->e($description) . '</p></div>'
            . '<div class="dashboard-source-actions"><a class="dashboard-primary-action" href="' . $this->url($postPath) . '">' . $this->e($title) . '投稿を開く</a>'
            . '<a class="dashboard-secondary-action" href="' . $this->url($templatePath) . '">' . $this->e($title) . 'テンプレート</a></div></article>';
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
            . '<form class="logout-form" method="post" action="' . $this->url('/logout') . '"><input type="hidden" name="csrf_token" value="' . $this->e($this->csrfToken()) . '"><button class="logout-link" type="submit">ログアウト</button></form>';

        echo '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $this->e($title) . ' | XPostPlus</title><link rel="stylesheet" href="' . $this->asset('/assets/css/rebuild.css') . '"></head><body><div class="app"><aside id="sidebar"><div class="sidebar-head"><h1>XPostPlus</h1><button class="menu-close" type="button" data-menu-close aria-label="メニューを閉じる">×</button></div><nav>' . $nav . '</nav></aside><main><header><button class="menu-toggle" type="button" data-menu-open aria-controls="sidebar" aria-expanded="false">☰</button><strong>' . $this->e($title) . '</strong></header><div class="content">' . $content . '</div></main></div><div class="menu-backdrop" data-menu-close></div><script src="' . $this->asset('/assets/js/rebuild.js') . '"></script></body></html>';
    }

    private function url(string $path): string
    {
        return ($this->base ?: '') . $path;
    }

    private function asset(string $path): string
    {
        $file = dirname(__DIR__, 2) . '/public' . $path;
        $version = is_file($file) ? (string)filemtime($file) : '1';
        return $this->url($path) . '?v=' . rawurlencode($version);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
            $this->redirect('/login');
        }
        $_SESSION['last_activity'] = time();
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf_token'];
    }

    private function verifyCsrf(): void
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if ($token === '' || !hash_equals($this->csrfToken(), $token)) {
            http_response_code(419);
            exit('画面の有効期限が切れました。前の画面へ戻り、もう一度お試しください。');
        }
    }

    private function flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    private function pullFlash(string $key): string
    {
        $message = (string)($_SESSION['flash'][$key] ?? '');
        unset($_SESSION['flash'][$key]);
        return $message;
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $this->url($path));
        exit;
    }

    private function createIndex(string $name, string $table, string $columns): void
    {
        try {
            $this->pdo->exec("CREATE INDEX {$name} ON {$table} ({$columns})");
        } catch (\PDOException $exception) {
            $message = strtolower($exception->getMessage());
            if (!str_contains($message, 'already exists') && !str_contains($message, 'duplicate key name')) {
                throw $exception;
            }
        }
    }

    private function ensureColumns(string $table, array $columns): void
    {
        $stmt = $this->pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        $existing = array_flip(array_column($stmt->fetchAll(), 'COLUMN_NAME'));
        foreach ($columns as $column => $definition) {
            if (!isset($existing[$column]) && preg_match('/^[a-z0-9_]+$/', $column)) {
                $this->pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        }
    }
}
