<?php use function App\Core\{e,url,csrf_field,flash}; ?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login - XPostPlus</title>
  <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body class="login">
  <form class="card login-card" method="post" action="<?= e(url('/login')) ?>">
    <h1>XPostPlus</h1>
    <p>ログインしてください。</p>
    <?php if ($message = flash('error')): ?>
      <div class="notice error"><?= e($message) ?></div>
    <?php endif; ?>
    <?= csrf_field() ?>
    <label>ID（メールアドレス）
      <input type="text" name="login" autocomplete="username" required>
    </label>
    <label>パスワード
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="primary">ログイン</button>
  </form>
</body>
</html>
