<?php use function App\Core\{e,url,csrf_field}; ?>
<style>
.post-list{display:grid;gap:16px}.post-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}.post-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}.post-status{display:inline-block;padding:4px 9px;border-radius:12px;background:#f0f0f1;font-size:12px}.post-status.posted{background:#dff4e5;color:#0a5c2b}.post-status.copied{background:#e7f3ff;color:#135e96}.post-card textarea{width:100%;min-height:130px;box-sizing:border-box;margin:12px 0}.post-actions{display:flex;gap:8px;flex-wrap:wrap}.post-actions form{margin:0}.post-meta{color:#646970;font-size:12px}
</style>
<section class="card">
  <h1>投稿文生成</h1>
  <p>作成された投稿文を一覧で確認し、投稿した記事を「投稿済み」に変更できます。</p>
</section>

<section class="card">
  <h2>投稿文一覧</h2>
  <?php if (!$posts): ?>
    <p>まだ投稿文はありません。</p>
  <?php endif; ?>

  <div class="post-list">
    <?php foreach ($posts as $post): ?>
      <?php
        $status = (string)($post['status'] ?? 'draft');
        $statusLabel = ['draft' => '未投稿', 'copied' => 'コピー済み', 'posted' => '投稿済み'][$status] ?? '未投稿';
      ?>
      <article class="post-card">
        <div class="post-head">
          <div>
            <h3><?= e($post['title']) ?></h3>
            <div class="post-meta">
              作成日：<?= e($post['created_at'] ?? '') ?>
              <?php if (!empty($post['posted_at'])): ?>｜投稿日：<?= e($post['posted_at']) ?><?php endif; ?>
            </div>
          </div>
          <span class="post-status <?= e($status) ?>"><?= e($statusLabel) ?></span>
        </div>

        <textarea readonly><?= e($post['body']) ?></textarea>

        <div class="post-actions">
          <button type="button" data-copy>投稿文をコピー</button>
          <?php if ($status !== 'posted'): ?>
            <form method="post" action="<?= e(url('/posts/status')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
              <input type="hidden" name="status" value="posted">
              <button class="primary">投稿済みにする</button>
            </form>
          <?php else: ?>
            <form method="post" action="<?= e(url('/posts/status')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
              <input type="hidden" name="status" value="draft">
              <button>未投稿に戻す</button>
            </form>
          <?php endif; ?>
          <form method="post" action="<?= e(url('/posts/delete')) ?>" onsubmit="return confirm('この投稿文を削除しますか？')">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
            <button>削除</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<script>
document.querySelectorAll('[data-copy]').forEach(button => {
  button.addEventListener('click', async () => {
    const textarea = button.closest('.post-card').querySelector('textarea');
    await navigator.clipboard.writeText(textarea.value);
    button.textContent = 'コピーしました';
  });
});
</script>
