<?php use function App\Core\{e,url,csrf_field}; ?>
<section class="card">
  <h1>設定</h1>
  <p>ログインに使用するID（メールアドレス）とパスワードを変更できます。</p>
</section>

<section class="card">
  <h2>ID（メールアドレス）の変更</h2>
  <form method="post" action="<?= e(url('/settings/email')) ?>">
    <?= csrf_field() ?>
    <label>メールアドレス
      <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required autocomplete="email">
    </label>
    <button class="primary">メールアドレスを変更</button>
  </form>
</section>

<section class="card">
  <h2>パスワードの変更</h2>
  <p>新しいパスワードは12文字以上で入力してください。</p>
  <form method="post" action="<?= e(url('/settings/password')) ?>">
    <?= csrf_field() ?>
    <label>新しいパスワード
      <input type="password" name="password" required minlength="12" autocomplete="new-password">
    </label>
    <label>新しいパスワード（確認）
      <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
    </label>
    <button class="primary">パスワードを変更</button>
  </form>
</section>
