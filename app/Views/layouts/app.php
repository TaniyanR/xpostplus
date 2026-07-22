<?php use function App\Core\{e,url,csrf_field,flash};
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isCurrent = static function (string $path) use ($currentPath): string {
    if ($path === '/') return rtrim($currentPath, '/') === '' ? ' current' : '';
    return str_ends_with(rtrim($currentPath, '/'), $path) ? ' current' : '';
};
$pageTitles = [
    '/rss-posts' => 'RSS投稿作成',
    '/templates' => 'テンプレート',
    '/posts' => '投稿作成',
    '/sites' => 'サイト設定',
    '/password' => 'パスワード変更',
];
$pageTitle = 'ダッシュボード';
foreach ($pageTitles as $path => $title) {
    if (str_ends_with(rtrim($currentPath, '/'), $path)) { $pageTitle = $title; break; }
}
?>
<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($pageTitle) ?> | XPostPlus</title><link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>"><style>aside nav a.current{background:#2271b1;color:#fff;font-weight:700}.page-location{display:flex;align-items:center;gap:8px}.page-location small{color:#646970;font-weight:400}</style></head><body><div class="app"><aside><h1>XPostPlus</h1><nav><a class="<?= e($isCurrent('/')) ?>" href="<?= e(url('/')) ?>">ダッシュボード</a><a class="<?= e($isCurrent('/rss-posts')) ?>" href="<?= e(url('/rss-posts')) ?>">RSS投稿作成</a><a class="<?= e($isCurrent('/templates')) ?>" href="<?= e(url('/templates')) ?>">テンプレート</a><a class="<?= e($isCurrent('/posts')) ?>" href="<?= e(url('/posts')) ?>">投稿作成</a><a class="<?= e($isCurrent('/sites')) ?>" href="<?= e(url('/sites')) ?>">サイト設定</a></nav><form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button>ログアウト</button></form></aside><main><header><strong class="page-location"><small>現在のページ</small><?= e($pageTitle) ?></strong><span><?= e($_SESSION['user_name']??'') ?></span></header><?php foreach(['success','error'] as $k): if($m=flash($k)): ?><div class="notice <?= $k ?>"><?= e($m) ?></div><?php endif; endforeach; ?><?= $content ?></main></div><script src="<?= e(url('/assets/js/app.js')) ?>"></script></body></html>