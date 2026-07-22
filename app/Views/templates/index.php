<?php use function App\Core\{e,url,csrf_field}; ?>
<style>
.template-editor{display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.8fr);gap:18px}.tag-buttons{display:flex;flex-wrap:wrap;gap:7px;margin:10px 0}.tag-buttons button{padding:7px 10px}.preview-box{white-space:pre-wrap;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:16px;min-height:220px}.template textarea{white-space:pre-wrap}@media(max-width:800px){.template-editor{grid-template-columns:1fr}}
</style>
<section class="card">
  <h1>テンプレート</h1>
  <p>記事情報のタグ、固定ハッシュタグ、絵文字、記号を自由に組み合わせてX投稿文の形を作ります。</p>
</section>
<section class="card template-editor">
  <div>
    <h2>新しいテンプレート</h2>
    <form method="post" action="<?= e(url('/templates')) ?>" id="template-form">
      <?= csrf_field() ?>
      <label>テンプレート名<input name="name" required placeholder="例：新着記事用"></label>
      <p><strong>記事情報を挿入</strong></p>
      <div class="tag-buttons">
        <?php foreach (['{title}'=>'タイトル','{url}'=>'記事URL','{description}'=>'記事説明','{site_name}'=>'サイト名','{published_at}'=>'公開日','{hashtags}'=>'自動ハッシュタグ','{image_url}'=>'画像URL'] as $tag=>$label): ?>
          <button type="button" data-insert="<?= e($tag) ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
      </div>
      <p><strong>装飾を挿入</strong></p>
      <div class="tag-buttons">
        <?php foreach (['🔥','✨','▼','▶','👇','━━━━━━━━━━━━','【】','#FANZA','#新作動画'] as $decoration): ?>
          <button type="button" data-insert="<?= e($decoration) ?>"><?= e($decoration) ?></button>
        <?php endforeach; ?>
      </div>
      <label>投稿文<textarea id="template-body" name="body" required rows="13">🔥おすすめ記事🔥

{title}

{description}

▼詳しくはこちら
{url}

{hashtags}</textarea></label>
      <button class="primary">テンプレートを保存</button>
    </form>
  </div>
  <div>
    <h2>完成イメージ</h2>
    <div id="template-preview" class="preview-box"></div>
    <p><small>ここではサンプル記事を使って表示します。</small></p>
  </div>
</section>
<section class="card">
  <h2>保存済みテンプレート</h2>
  <?php foreach($templates as $t): ?>
    <article class="template"><h3><?= e($t['name']) ?></h3><pre><?= e($t['body']) ?></pre><?php if(!$t['is_default']): ?><form method="post" action="<?= e(url('/templates/delete')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button>削除</button></form><?php endif; ?></article>
  <?php endforeach; ?>
</section>
<script>
(() => {
 const body=document.getElementById('template-body'), preview=document.getElementById('template-preview');
 const sample={title:'今夜チェックしたいおすすめ作品を紹介',url:'https://example.com/article',description:'記事の紹介文がここに入ります。',site_name:'サンプルサイト',published_at:'2026年7月22日',hashtags:'#おすすめ #新着記事',image_url:'https://example.com/image.jpg'};
 function render(){preview.textContent=body.value.replace(/\{(title|url|description|site_name|published_at|hashtags|image_url)\}/g,(_,key)=>sample[key]||'');}
 document.querySelectorAll('[data-insert]').forEach(button=>button.addEventListener('click',()=>{const text=button.dataset.insert,start=body.selectionStart,end=body.selectionEnd;body.value=body.value.slice(0,start)+text+body.value.slice(end);body.focus();body.selectionStart=body.selectionEnd=start+text.length;render();}));
 body.addEventListener('input',render);render();
})();
</script>