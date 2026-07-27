<?php use function App\Core\{e,url,csrf_field}; ?>
<style>
.post-list{display:grid;gap:16px}.post-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}.post-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}.post-status{display:inline-block;padding:4px 9px;border-radius:12px;background:#f0f0f1;font-size:12px}.post-status.posted{background:#dff4e5;color:#0a5c2b}.post-card textarea{width:100%;min-height:130px;box-sizing:border-box;margin:12px 0}.post-actions{display:flex;gap:8px;flex-wrap:wrap}.post-actions form{margin:0}.post-meta{color:#646970;font-size:12px}.filters{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.bulk-bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
</style>
<section class="card">
  <h1>投稿管理</h1>
  <p>API・RSS・動画から作成した投稿をまとめて管理します。コピーすると自動で「投稿済み」になります。</p>
  <div class="filters">
    <a href="<?= e(url('/posts')) ?>">すべて</a>
    <a href="<?= e(url('/posts?status=draft')) ?>">未投稿</a>
    <a href="<?= e(url('/posts?status=posted')) ?>">投稿済み</a>
  </div>
</section>

<section class="card">
  <h2>投稿一覧</h2>
  <?php if (!$posts): ?><p>該当する投稿はありません。</p><?php endif; ?>
  <form method="post" action="<?= e(url('/posts/bulk-delete')) ?>" onsubmit="return confirm('選択した投稿を一括削除しますか？')">
    <?= csrf_field() ?>
    <div class="bulk-bar">
      <label><input type="checkbox" data-select-all> 全選択</label>
      <button type="submit">選択した投稿を一括削除</button>
    </div>
    <div class="post-list">
      <?php foreach ($posts as $post): ?>
        <?php $status = (string)($post['status'] ?? 'draft'); ?>
        <article class="post-card" data-post-id="<?= (int)$post['id'] ?>">
          <div class="post-head">
            <div>
              <label><input type="checkbox" name="ids[]" value="<?= (int)$post['id'] ?>" data-row-check> 選択</label>
              <h3><?= e($post['title']) ?></h3>
              <div class="post-meta">取得元：<?= e(strtoupper((string)$post['source_type'])) ?>｜作成日：<?= e($post['created_at'] ?? '') ?></div>
            </div>
            <span class="post-status <?= e($status) ?>" data-status><?= $status === 'posted' ? '投稿済み' : '未投稿' ?></span>
          </div>
          <textarea readonly><?= e($post['body']) ?></textarea>
          <div class="post-actions">
            <button type="button" class="primary" data-copy data-token="<?= e($_SESSION['csrf_token'] ?? '') ?>"><?= $status === 'posted' ? '再コピー' : '投稿文をコピー' ?></button>
            <button type="submit" formaction="<?= e(url('/posts/delete')) ?>" formmethod="post" name="id" value="<?= (int)$post['id'] ?>" onclick="return confirm('この投稿を削除しますか？')">削除</button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </form>
</section>
<script>
const all = document.querySelector('[data-select-all]');
if (all) all.addEventListener('change', () => document.querySelectorAll('[data-row-check]').forEach(c => c.checked = all.checked));
document.querySelectorAll('[data-copy]').forEach(button => {
  button.addEventListener('click', async () => {
    const card = button.closest('.post-card');
    const textarea = card.querySelector('textarea');
    try {
      await navigator.clipboard.writeText(textarea.value);
      const body = new URLSearchParams({id: card.dataset.postId, csrf_token: button.dataset.token});
      const response = await fetch('<?= e(url('/posts/copied')) ?>', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body});
      if (!response.ok) throw new Error();
      card.querySelector('[data-status]').textContent = '投稿済み';
      card.querySelector('[data-status]').classList.add('posted');
      button.textContent = '再コピー';
    } catch (e) {
      alert('コピーまたは状態更新に失敗しました。もう一度お試しください。');
    }
  });
});
</script>