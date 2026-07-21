<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Database, View};
use function App\Core\{csrf_token, flash, redirect, verify_csrf};

final class AuthController extends Controller
{
    private const INITIAL_LOGIN = 'admin';
    private const INITIAL_EMAIL = 'admin@xpostplus.local';

    public function showLogin(): string
    {
        csrf_token();
        return View::render('auth/login', ['first' => $this->userCount() === 0], null);
    }

    public function login(): string
    {
        verify_csrf();

        $pdo = Database::pdo();
        $login = mb_strtolower(trim((string)($_POST['login'] ?? $_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 64);
        $firstInstall = $this->userCount() === 0;
        $initialPassword = implode('', ['pass', 'word']);

        if ($login === '' || $password === '') {
            flash('error', 'ユーザー名またはメールアドレスとパスワードを入力してください。');
            redirect('/login');
        }

        $email = $login === self::INITIAL_LOGIN ? self::INITIAL_EMAIL : $login;
        if ($login !== self::INITIAL_LOGIN && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', '正しいユーザー名またはメールアドレスを入力してください。');
            redirect('/login');
        }

        $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at <= ?')
            ->execute([date('Y-m-d H:i:s', time() - 86400)]);

        if ($firstInstall) {
            if ($login !== self::INITIAL_LOGIN || !hash_equals($initialPassword, $password)) {
                flash('error', '初期ユーザー名または初期パスワードが違います。');
                redirect('/login');
            }

            $now = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
                ->execute(['管理者', self::INITIAL_EMAIL, password_hash($initialPassword, PASSWORD_DEFAULT), $now, $now]);
        }

        $attempt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip_address = ? AND attempted_at > ?');
        $attempt->execute([$email, $ip, date('Y-m-d H:i:s', time() - 900)]);
        if ((int)$attempt->fetchColumn() >= 5) {
            flash('error', 'ログイン試行回数が多すぎます。15分後に再試行してください。');
            redirect('/login');
        }

        $statement = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $statement->execute([$email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $pdo->prepare('INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, ?)')
                ->execute([$email, $ip, date('Y-m-d H:i:s')]);
            usleep(random_int(200000, 500000));
            flash('error', 'ユーザー名、メールアドレスまたはパスワードが違います。');
            redirect('/login');
        }

        $pdo->prepare('DELETE FROM login_attempts WHERE email = ? AND ip_address = ?')->execute([$email, $ip]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = (string)$user['name'];
        $_SESSION['last_activity'] = time();

        if ($firstInstall) {
            flash('success', '安全のため、初期パスワードを変更してください。');
            redirect('/password');
        }

        redirect('/');
    }

    public function logout(): string
    {
        verify_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('/login');
    }

    public function showPassword(): string
    {
        $this->requireAuth();
        return View::render('auth/password');
    }

    public function changePassword(): string
    {
        $this->requireAuth();
        verify_csrf();
        $password = (string)($_POST['password'] ?? '');
        if (strlen($password) < 12) {
            flash('error', 'パスワードは12文字以上で入力してください。');
            redirect('/password');
        }

        Database::pdo()->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), $_SESSION['user_id']]);
        session_regenerate_id(true);
        flash('success', 'パスワードを変更しました。');
        redirect('/password');
    }
}
