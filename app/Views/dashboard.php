<?php use function App\Core\url; ?>
<section class="card" style="margin-bottom:20px">
  <h1>XPostPlusの使い方</h1>
  <p>登録したサイトのRSSから記事を取得し、Xで紹介する投稿文を管理するツールです。</p>
</section>
<section class="grid">
  <div class="card"><p><strong>STEP 1</strong></p><h2>RSSサイトを登録</h2><p>サイトURL、RSS URL、固定ハッシュタグを登録します。</p><p><a href="<?= url('/rss-posts') ?>">RSS投稿作成を開く</a></p></div>
  <div class="card"><p><strong>STEP 2</strong></p><h2>テンプレートを作る</h2><p>記事タイトル、URL、ハッシュタグなどの配置を決めます。</p><p><a href="<?= url('/templates') ?>">テンプレートを開く</a></p></div>
  <div class="card"><p><strong>STEP 3</strong></p><h2>投稿文を確認</h2><p>作成された投稿文の一覧と、投稿済みかどうかを確認します。</p><p><a href="<?= url('/posts') ?>">投稿文生成を開く</a></p></div>
</section>
<section class="card" style="margin-top:20px">
  <h2>アカウント設定</h2>
  <p>ログイン用のメールアドレスとパスワードは設定画面から変更できます。</p>
  <p><a href="<?= url('/settings') ?>">設定を開く</a></p>
</section>
