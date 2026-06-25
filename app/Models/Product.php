<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Product
{
    public static function all(): array { return Database::pdo()->query('SELECT * FROM products ORDER BY id DESC')->fetchAll(); }
    public static function find(int $id): ?array { $s=Database::pdo()->prepare('SELECT * FROM products WHERE id=?'); $s->execute([$id]); return $s->fetch() ?: null; }
    public static function upsert(array $p): void
    {
        $sql='INSERT INTO products (service, external_id, title, actress, genre, article_url, affiliate_url, image_url, sample_movie_url, raw_json, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';
        $now=date('Y-m-d H:i:s');
        try { Database::pdo()->prepare($sql)->execute([$p['service'],$p['external_id'],$p['title'],$p['actress']??null,$p['genre']??null,$p['article_url']??null,$p['affiliate_url']??null,$p['image_url']??null,$p['sample_movie_url']??null,json_encode($p['raw']??[], JSON_UNESCAPED_UNICODE),$now,$now]); }
        catch (\PDOException) { $u=Database::pdo()->prepare('UPDATE products SET title=?, actress=?, genre=?, article_url=?, affiliate_url=?, image_url=?, sample_movie_url=?, raw_json=?, updated_at=? WHERE service=? AND external_id=?'); $u->execute([$p['title'],$p['actress']??null,$p['genre']??null,$p['article_url']??null,$p['affiliate_url']??null,$p['image_url']??null,$p['sample_movie_url']??null,json_encode($p['raw']??[], JSON_UNESCAPED_UNICODE),$now,$p['service'],$p['external_id']]); }
    }
}
