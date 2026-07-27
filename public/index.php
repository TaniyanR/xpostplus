<?php

declare(strict_types=1);
use App\Core\{App, Router};
use App\Controllers\{AuthController, DashboardController, PostController, ProductController, SiteController, TemplateController, VideoController};

require dirname(__DIR__) . '/app/Core/bootstrap.php';

$router = new Router();
$router->get('/', [DashboardController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/settings', [AuthController::class, 'showSettings']);
$router->post('/settings/email', [AuthController::class, 'changeEmail']);
$router->post('/settings/password', [AuthController::class, 'changePassword']);
$router->get('/password', [AuthController::class, 'showSettings']);
$router->post('/password', [AuthController::class, 'changePassword']);
$router->get('/rss-posts', [SiteController::class, 'index']);
$router->post('/rss-posts', [SiteController::class, 'store']);
$router->post('/rss-posts/delete', [SiteController::class, 'delete']);
$router->get('/products', [ProductController::class, 'index']);
$router->post('/products/api', [ProductController::class, 'saveApi']);
$router->post('/products/search', [ProductController::class, 'search']);
$router->post('/products/save', [ProductController::class, 'save']);
$router->post('/products/delete', [ProductController::class, 'delete']);
$router->get('/videos', [VideoController::class, 'index']);
$router->post('/videos', [VideoController::class, 'store']);
$router->post('/videos/delete', [VideoController::class, 'delete']);
$router->get('/templates', [TemplateController::class, 'index']);
$router->post('/templates', [TemplateController::class, 'store']);
$router->post('/templates/delete', [TemplateController::class, 'delete']);
$router->get('/posts', [PostController::class, 'index']);
$router->post('/posts/copied', [PostController::class, 'copied']);
$router->post('/posts/delete', [PostController::class, 'delete']);
$router->post('/posts/bulk-delete', [PostController::class, 'bulkDelete']);
(new App($router))->run();
