<?php use function App\Core\url; ?>
<section class="card" style="margin-bottom:20px">
  <h1>はじめに</h1>
  <p>初めて使う場合は、下の順番で進めてください。</p>
</section>

<section class="grid">
  <div class="card">
    <p><strong>STEP 1</strong></p>
    <h2>RSSサイトを登録する</h2>
    <p>紹介したいサイトのRSS URLを登録します。</p>
    <p><button type="button" disabled>RSSサイト管理（次に作成）</button></p>
  </div>

  <div class="card">
    <p><strong>STEP 2</strong></p>
    <h2>記事を選ぶ</h2>
    <p>RSSから取得した記事の中から、Xで紹介したい記事を選びます。</p>
    <p><a href="<?= url('/rss-posts') ?>">RSS投稿作成を開く</a></p>
  </div>

  <div class="card">
    <p><strong>STEP 3</strong></p>
    <h2>投稿文をコピーする</h2>
    <p>タイトル、URL、ハッシュタグを確認して、投稿文と画像をコピーします。</p>
    <p><a href="<?= url('/rss-posts') ?>">投稿文を作成する</a></p>
  </div>
</section>

<section class="card" style="margin-top:20px">
  <h2>現在できること</h2>
  <p>今は試作画面で、記事選択・投稿文作成・複数記事の一括作成・コピー操作を確認できます。</p>
  <p>RSSサイトの実登録、RSS取得、履歴保存はこれから接続します。</p>
</section>

<section class="grid" style="margin-top:20px">
  <div class="card"><h2>商品</h2><p class="big"><?= $products ?></p></div>
  <div class="card"><h2>投稿文</h2><p class="big"><?= $posts ?></p></div>
  <div class="card"><h2>テンプレート</h2><p class="big"><?= $templates ?></p></div>
</section>
