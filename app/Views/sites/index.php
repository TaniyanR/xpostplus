<?php use function App\Core\{e,url,csrf_field}; ?>
<section class="card">
  <h1>サイト設定</h1>
  <p>自分が運営しているサイトのRSSを登録します。FANZA・DUGA・SOKMILへ直接接続する設定ではありません。</p>
</section>
<section class="card">
  <h2>RSSサイトを追加</h2>
  <form method="post" action="<?= e(url('/sites')) ?>">
    <?= csrf_field() ?>
    <label>サイト名<input name="name" required placeholder="例：PinkClub FANZA"></label>
    <label>サイトURL<input type="url" name="site_url" required placeholder="https://example.com/"></label>
    <label>RSS URL<input type="url" name="rss_url" required placeholder="https://example.com/feed/"></label>
    <label>毎回付けるハッシュタグ<input name="fixed_hashtags" placeholder="#FANZA #新作動画"></label>
    <button class="primary">登録する</button>
  </form>
</section>
<section class="card">
  <h2>登録済みサイト</h2>
  <?php if (!$sites): ?><p>まだRSSサイトが登録されていません。</p><?php endif; ?>
  <?php foreach ($sites as $site): ?>
    <article class="template">
      <h3><?= e($site['name']) ?></h3>
      <p><strong>サイト：</strong><a href="<?= e($site['site_url']) ?>" target="_blank" rel="noopener"><?= e($site['site_url']) ?></a></p>
      <p><strong>RSS：</strong><?= e($site['rss_url']) ?></p>
      <p><strong>固定タグ：</strong><?= e($site['fixed_hashtags'] ?: 'なし') ?></p>
      <form method="post" action="<?= e(url('/sites/delete')) ?>" onsubmit="return confirm('このサイトを削除しますか？')">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$site['id'] ?>"><button>削除</button>
      </form>
    </article>
  <?php endforeach; ?>
</section>