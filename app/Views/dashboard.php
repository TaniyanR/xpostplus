<?php use function App\Core\{e,url}; ?>
<section class="card" style="margin-bottom:20px">
  <h1>XPostPlus</h1>
  <p>Xへ投稿するための素材をAPI・RSS・動画から取得し、テンプレートで投稿文を作成・管理するツールです。</p>
</section>

<section class="grid">
  <article class="card">
    <h2>🛒 API投稿</h2>
    <p>FANZA・DUGA・SOKMILの商品情報、サンプル画像、サンプル動画を取得します。</p>
    <p><strong>保存済み：<?= (int)$products ?>件</strong></p>
    <p><a href="<?= e(url('/products')) ?>">API投稿を開く</a></p>
  </article>
  <article class="card">
    <h2>📰 RSS投稿</h2>
    <p>登録したサイトのRSSから記事を一括取得し、投稿素材として保存します。</p>
    <p><strong>取得済み：<?= (int)$rssItems ?>件</strong></p>
    <p><a href="<?= e(url('/rss-posts')) ?>">RSS投稿を開く</a></p>
  </article>
  <article class="card">
    <h2>🎬 動画投稿</h2>
    <p>動画ページURLまたはMP4 URLを登録し、編集する動画素材を管理します。</p>
    <p><strong>動画素材：<?= (int)$videos ?>件</strong></p>
    <p><a href="<?= e(url('/videos')) ?>">動画投稿を開く</a></p>
  </article>
</section>

<section class="grid" style="margin-top:20px">
  <article class="card">
    <h2>📋 投稿管理</h2>
    <p>作成した投稿文を確認し、コピー・再コピー・削除を行います。</p>
    <p><strong>未投稿：<?= (int)$unposted ?>件　投稿済み：<?= (int)$posted ?>件</strong></p>
    <p><a href="<?= e(url('/posts')) ?>">投稿管理を開く</a></p>
  </article>
  <article class="card">
    <h2>📝 テンプレート</h2>
    <p>API・RSS・動画それぞれの投稿文テンプレートを管理します。</p>
    <p><strong>登録済み：<?= (int)$templates ?>件</strong></p>
    <p><a href="<?= e(url('/templates')) ?>">テンプレートを開く</a></p>
  </article>
  <article class="card">
    <h2>⚙ 設定</h2>
    <p>ログイン情報など、XPostPlus全体の設定を管理します。</p>
    <p><a href="<?= e(url('/settings')) ?>">設定を開く</a></p>
  </article>
</section>
