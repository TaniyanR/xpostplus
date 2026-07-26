<?php use function App\Core\{e,url,csrf_field}; ?>
<section class="card">
  <h1>商品取得</h1>
  <p>FANZA・DUGA・SOKMILの公式APIから、X投稿に使う商品情報を取得します。Xへの自動投稿は行いません。</p>
</section>

<section class="grid">
  <?php
  $serviceFields = [
      'fanza' => [
          'label' => 'FANZA',
          'fields' => [
              'api_id' => 'API ID',
              'affiliate_id' => 'アフィリエイトID',
          ],
      ],
      'duga' => [
          'label' => 'DUGA',
          'fields' => [
              'appid' => 'アプリケーションID（APP ID）',
              'agentid' => '代理店ID（AGENT ID）',
          ],
      ],
      'sokmil' => [
          'label' => 'SOKMIL',
          'fields' => [
              'affiliate_id' => 'アフィリエイトID',
              'endpoint' => 'APIエンドポイント',
          ],
      ],
  ];
  ?>
  <?php foreach ($serviceFields as $service => $definition): ?>
    <article class="card">
      <h2><?= e($definition['label']) ?> API設定</h2>
      <form method="post" action="<?= e(url('/products/api')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="service" value="<?= e($service) ?>">
        <?php foreach ($definition['fields'] as $field => $label): ?>
          <label><?= e($label) ?>
            <input name="credentials[<?= e($field) ?>]" value="<?= e($apiSettings[$service][$field] ?? '') ?>" autocomplete="off">
          </label>
        <?php endforeach; ?>
        <button class="primary">API設定を保存</button>
      </form>
    </article>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2>商品を取得する</h2>
  <form class="inline" method="post" action="<?= e(url('/products/search')) ?>">
    <?= csrf_field() ?>
    <label>サービス
      <select name="service">
        <option value="fanza" <?= $searchedService === 'fanza' ? 'selected' : '' ?>>FANZA</option>
        <option value="duga" <?= $searchedService === 'duga' ? 'selected' : '' ?>>DUGA</option>
        <option value="sokmil" <?= $searchedService === 'sokmil' ? 'selected' : '' ?>>SOKMIL</option>
      </select>
    </label>
    <label>キーワード
      <input name="keyword" placeholder="例：女優名、ジャンル、作品名">
    </label>
    <button class="primary">商品を取得</button>
  </form>
  <p><small>API設定が未入力の場合は、現在の試作データが表示されることがあります。</small></p>
</section>

<?php if ($results): ?>
<section class="card">
  <h2>取得結果</h2>
  <div class="cards">
    <?php foreach ($results as $index => $product): ?>
      <article>
        <?php if (!empty($product['image_url'])): ?>
          <img src="<?= e($product['image_url']) ?>" alt="" loading="lazy">
        <?php endif; ?>
        <h3><?= e($product['title'] ?? '') ?></h3>
        <p><strong>サービス：</strong><?= e(strtoupper((string)($product['service'] ?? ''))) ?></p>
        <?php if (!empty($product['actress'])): ?><p><strong>出演者：</strong><?= e($product['actress']) ?></p><?php endif; ?>
        <?php if (!empty($product['genre'])): ?><p><strong>ジャンル：</strong><?= e($product['genre']) ?></p><?php endif; ?>
        <form method="post" action="<?= e(url('/products/save')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="index" value="<?= (int)$index ?>">
          <button class="primary">投稿候補へ保存</button>
        </form>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="card">
  <h2>保存済みの商品</h2>
  <?php if (!$products): ?>
    <p>保存済みの商品はありません。</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>サービス</th><th>タイトル</th><th>出演者</th><th>ジャンル</th></tr></thead>
        <tbody>
        <?php foreach ($products as $product): ?>
          <tr>
            <td><?= e(strtoupper((string)$product['service'])) ?></td>
            <td><?= e($product['title']) ?></td>
            <td><?= e($product['actress'] ?? '') ?></td>
            <td><?= e($product['genre'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
