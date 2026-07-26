<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, View};
use App\Models\Product;
use App\Services\SettingsService;
use App\Services\Affiliate\{DugaService, FanzaService, SokmilService};
use function App\Core\{flash, redirect, verify_csrf};

final class ProductController extends Controller
{
    private const SERVICES = ['fanza', 'duga', 'sokmil'];

    private function service(string $name): object
    {
        return match ($name) {
            'duga' => new DugaService(),
            'sokmil' => new SokmilService(),
            default => new FanzaService(),
        };
    }

    public function index(): string
    {
        $this->requireAuth();
        $settings = new SettingsService();

        return View::render('products/index', [
            'products' => Product::all(),
            'results' => $_SESSION['search_results'] ?? [],
            'searchedService' => $_SESSION['searched_service'] ?? 'fanza',
            'apiSettings' => [
                'fanza' => $settings->apiCredentials('fanza'),
                'duga' => $settings->apiCredentials('duga'),
                'sokmil' => $settings->apiCredentials('sokmil'),
            ],
        ]);
    }

    public function saveApi(): string
    {
        $this->requireAuth();
        verify_csrf();

        $service = (string)($_POST['service'] ?? '');
        if (!in_array($service, self::SERVICES, true)) {
            flash('error', '対象サービスが正しくありません。');
            redirect('/products');
        }

        $credentials = $_POST['credentials'] ?? [];
        if (!is_array($credentials)) {
            $credentials = [];
        }

        (new SettingsService())->saveApi($service, array_map(
            static fn ($value): string => trim((string)$value),
            $credentials
        ));

        flash('success', strtoupper($service) . 'のAPI設定を保存しました。');
        redirect('/products');
    }

    public function search(): string
    {
        $this->requireAuth();
        verify_csrf();

        $service = (string)($_POST['service'] ?? 'fanza');
        if (!in_array($service, self::SERVICES, true)) {
            $service = 'fanza';
        }

        $keyword = trim((string)($_POST['keyword'] ?? ''));
        $settings = new SettingsService();

        try {
            $_SESSION['search_results'] = $this->service($service)->search(
                $keyword,
                $settings->apiCredentials($service)
            );
            $_SESSION['searched_service'] = $service;
            flash('success', '商品取得が完了しました。投稿に使う商品を保存してください。');
        } catch (\Throwable $e) {
            $_SESSION['search_results'] = [];
            flash('error', '商品を取得できませんでした。API設定と入力内容を確認してください。');
        }

        redirect('/products');
    }

    public function save(): string
    {
        $this->requireAuth();
        verify_csrf();

        $index = (int)($_POST['index'] ?? -1);
        $rows = $_SESSION['search_results'] ?? [];

        if (!isset($rows[$index]) || !is_array($rows[$index])) {
            flash('error', '保存する商品が見つかりません。');
            redirect('/products');
        }

        Product::upsert($rows[$index]);
        flash('success', '商品を投稿候補として保存しました。');
        redirect('/products');
    }
}
