<?php use function App\Core\e; ?>
<style>
.rss-builder{display:grid;gap:24px}.page-intro,.rss-toolbar,.rss-template,.rss-history,.rss-section{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px}.page-intro h1{margin:0 0 8px}.page-intro p{margin:0;color:#50575e}.step-title{display:flex;align-items:center;gap:10px;margin:0 0 8px}.step-number{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:#2271b1;color:#fff;font-weight:700}.step-help{margin:0 0 16px;color:#646970}.rss-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.rss-toolbar button,.rss-card button,.rss-template button{padding:9px 14px;border:1px solid #2271b1;border-radius:5px;background:#2271b1;color:#fff;cursor:pointer}.rss-toolbar .secondary,.rss-card .secondary,.rss-template .secondary{background:#fff;color:#2271b1}.rss-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:16px}.rss-card{background:#fff;border:1px solid #dcdcde;border-radius:9px;overflow:hidden}.rss-card.selected{outline:3px solid #72aee6}.rss-card img{display:block;width:100%;aspect-ratio:16/9;object-fit:cover;background:#f0f0f1}.rss-card-body{padding:14px}.rss-card h3{margin:8px 0;font-size:17px;line-height:1.5}.rss-meta{font-size:12px;color:#646970}.rss-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.rss-template textarea{width:100%;min-height:180px;padding:12px;box-sizing:border-box;font-family:inherit}.rss-shortcodes{display:flex;gap:7px;flex-wrap:wrap;margin:10px 0}.rss-shortcodes code{padding:5px 7px;background:#f0f0f1;border-radius:4px;cursor:pointer}.rss-preview-list{display:grid;gap:14px}.rss-preview{display:grid;grid-template-columns:180px 1fr;gap:15px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px}.rss-preview img{width:100%;aspect-ratio:16/9;object-fit:cover}.rss-preview textarea{width:100%;min-height:145px;box-sizing:border-box;padding:10px}.rss-history table{width:100%;border-collapse:collapse}.rss-history th,.rss-history td{padding:10px;border-bottom:1px solid #dcdcde;text-align:left}.rss-empty{padding:30px;text-align:center;background:#fff;border:1px dashed #b6b6b6;border-radius:8px;color:#646970}.optional-label{font-size:12px;background:#f0f0f1;border-radius:12px;padding:3px 9px;color:#50575e}@media(max-width:700px){.rss-preview{grid-template-columns:1fr}.rss-preview img{max-width:100%}}
</style>

<section class="rss-builder">
  <div class="page-intro">
    <h1>RSS投稿作成</h1>
    <p>RSSの記事を選び、Xへ投稿する文章と画像を準備するページです。上から順番に操作してください。</p>
  </div>

  <section class="rss-section">
    <h2 class="step-title"><span class="step-number">1</span>投稿したい記事を選ぶ</h2>
    <p class="step-help">1件だけなら「この1件を作成」、複数ならチェックを付けて「選択した記事で作成」を押します。</p>

    <div class="rss-toolbar">
      <strong>表示するRSSサイト</strong>
      <select><option>すべてのサイト</option><option>サンプル動画ニュース</option><option>夜ふかしセレクト</option></select>
      <button type="button" class="secondary" id="select-all">すべて選択</button>
      <button type="button" class="secondary" id="clear-all">選択解除</button>
      <button type="button" id="generate-selected">選択した記事で作成</button>
    </div>

    <div class="rss-grid" style="margin-top:16px">
      <?php foreach ($articles as $article): ?>
        <article class="rss-card" data-article='<?= e(json_encode($article, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>'>
          <img src="<?= e($article['image_url']) ?>" alt="">
          <div class="rss-card-body">
            <label><input type="checkbox" class="article-check"> この記事を選択</label>
            <div class="rss-meta"><?= e($article['site_name']) ?>｜<?= e($article['published_at']) ?></div>
            <h3><?= e($article['title']) ?></h3>
            <p><?= e($article['description']) ?></p>
            <div class="rss-meta"><?= e($article['hashtags']) ?></div>
            <div class="rss-actions">
              <button type="button" class="generate-one">この1件を作成</button>
              <a href="<?= e($article['url']) ?>" target="_blank" rel="noopener">元の記事を見る</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="rss-template">
    <h2 class="step-title"><span class="step-number">2</span>投稿文の形を決める <span class="optional-label">必要な時だけ変更</span></h2>
    <p class="step-help">最初から基本の形が入っています。通常は変更せず、そのまま使えます。</p>
    <details>
      <summary>投稿レイアウトを編集する</summary>
      <p>下の項目をクリックすると、投稿文へ差し込む記号を追加できます。</p>
      <div class="rss-shortcodes">
        <?php foreach (['{title}','{url}','{image_url}','{description}','{site_name}','{published_at}','{hashtags}'] as $code): ?>
          <code data-code="<?= e($code) ?>"><?= e($code) ?></code>
        <?php endforeach; ?>
      </div>
      <textarea id="post-template">【{site_name}】

{title}

{description}

詳しくはこちら
{url}

{hashtags}</textarea>
    </details>
  </section>

  <section class="rss-section">
    <h2 class="step-title"><span class="step-number">3</span>投稿文と画像をコピーする</h2>
    <p class="step-help">作成された文章をコピーし、画像を確認してXへ貼り付けます。</p>
    <div id="preview-list" class="rss-preview-list"><div class="rss-empty">まだ投稿文はありません。上の記事から「この1件を作成」または「選択した記事で作成」を押してください。</div></div>
  </section>

  <div class="rss-history">
    <h2>この画面での操作履歴</h2>
    <p class="step-help">現在は試作のため、ページを閉じると履歴は消えます。</p>
    <table>
      <thead><tr><th>日時</th><th>記事タイトル</th><th>状態</th></tr></thead>
      <tbody id="history-body"><tr><td colspan="3">まだ履歴はありません。</td></tr></tbody>
    </table>
  </div>
</section>

<script>
(() => {
  const cards = [...document.querySelectorAll('.rss-card')];
  const template = document.getElementById('post-template');
  const previewList = document.getElementById('preview-list');
  const historyBody = document.getElementById('history-body');
  const history = [];
  const articleOf = card => JSON.parse(card.dataset.article);
  const renderText = article => template.value.replace(/\{(title|url|image_url|description|site_name|published_at|hashtags)\}/g, (_, key) => article[key] || '');
  function updateSelected(card){card.classList.toggle('selected',card.querySelector('.article-check').checked)}
  function escapeHtml(value){return String(value).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]))}
  function drawHistory(){historyBody.innerHTML=history.map(row=>`<tr><td>${escapeHtml(row.time)}</td><td>${escapeHtml(row.title)}</td><td>${escapeHtml(row.status)}</td></tr>`).join('')}
  function addHistory(article,status){history.unshift({time:new Date().toLocaleString('ja-JP'),title:article.title,status});drawHistory()}
  function buildPreview(article){
    const wrapper=document.createElement('article');wrapper.className='rss-preview';
    wrapper.innerHTML=`<div><img src="${escapeHtml(article.image_url)}" alt=""><button type="button" class="secondary copy-image" style="margin-top:8px">画像URLをコピー</button></div><div><strong>${escapeHtml(article.title)}</strong><textarea>${escapeHtml(renderText(article))}</textarea><div class="rss-actions"><button type="button" class="copy-post">投稿文をコピー</button><button type="button" class="secondary mark-posted">投稿済みにする</button><a href="https://x.com/intent/post" target="_blank" rel="noopener">Xを開く</a></div></div>`;
    wrapper.querySelector('.copy-post').addEventListener('click',async()=>{await navigator.clipboard.writeText(wrapper.querySelector('textarea').value);addHistory(article,'コピー済み');wrapper.querySelector('.copy-post').textContent='コピーしました'});
    wrapper.querySelector('.copy-image').addEventListener('click',async()=>{await navigator.clipboard.writeText(article.image_url);wrapper.querySelector('.copy-image').textContent='コピーしました'});
    wrapper.querySelector('.mark-posted').addEventListener('click',()=>{addHistory(article,'投稿済み');wrapper.querySelector('.mark-posted').textContent='投稿済み'});
    return wrapper;
  }
  function generate(list){previewList.innerHTML='';if(!list.length){previewList.innerHTML='<div class="rss-empty">記事が選択されていません。</div>';return}list.forEach(article=>previewList.appendChild(buildPreview(article)));previewList.scrollIntoView({behavior:'smooth',block:'start'})}
  cards.forEach(card=>{card.querySelector('.article-check').addEventListener('change',()=>updateSelected(card));card.querySelector('.generate-one').addEventListener('click',()=>generate([articleOf(card)]))});
  document.getElementById('select-all').addEventListener('click',()=>cards.forEach(card=>{card.querySelector('.article-check').checked=true;updateSelected(card)}));
  document.getElementById('clear-all').addEventListener('click',()=>cards.forEach(card=>{card.querySelector('.article-check').checked=false;updateSelected(card)}));
  document.getElementById('generate-selected').addEventListener('click',()=>generate(cards.filter(card=>card.querySelector('.article-check').checked).map(articleOf)));
  document.querySelectorAll('.rss-shortcodes code').forEach(code=>code.addEventListener('click',()=>{const start=template.selectionStart;template.value=template.value.slice(0,start)+code.dataset.code+template.value.slice(template.selectionEnd);template.focus();template.selectionStart=template.selectionEnd=start+code.dataset.code.length}));
})();
</script>