<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class SettingsService
{
    public function apiCredentials(string $service): array
    {
        $statement = Database::pdo()->prepare('SELECT credentials FROM api_settings WHERE service = ?');
        $statement->execute([$service]);
        $payload = (string)($statement->fetchColumn() ?: '');

        return (new CredentialCipher())->decrypt($payload);
    }

    public function saveApi(string $service, array $credentials): void
    {
        $allowed = ['fanza', 'sokmil', 'duga'];
        if (!in_array($service, $allowed, true)) {
            throw new \InvalidArgumentException('未対応のAPIサービスです。');
        }

        $clean = [];
        foreach ($credentials as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }
            $clean[$key] = trim((string)$value);
        }

        $payload = (new CredentialCipher())->encrypt($clean);
        $now = date('Y-m-d H:i:s');
        $pdo = Database::pdo();

        try {
            $pdo->prepare('INSERT INTO api_settings (service, credentials, enabled, updated_at) VALUES (?, ?, 1, ?)')
                ->execute([$service, $payload, $now]);
        } catch (\PDOException) {
            $pdo->prepare('UPDATE api_settings SET credentials = ?, enabled = 1, updated_at = ? WHERE service = ?')
                ->execute([$payload, $now, $service]);
        }
    }

    public function ngWords(): array
    {
        return array_column(Database::pdo()->query('SELECT word FROM ng_words ORDER BY word')->fetchAll(), 'word');
    }

    public function saveNgWords(string $words): void
    {
        $pdo = Database::pdo();
        $items = array_filter(array_unique(array_map('trim', preg_split('/\R/u', $words) ?: [])));

        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM ng_words');
            $statement = $pdo->prepare('INSERT INTO ng_words (word, created_at) VALUES (?, ?)');
            foreach ($items as $word) {
                if (mb_strlen($word) > 190) {
                    continue;
                }
                $statement->execute([$word, date('Y-m-d H:i:s')]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
