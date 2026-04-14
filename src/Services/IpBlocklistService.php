<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

final class IpBlocklistService
{
    public static function isBlocked(string $ip): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("SELECT 1 FROM blocked_ips WHERE ip = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
            $stmt->execute([$ip]);
            return (bool) $stmt->fetch();
        } catch (Throwable $e) {
            Logger::error('IpBlocklist check error: ' . $e->getMessage());
            return false;
        }
    }

    public static function block(string $ip, string $reason, ?int $minutes = null): bool
    {
        try {
            $db = Database::connection();
            $expiresAt = $minutes ? date('Y-m-d H:i:s', time() + ($minutes * 60)) : null;

            $stmt = $db->prepare("
                INSERT INTO blocked_ips (ip, reason, created_at, expires_at) 
                VALUES (?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE 
                    reason = VALUES(reason),
                    created_at = NOW(),
                    expires_at = VALUES(expires_at)
            ");
            $result = $stmt->execute([$ip, $reason, $expiresAt]);

            Logger::warning('IP bloqueado: ' . $ip . ' - Motivo: ' . $reason);

            return $result;
        } catch (Throwable $e) {
            Logger::error('IpBlocklist block error: ' . $e->getMessage());
            return false;
        }
    }

    public static function unblock(string $ip): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("DELETE FROM blocked_ips WHERE ip = ?");
            return $stmt->execute([$ip]);
        } catch (Throwable $e) {
            Logger::error('IpBlocklist unblock error: ' . $e->getMessage());
            return false;
        }
    }

    public static function recordFailedAttempt(string $ip): int
    {
        try {
            $db = Database::connection();
            
            $stmt = $db->prepare("SELECT attempts, last_attempt FROM ip_failed_attempts WHERE ip = ?");
            $stmt->execute([$ip]);
            $row = $stmt->fetch();

            $attempts = ($row ? (int) $row['attempts'] : 0) + 1;
            $maxAttempts = (int) config('app.security.max_login_attempts', 5);

            if ($row) {
                $stmt = $db->prepare("UPDATE ip_failed_attempts SET attempts = ?, last_attempt = NOW() WHERE ip = ?");
                $stmt->execute([$attempts, $ip]);
            } else {
                $stmt = $db->prepare("INSERT INTO ip_failed_attempts (ip, attempts, last_attempt) VALUES (?, 1, NOW())");
                $stmt->execute([$ip]);
            }

            if ($attempts >= $maxAttempts) {
                self::block($ip, "Bloqueio automatico apos {$attempts} tentativas falhadas de login", 60);
                self::clearFailedAttempts($ip);
                Logger::warning("IP {$ip} bloqueado automaticamente apos {$attempts} tentativas", ['attempts' => $attempts]);
            }

            return $attempts;
        } catch (Throwable $e) {
            Logger::error('IpBlocklist recordFailedAttempt error: ' . $e->getMessage());
            return 0;
        }
    }

    public static function clearFailedAttempts(string $ip): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("DELETE FROM ip_failed_attempts WHERE ip = ?");
            return $stmt->execute([$ip]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function cleanup(): int
    {
        try {
            $db = Database::connection();
            
            $stmt = $db->prepare("DELETE FROM blocked_ips WHERE expires_at IS NOT NULL AND expires_at < NOW()");
            $stmt->execute();
            
            $stmt = $db->prepare("DELETE FROM ip_failed_attempts WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute();

            return $stmt->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function getBlockedList(): array
    {
        try {
            $db = Database::connection();
            $stmt = $db->query("SELECT ip, reason, created_at, expires_at FROM blocked_ips WHERE expires_at IS NULL OR expires_at > NOW() ORDER BY created_at DESC");
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function ensureTables(): bool
    {
        try {
            $db = Database::connection();

            $db->exec("
                CREATE TABLE IF NOT EXISTS blocked_ips (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    ip VARCHAR(45) NOT NULL UNIQUE,
                    reason VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NULL,
                    INDEX idx_blocked_ip (ip),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS ip_failed_attempts (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    ip VARCHAR(45) NOT NULL UNIQUE,
                    attempts INT UNSIGNED NOT NULL DEFAULT 1,
                    last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ip_attempts (ip, attempts)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            return true;
        } catch (Throwable $e) {
            Logger::error('IpBlocklist ensureTables error: ' . $e->getMessage());
            return false;
        }
    }
}
