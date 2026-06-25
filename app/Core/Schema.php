<?php

declare(strict_types=1);

namespace App\Core;

final class Schema
{
    public static function migrate(): void
    {
        $pdo = Database::pdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $auto = $driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $text = $driver === 'mysql' ? 'LONGTEXT' : 'TEXT';
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (id $auto, name VARCHAR(100) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id $auto, email VARCHAR(190) NOT NULL, ip_address VARCHAR(64) NOT NULL, attempted_at DATETIME NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_settings (id $auto, service VARCHAR(50) NOT NULL UNIQUE, credentials $text NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (id $auto, service VARCHAR(50) NOT NULL, external_id VARCHAR(190) NOT NULL, title VARCHAR(500) NOT NULL, actress VARCHAR(500), genre VARCHAR(500), article_url VARCHAR(1000), affiliate_url VARCHAR(1000), image_url VARCHAR(1000), sample_movie_url VARCHAR(1000), raw_json $text, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE(service, external_id))");
        $pdo->exec("CREATE TABLE IF NOT EXISTS post_templates (id $auto, name VARCHAR(190) NOT NULL, body $text NOT NULL, is_default INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS posts (id $auto, product_id INTEGER NOT NULL, template_id INTEGER, body $text NOT NULL, hashtags VARCHAR(1000), status VARCHAR(30) NOT NULL DEFAULT 'draft', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS ng_words (id $auto, word VARCHAR(190) NOT NULL UNIQUE, created_at DATETIME NOT NULL)");
        $count = (int)$pdo->query('SELECT COUNT(*) FROM post_templates')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare('INSERT INTO post_templates (name, body, is_default, created_at, updated_at) VALUES (?, ?, 1, ?, ?)');
            $body = "{title}\n\nサンプル動画: {sample_movie_url}\n詳細: {article_url}\n{hashtags}";
            $now = date('Y-m-d H:i:s');
            $stmt->execute(['標準テンプレート', $body, $now, $now]);
        }
    }
}
