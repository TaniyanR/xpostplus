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
        $pdo->exec("CREATE TABLE IF NOT EXISTS sites (id $auto, name VARCHAR(190) NOT NULL, base_url VARCHAR(1000) NOT NULL, service VARCHAR(50), is_default INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (id $auto, service VARCHAR(50) NOT NULL, external_id VARCHAR(190) NOT NULL, title VARCHAR(500) NOT NULL, actress VARCHAR(500), genre VARCHAR(500), release_date DATE, price VARCHAR(100), article_url VARCHAR(1000), affiliate_url VARCHAR(1000), image_url VARCHAR(1000), sample_movie_url VARCHAR(1000), raw_json $text, fetched_at DATETIME, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE(service, external_id))");
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_media (id $auto, product_id INTEGER NOT NULL, media_type VARCHAR(30) NOT NULL, media_url VARCHAR(1000) NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS post_templates (id $auto, name VARCHAR(190) NOT NULL, body $text NOT NULL, is_default INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS posts (id $auto, product_id INTEGER NOT NULL, template_id INTEGER, site_id INTEGER, body $text NOT NULL, hashtags VARCHAR(1000), media_mode VARCHAR(30) NOT NULL DEFAULT 'text', status VARCHAR(30) NOT NULL DEFAULT 'draft', copied_at DATETIME, posted_at DATETIME, memo $text, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS ng_words (id $auto, word VARCHAR(190) NOT NULL UNIQUE, created_at DATETIME NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_logs (id $auto, service VARCHAR(50) NOT NULL, action VARCHAR(100) NOT NULL, status VARCHAR(30) NOT NULL, message $text, created_at DATETIME NOT NULL)");

        self::createIndex($pdo, 'idx_login_attempts_lookup', 'login_attempts', 'email, ip_address, attempted_at');
        self::createIndex($pdo, 'idx_products_service_date', 'products', 'service, release_date');
        self::createIndex($pdo, 'idx_products_updated', 'products', 'updated_at');
        self::createIndex($pdo, 'idx_posts_status_created', 'posts', 'status, created_at');
        self::createIndex($pdo, 'idx_product_media_product', 'product_media', 'product_id, sort_order');
        self::createIndex($pdo, 'idx_api_logs_created', 'api_logs', 'created_at');

        $count = (int)$pdo->query('SELECT COUNT(*) FROM post_templates')->fetchColumn();
        if ($count === 0) {
            $statement = $pdo->prepare('INSERT INTO post_templates (name, body, is_default, created_at, updated_at) VALUES (?, ?, 1, ?, ?)');
            $body = "{title}\n\nサンプル動画: {sample_movie_url}\n詳細: {article_url}\n{hashtags}";
            $now = date('Y-m-d H:i:s');
            $statement->execute(['標準テンプレート', $body, $now, $now]);
        }

        $siteCount = (int)$pdo->query('SELECT COUNT(*) FROM sites')->fetchColumn();
        if ($siteCount === 0) {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO sites (name, base_url, service, is_default, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)')
                ->execute(['PinkClub FANZA', 'https://pinkclub-fanza.com/', 'fanza', $now, $now]);
        }
    }

    private static function createIndex(\PDO $pdo, string $name, string $table, string $columns): void
    {
        try {
            $pdo->exec("CREATE INDEX $name ON $table ($columns)");
        } catch (\PDOException $e) {
            $message = strtolower($e->getMessage());
            if (!str_contains($message, 'already exists') && !str_contains($message, 'duplicate key name')) {
                throw $e;
            }
        }
    }
}
