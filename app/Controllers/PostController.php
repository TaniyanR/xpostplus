<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller, Database, View};
use function App\Core\{flash, redirect, verify_csrf};

final class PostController extends Controller
{
    public function index(): string
    {
        $this->requireAuth();
        $filter = (string)($_GET['status'] ?? 'all');
        $where = in_array($filter, ['draft', 'posted'], true) ? 'WHERE posts.status = ' . Database::pdo()->quote($filter) : '';
        $posts = Database::pdo()->query(
            "SELECT posts.*, COALESCE(products.title, '記事タイトル未設定') AS title,
                    COALESCE(products.service, 'rss') AS source_type
             FROM posts
             LEFT JOIN products ON products.id = posts.product_id
             $where ORDER BY posts.id DESC"
        )->fetchAll();
        return View::render('posts/index', ['posts' => $posts, 'filter' => $filter]);
    }

    public function copied(): string
    {
        $this->requireAuth();
        verify_csrf();
        $id = (int)($_POST['id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare("UPDATE posts SET status='posted', copied_at=?, posted_at=COALESCE(posted_at, ?), updated_at=? WHERE id=?")
            ->execute([$now, $now, $now, $id]);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode(['ok' => true, 'posted_at' => $now], JSON_UNESCAPED_UNICODE);
    }

    public function delete(): string
    {
        $this->requireAuth();
        verify_csrf();
        Database::pdo()->prepare('DELETE FROM posts WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('success', '投稿を削除しました。');
        redirect('/posts');
    }

    public function bulkDelete(): string
    {
        $this->requireAuth();
        verify_csrf();
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
        if (!$ids) { flash('error', '削除する投稿を選択してください。'); redirect('/posts'); }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        Database::pdo()->prepare("DELETE FROM posts WHERE id IN ($marks)")->execute($ids);
        flash('success', count($ids) . '件の投稿を削除しました。');
        redirect('/posts');
    }
}