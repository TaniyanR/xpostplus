<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Database, View};
use App\Models\Product;
use App\Services\{HashtagService, PostTemplateService, SettingsService};
use function App\Core\{flash, redirect, verify_csrf};

final class PostController extends Controller
{
    public function index(): string
    {
        $this->requireAuth();
        $pdo = Database::pdo();

        return View::render('posts/index', [
            'products' => Product::all(),
            'templates' => $pdo->query('SELECT * FROM post_templates ORDER BY is_default DESC, id DESC')->fetchAll(),
            'posts' => $pdo->query('SELECT posts.*, products.title FROM posts JOIN products ON products.id = posts.product_id ORDER BY posts.id DESC')->fetchAll(),
        ]);
    }

    public function generate(): string
    {
        $this->requireAuth();
        verify_csrf();

        $ids = $_POST['product_ids'] ?? [];
        $templateId = (int)($_POST['template_id'] ?? 0);
        $pdo = Database::pdo();
        $templateStatement = $pdo->prepare('SELECT * FROM post_templates WHERE id = ?');
        $templateStatement->execute([$templateId]);
        $template = $templateStatement->fetch();

        if (!$template) {
            flash('error', 'テンプレートが見つかりません。');
            redirect('/posts');
        }

        $ngWords = (new SettingsService())->ngWords();
        $hashtagService = new HashtagService();
        $renderer = new PostTemplateService();
        $insert = $pdo->prepare('INSERT INTO posts (product_id, template_id, body, hashtags, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');

        foreach ($ids as $id) {
            $product = Product::find((int)$id);
            if (!$product) {
                continue;
            }

            $hashtags = $hashtagService->generate($product, $ngWords);
            $body = $renderer->render($template['body'], $product, $hashtags);
            $now = date('Y-m-d H:i:s');
            $insert->execute([$product['id'], $templateId, $body, $hashtags, 'draft', $now, $now]);
        }

        flash('success', '投稿文を生成しました。');
        redirect('/posts');
    }

    public function delete(): string
    {
        $this->requireAuth();
        verify_csrf();
        Database::pdo()->prepare('DELETE FROM posts WHERE id = ?')->execute([(int)$_POST['id']]);
        redirect('/posts');
    }
}
