<?php use function App\Core\{e,url,csrf_field}; ?>
<section class="card">
  <h1>動画投稿</h1>
  <p>動画ページURLまたはMP4 URLを登録し、投稿用動画の取得・加工に使う素材を管理します。</p>
</section>

<section class="card">
  <h2>動画素材を登録</h2>
  <form method="post" action="<?= e(url('/videos')) ?>">
    <?= csrf_field() ?>
    <label>取得方法
      <select name="source_type">
        <option value="page">動画ページURL</option>
        <option value="mp4">MP4 URL</option>
      </select>
    </label>
    <label>URL
      <input type="url" name="source_url" required placeholder="https://example.com/video または https://example.com/movie.mp4">
    </label>
    <label>管理用タイトル（任意）
      <input name="title" placeholder="動画名や識別しやすい名前">
    </label>
    <button class="primary">動画素材を登録</button>
  </form>
  <p><small>現在はURLと素材情報をDBへ保存する段階です。実際のダウンロード、カット、リサイズ、文字入れは安全確認を行いながら次の段階で接続します。</small></p>
</section>

<section class="card">
  <h2>登録済みの動画素材</h2>
  <?php if (!$videos): ?>
    <p>登録済みの動画素材はありません。</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('/videos/delete')) ?>" onsubmit="return confirm('選択した動画素材を削除しますか？')">
      <?= csrf_field() ?>
      <p class="bulk-actions"><label class="check-item"><input type="checkbox" data-video-all> 全選択</label><button>選択した動画を一括削除</button></p>
      <div class="table-wrap"><table>
        <thead><tr><th></th><th>種類</th><th>タイトル</th><th>URL</th><th>状態</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($videos as $video): ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int)$video['id'] ?>" data-video-check></td>
            <td><?= $video['source_type'] === 'mp4' ? 'MP4 URL' : '動画ページURL' ?></td>
            <td><?= e($video['title'] ?: 'タイトル未設定') ?></td>
            <td><a href="<?= e($video['source_url']) ?>" target="_blank" rel="noopener">元URLを開く</a></td>
            <td>登録済み</td>
            <td><button name="id" value="<?= (int)$video['id'] ?>" onclick="return confirm('この動画素材を削除しますか？')">個別削除</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </form>
  <?php endif; ?>
</section>
<script>
const all=document.querySelector('[data-video-all]');if(all)all.addEventListener('change',()=>document.querySelectorAll('[data-video-check]').forEach(c=>c.checked=all.checked));
</script>
