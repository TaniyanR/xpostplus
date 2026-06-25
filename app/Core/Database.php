<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        if ($config['driver'] === 'mysql') {
            $m = $config['mysql'];
            $dsn = "mysql:host={$m['host']};port={$m['port']};dbname={$m['database']};charset={$m['charset']}";
            self::$pdo = new PDO($dsn, $m['username'], $m['password']);
        } else {
            $path = $config['sqlite_path'];
            if (!is_file($path)) {
                touch($path);
            }
            self::$pdo = new PDO('sqlite:' . $path);
        }
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return self::$pdo;
    }
}
