<?php

declare(strict_types=1);

use App\Core\{App, Router};
use App\Controllers\{AuthController, DashboardController, ProductController, PostController, RssPostController, SettingController, TemplateController};

require dirname(__DIR__) . '/app/Core/bootstrap.php';

$router = new Router();
$router->get('/', [DashboardController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/password', [AuthController::class, 'showPassword']);
$router->post('/password', [AuthController::class, 'changePassword']);
$router->get('/products', [ProductController::class, 'index']);
$router->post('/products/search', [ProductController::class, 'search']);
$router->post('/products/save', [ProductController::class, 'save']);
$router->get('/templates', [TemplateController::class, 'index']);
$router->post('/templates', [TemplateController::class, 'store']);
$router->post('/templates/delete', [TemplateController::class, 'delete']);
$router->get('/posts', [PostController::class, 'index']);
$router->post('/posts/generate', [PostController::class, 'generate']);
$router->post('/posts/delete', [PostController::class, 'delete']);
$router->get('/rss-posts', [RssPostController::class, 'index']);
$router->get('/settings', [SettingController::class, 'index']);
$router->post('/settings/api', [SettingController::class, 'saveApi']);
$router->post('/settings/ng-words', [SettingController::class, 'saveNgWords']);

(new App($router))->run();
