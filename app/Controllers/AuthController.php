<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller, Database, View};
use function App\Core\{csrf_token, flash, redirect, verify_csrf};

final class AuthController extends Controller
{
    public function showLogin(): string
    {
        csrf_token();
        return View::render('auth/login', ['first' => $this->userCount() === 0], null);
    }

    public function login(): string
    {
        verify_csrf();

        $pdo = Database::pdo();
        $login = mb_strtolower(trim((string)($_POST['login'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 64);
        $first = $this->userCount() === 0;

        if ($login === '' || $password === '') {
            flash('error', 'ユーザー名とパスワードを入力してください。');
            redirect('/login');
        }

        $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at <= ?')
            ->execute([date('Y-m-d H:i:s', time() - 86400)]);

        if ($first) {
            if ($login !== 'admin' || $password !== 'password') {
                flash('error', '初回はユーザー名「admin」、パスワード「password」でログインしてください。');
                redirect('/login');
            }

            $now = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
                ->execute(['admin', 'admin@localhost', password_hash('password', PASSWORD_DEFAULT), $now, $now]);
        }

        $attemptKey = substr($login, 0, 255);
        $attempt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip_address = ? AND attempted_at > ?');
        $attempt->execute([$attemptKey, $ip, date('Y-m-d H:i:s', time() - 900)]);
        if ((int)$attempt->fetchColumn() >= 5) {
            flash('error', 'ログイン試行回数が多すぎます。15分後に再試行してください。');
            redirect('/login');
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $statement = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        } else {
            $statement = $pdo->prepare('SELECT * FROM users WHERE name = ?');
        }
        $statement->execute([$login]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $pdo->prepare('INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, ?)')
                ->execute([$attemptKey, $ip, date('Y-m-d H:i:s')]);
            usleep(random_int(200000, 500000));
            flash('error', 'ユーザー名またはパスワードが違います。');
            redirect('/login');
        }

        $pdo->prepare('DELETE FROM login_attempts WHERE email = ? AND ip_address = ?')->execute([$attemptKey, $ip]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = (string)$user['name'];
        $_SESSION['last_activity'] = time();

        if ($first) {
            $_SESSION['force_password_change'] = true;
            flash('error', '初期パスワードのままでは危険です。新しいパスワードへ変更してください。');
            redirect('/settings');
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

    public function showSettings(): string
    {
        $this->requireAuth();
        $statement = Database::pdo()->prepare('SELECT name, email FROM users WHERE id = ?');
        $statement->execute([$_SESSION['user_id']]);
        return View::render('settings/account', ['user' => $statement->fetch()]);
    }

    public function showPassword(): string
    {
        return $this->showSettings();
    }

    public function changeEmail(): string
    {
        $this->requireAuth();
        verify_csrf();
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', '正しいメールアドレスを入力してください。');
            redirect('/settings');
        }

        try {
            Database::pdo()->prepare('UPDATE users SET email = ?, updated_at = ? WHERE id = ?')
                ->execute([$email, date('Y-m-d H:i:s'), $_SESSION['user_id']]);
        } catch (\PDOException $e) {
            flash('error', 'そのメールアドレスは使用できません。');
            redirect('/settings');
        }

        flash('success', 'ID（メールアドレス）を変更しました。');
        redirect('/settings');
    }

    public function changePassword(): string
    {
        $this->requireAuth();
        verify_csrf();
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirmation'] ?? $password);

        if (strlen($password) < 12) {
            flash('error', 'パスワードは12文字以上で入力してください。');
            redirect('/settings');
        }
        if ($password !== $confirmation) {
            flash('error', '確認用パスワードが一致しません。');
            redirect('/settings');
        }

        Database::pdo()->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), $_SESSION['user_id']]);
        unset($_SESSION['force_password_change']);
        session_regenerate_id(true);
        flash('success', 'パスワードを変更しました。');
        redirect('/settings');
    }
}
