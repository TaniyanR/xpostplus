<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, View};
use App\Services\SettingsService;
use function App\Core\{flash, redirect, verify_csrf};

final class SettingController extends Controller
{
    public function index(): string
    {
        $this->requireAuth();
        $settings = new SettingsService();

        return View::render('settings/index', [
            'settings' => [
                'fanza' => $settings->apiCredentials('fanza'),
                'sokmil' => $settings->apiCredentials('sokmil'),
                'duga' => $settings->apiCredentials('duga'),
            ],
            'ngWords' => implode("\n", $settings->ngWords()),
        ]);
    }

    public function saveApi(): string
    {
        $this->requireAuth();
        verify_csrf();

        $service = $_POST['service'] ?? 'fanza';
        $data = $_POST['credentials'] ?? [];
        (new SettingsService())->saveApi($service, array_map('trim', $data));

        flash('success', 'API設定を保存しました。');
        redirect('/settings');
    }

    public function saveNgWords(): string
    {
        $this->requireAuth();
        verify_csrf();
        (new SettingsService())->saveNgWords((string)($_POST['ng_words'] ?? ''));

        flash('success', 'NGワードを保存しました。');
        redirect('/settings');
    }
}
