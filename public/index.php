<?php

declare(strict_types=1);

use App\Core\{App, Router};
use App\Controllers\{AuthController, DashboardController, PostController, RssPostController, SiteController, TemplateController};

require dirname(__DIR__) . '/app/Core/bootstrap.php';

$router = new Router();
$router->get('/', [DashboardController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/password', [AuthController::class, 'showPassword']);
$router->post('/password', [AuthController::class, 'changePassword']);
$router->get('/rss-posts', [RssPostController::class, 'index']);
$router->get('/templates', [TemplateController::class, 'index']);
$router->post('/templates', [TemplateController::class, 'store']);
$router->post('/templates/delete', [TemplateController::class, 'delete']);
$router->get('/posts', [PostController::class, 'index']);
$router->post('/posts/generate', [PostController::class, 'generate']);
$router->post('/posts/delete', [PostController::class, 'delete']);
$router->get('/sites', [SiteController::class, 'index']);
$router->post('/sites', [SiteController::class, 'store']);
$router->post('/sites/delete', [SiteController::class, 'delete']);

(new App($router))->run();