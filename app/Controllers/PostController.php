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
        $pdo = Database::pdo();

        $posts = $pdo->query(
            "SELECT posts.*, COALESCE(products.title, '記事タイトル未設定') AS title
             FROM posts
             LEFT JOIN products ON products.id = posts.product_id
             ORDER BY posts.id DESC"
        )->fetchAll();

        return View::render('posts/index', ['posts' => $posts]);
    }

    public function updateStatus(): string
    {
        $this->requireAuth();
        verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'copied', 'posted'], true)) {
            flash('error', '投稿状態が正しくありません。');
            redirect('/posts');
        }

        $now = date('Y-m-d H:i:s');
        $postedAt = $status === 'posted' ? $now : null;
        $copiedAt = in_array($status, ['copied', 'posted'], true) ? $now : null;

        Database::pdo()->prepare('UPDATE posts SET status = ?, copied_at = COALESCE(?, copied_at), posted_at = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, $copiedAt, $postedAt, $now, $id]);

        flash('success', $status === 'posted' ? '投稿済みに変更しました。' : '投稿状態を変更しました。');
        redirect('/posts');
    }

    public function delete(): string
    {
        $this->requireAuth();
        verify_csrf();
        Database::pdo()->prepare('DELETE FROM posts WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('success', '投稿文を削除しました。');
        redirect('/posts');
    }
}
