<?php use function App\Core\url; ?>
<section class="card" style="margin-bottom:20px">
  <h1>XPostPlusの使い方</h1>
  <p>自分のサイトのRSSから記事を取り込み、Xで紹介する投稿文を作るツールです。</p>
</section>
<section class="grid">
  <div class="card"><p><strong>STEP 1</strong></p><h2>サイトを登録</h2><p>サイトURL、RSS URL、固定ハッシュタグを登録します。</p><p><a href="<?= url('/sites') ?>">サイト設定を開く</a></p></div>
  <div class="card"><p><strong>STEP 2</strong></p><h2>記事を選択</h2><p>RSSの記事からXで紹介したいものを1件または複数選びます。</p><p><a href="<?= url('/rss-posts') ?>">RSS投稿作成を開く</a></p></div>
  <div class="card"><p><strong>STEP 3</strong></p><h2>投稿を作成</h2><p>テンプレートを適用し、投稿文・ハッシュタグ・画像を確認します。</p><p><a href="<?= url('/posts') ?>">投稿作成を開く</a></p></div>
</section>
<section class="card" style="margin-top:20px">
  <h2>テンプレートについて</h2>
  <p>記事タイトルやURLのタグだけでなく、固定ハッシュタグ、絵文字、区切り線なども自由に入れられます。</p>
  <p><a href="<?= url('/templates') ?>">テンプレートを作る</a></p>
</section>