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
        $this->sendSecurityHeaders();
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($this->base !== '' && $this->base !== '/' && str_starts_with($path, $this->base)) {
            $path = substr($path, strlen($this->base)) ?: '/';
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
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

        if (!empty($_SESSION['force_password_change']) && $path !== '/password' && $path !== '/logout') {
            $this->redirect('/password');
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
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (id {$id}, name VARCHAR(100) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id {$id}, email VARCHAR(190) NOT NULL, ip_address VARCHAR(64) NOT NULL, attempted_at DATETIME NOT NULL)");
        $this->createIndex('idx_login_attempts_lookup', 'login_attempts', 'email, ip_address, attempted_at');

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

    private function loginPage(): void
    {
        $error = $this->pullFlash('error');
        $first = (int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
        $notice = $first
            ? '<div class="notice">初回ログイン：ユーザー名 <strong>admin</strong>／パスワード <strong>password</strong></div>'
            : '';
        $errorHtml = $error !== '' ? '<div class="notice error">' . $this->e($error) . '</div>' : '';

        echo '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ログイン | XPostPlus</title><link rel="stylesheet" href="' . $this->url('/assets/css/rebuild.css') . '"></head><body class="login-body"><main class="login-shell"><section class="login-card"><h1>XPostPlus</h1><p>管理画面へログインしてください。</p>' . $errorHtml . $notice
            . '<form method="post" action="' . $this->url('/login') . '"><input type="hidden" name="csrf_token" value="' . $this->e($this->csrfToken()) . '">'
            . '<label>ユーザー名またはメールアドレス<input name="login" type="text" autocomplete="username" required autofocus></label>'
            . '<label>パスワード<input name="password" type="password" autocomplete="current-password" required></label>'
            . '<button class="primary login-button" type="submit">ログイン</button></form></section></main></body></html>';
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
                $this->flash('error', '初回はユーザー名「admin」、パスワード「password」でログインしてください。');
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

        if ($first || password_verify('password', (string)$user['password_hash'])) {
            $_SESSION['force_password_change'] = true;
            $this->flash('error', '初期パスワードのままでは危険です。新しいパスワードへ変更してください。');
            $this->redirect('/password');
        }

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
        unset($_SESSION['force_password_change']);
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
}
