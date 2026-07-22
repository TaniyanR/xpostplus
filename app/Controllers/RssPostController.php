<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, View};

final class RssPostController extends Controller
{
    public function index(): string
    {
        $this->requireAuth();

        $articles = [
            [
                'id' => 1,
                'site_name' => 'サンプル動画ニュース',
                'title' => '話題の新作動画をピックアップしました',
                'url' => 'https://example.com/articles/new-movie-01',
                'image_url' => 'https://placehold.co/640x360?text=EyeCatch+01',
                'description' => '新着作品の見どころを分かりやすく紹介するサンプル記事です。',
                'published_at' => date('Y-m-d H:i'),
                'hashtags' => '#新作動画 #おすすめ動画 #動画紹介',
            ],
            [
                'id' => 2,
                'site_name' => '夜ふかしセレクト',
                'title' => '今週チェックしておきたいおすすめ作品まとめ',
                'url' => 'https://example.com/articles/weekly-pickup',
                'image_url' => 'https://placehold.co/640x360?text=EyeCatch+02',
                'description' => '複数作品をまとめて確認できる紹介記事のサンプルです。',
                'published_at' => date('Y-m-d H:i', time() - 3600),
                'hashtags' => '#おすすめ作品 #動画まとめ #夜ふかしナビ',
            ],
            [
                'id' => 3,
                'site_name' => '大人の作品案内',
                'title' => '初めてでも選びやすい人気作品を紹介',
                'url' => 'https://example.com/articles/popular-guide',
                'image_url' => 'https://placehold.co/640x360?text=EyeCatch+03',
                'description' => '人気作品を選びやすく整理した記事を想定したサンプルです。',
                'published_at' => date('Y-m-d H:i', time() - 7200),
                'hashtags' => '#人気作品 #作品紹介 #おすすめ',
            ],
        ];

        return View::render('rss-posts/index', ['articles' => $articles]);
    }
}
