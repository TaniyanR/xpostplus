<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class SettingsService
{
    public function apiCredentials(string $service): array
    {
        $s=Database::pdo()->prepare('SELECT credentials FROM api_settings WHERE service=?'); $s->execute([$service]);
        return json_decode((string)($s->fetchColumn() ?: '{}'), true) ?: [];
    }
    public function saveApi(string $service, array $credentials): void
    {
        $now=date('Y-m-d H:i:s');
        try { Database::pdo()->prepare('INSERT INTO api_settings (service, credentials, enabled, updated_at) VALUES (?,?,1,?)')->execute([$service,json_encode($credentials, JSON_UNESCAPED_UNICODE),$now]); }
        catch (\PDOException) { Database::pdo()->prepare('UPDATE api_settings SET credentials=?, updated_at=? WHERE service=?')->execute([json_encode($credentials, JSON_UNESCAPED_UNICODE),$now,$service]); }
    }
    public function ngWords(): array { return array_column(Database::pdo()->query('SELECT word FROM ng_words ORDER BY word')->fetchAll(), 'word'); }
    public function saveNgWords(string $words): void
    {
        $pdo=Database::pdo(); $pdo->exec('DELETE FROM ng_words');
        $stmt=$pdo->prepare('INSERT INTO ng_words (word, created_at) VALUES (?, ?)');
        foreach (array_filter(array_unique(array_map('trim', preg_split('/\R/u', $words) ?: []))) as $w) $stmt->execute([$w,date('Y-m-d H:i:s')]);
    }
}
