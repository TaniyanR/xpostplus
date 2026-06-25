<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Database, View};
use function App\Core\{csrf_token, flash, redirect, verify_csrf};

final class AuthController extends Controller
{
    public function showLogin(): string { csrf_token(); return View::render('auth/login', ['first'=>$this->userCount()===0], null); }
    public function login(): string
    {
        verify_csrf(); $pdo=Database::pdo(); $email=trim($_POST['email']??''); $pass=(string)($_POST['password']??''); $ip=$_SERVER['REMOTE_ADDR']??'cli';
        if ($this->userCount()===0) { $stmt=$pdo->prepare('INSERT INTO users (name,email,password_hash,created_at,updated_at) VALUES (?,?,?,?,?)'); $now=date('Y-m-d H:i:s'); $stmt->execute(['管理者',$email,password_hash($pass,PASSWORD_DEFAULT),$now,$now]); }
        $attempt=$pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE email=? AND ip_address=? AND attempted_at > ?"); $attempt->execute([$email,$ip,date('Y-m-d H:i:s', time()-900)]);
        if ((int)$attempt->fetchColumn()>=5) { flash('error','ログイン試行回数が多すぎます。15分後に再試行してください。'); redirect('/login'); }
        $s=$pdo->prepare('SELECT * FROM users WHERE email=?'); $s->execute([$email]); $u=$s->fetch();
        if (!$u || !password_verify($pass,$u['password_hash'])) { $pdo->prepare('INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?,?,?)')->execute([$email,$ip,date('Y-m-d H:i:s')]); flash('error','メールアドレスまたはパスワードが違います。'); redirect('/login'); }
        session_regenerate_id(true); $_SESSION['user_id']=$u['id']; $_SESSION['user_name']=$u['name']; redirect('/');
    }
    public function logout(): string { verify_csrf(); $_SESSION=[]; session_destroy(); redirect('/login'); }
    public function showPassword(): string { $this->requireAuth(); return View::render('auth/password'); }
    public function changePassword(): string { $this->requireAuth(); verify_csrf(); $p=(string)($_POST['password']??''); if(strlen($p)<8){flash('error','8文字以上で入力してください。');redirect('/password');} Database::pdo()->prepare('UPDATE users SET password_hash=?, updated_at=? WHERE id=?')->execute([password_hash($p,PASSWORD_DEFAULT),date('Y-m-d H:i:s'),$_SESSION['user_id']]); flash('success','パスワードを変更しました。'); redirect('/password'); }
}
