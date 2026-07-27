<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller, Database, View};
use function App\Core\{flash, redirect, verify_csrf};

final class VideoController extends Controller
{
    public function index(): string
    {
        $this->requireAuth();
        $videos = Database::pdo()->query('SELECT * FROM video_assets ORDER BY id DESC')->fetchAll();
        return View::render('videos/index', ['videos' => $videos]);
    }

    public function store(): string
    {
        $this->requireAuth();
        verify_csrf();

        $sourceType = (string)($_POST['source_type'] ?? 'page');
        $sourceUrl = trim((string)($_POST['source_url'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));

        if (!in_array($sourceType, ['page', 'mp4'], true) || !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            flash('error', '動画ページURLまたはMP4 URLを正しく入力してください。');
            redirect('/videos');
        }

        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare('INSERT INTO video_assets (source_type, source_url, title, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$sourceType, $sourceUrl, $title !== '' ? $title : null, 'registered', $now, $now]);

        flash('success', '動画素材を登録しました。ダウンロード・加工処理は次の実装段階で接続します。');
        redirect('/videos');
    }

    public function delete(): string
    {
        $this->requireAuth();
        verify_csrf();
        $ids = $_POST['ids'] ?? [];
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $ids[] = $id;
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []))));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            Database::pdo()->prepare("DELETE FROM video_assets WHERE id IN ($placeholders)")->execute($ids);
        }
        flash('success', count($ids) . '件の動画素材を削除しました。');
        redirect('/videos');
    }
}
