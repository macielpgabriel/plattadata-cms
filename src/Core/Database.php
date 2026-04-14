<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;
    private static ?string $lastDsn = null;

    public static function connection(): PDO
    {
        $host = config('database.host');
        $port = config('database.port');
        $name = config('database.name');
        $user = config('database.user');
        $pass = config('database.pass');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        if (self::$pdo !== null && self::$lastDsn === $dsn) {
            return self::$pdo;
        }

        self::$pdo = null;

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$lastDsn = $dsn;
        } catch (PDOException $exception) {
            self::logConnectionError($exception->getMessage(), [
                'host' => (string) $host,
                'port' => (string) $port,
                'database' => (string) $name,
                'user' => (string) $user,
            ]);

            throw new \RuntimeException('Database connection failed: ' . $exception->getMessage(), (int) $exception->getCode(), $exception);
        }


        return self::$pdo;
    }

    private static function logConnectionError(string $message, array $context): void
    {
        $logDir = base_path('storage/logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $cacheFile = $logDir . '/.connection_error_logged';
        $now = time();
        
        if (file_exists($cacheFile)) {
            $lastLogged = (int) file_get_contents($cacheFile);
            if ($now - $lastLogged < 3600) {
                return;
            }
        }
        
        $line = sprintf(
            "[%s] DB connection error: %s | host=%s port=%s db=%s user=%s\n",
            date('c'),
            $message,
            $context['host'] ?? '',
            $context['port'] ?? '',
            $context['database'] ?? '',
            $context['user'] ?? ''
        );

        @file_put_contents($logDir . '/app.log', $line, FILE_APPEND);
        @file_put_contents($cacheFile, (string) $now);
    }
}
