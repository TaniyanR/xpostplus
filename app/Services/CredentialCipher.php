<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class CredentialCipher
{
    private const PREFIX = 'enc:v1:';

    public function encrypt(array $credentials): string
    {
        $plain = json_encode($credentials, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $key = $this->key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $key);

        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    public function decrypt(string $payload): array
    {
        if ($payload === '') {
            return [];
        }

        if (!str_starts_with($payload, self::PREFIX)) {
            return json_decode($payload, true) ?: [];
        }

        $decoded = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('API設定の復号に失敗しました。');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key());
        if ($plain === false) {
            throw new RuntimeException('API設定の復号に失敗しました。APP_KEYを確認してください。');
        }

        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    }

    private function key(): string
    {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('APIキー暗号化にはPHP Sodium拡張が必要です。');
        }

        $value = (string)(getenv('APP_KEY') ?: '');
        if ($value === '') {
            throw new RuntimeException('APP_KEYが未設定です。32バイト以上のランダム文字列を設定してください。');
        }

        return sodium_crypto_generichash($value, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
