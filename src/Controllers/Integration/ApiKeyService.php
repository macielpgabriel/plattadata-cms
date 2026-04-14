<?php

declare(strict_types=1);

namespace App\Controllers\Integration;

use App\Core\Database;

final class ApiKeyService
{
    public function getApiKeys(): array
    {
        $db = Database::connection();
        $stmt = $db->query("SELECT id, name, api_key, is_active, created_at, expires_at FROM api_keys ORDER BY created_at DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getWebhookSecrets(): array
    {
        $db = Database::connection();
        $stmt = $db->query("SELECT id, name, webhook_secret, is_active, created_at, expires_at FROM api_keys WHERE webhook_secret IS NOT NULL ORDER BY created_at DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function createApiKey(string $name): string
    {
        $db = Database::connection();
        $apiKey = bin2hex(random_bytes(32));
        
        $stmt = $db->prepare("INSERT INTO api_keys (name, api_key, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$name, $apiKey]);
        
        return $apiKey;
    }

    public function createWebhookSecret(string $name): string
    {
        $db = Database::connection();
        $secret = bin2hex(random_bytes(32));
        
        $stmt = $db->prepare("INSERT INTO api_keys (name, webhook_secret, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$name, $secret]);
        
        return $secret;
    }

    public function removeApiKey(int $id): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare("DELETE FROM api_keys WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function removeWebhookSecret(int $id): bool
    {
        return $this->removeApiKey($id);
    }

    public function getRecentAccessLogs(int $limit = 20): array
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT * FROM api_access_logs ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function logAccess(?string $apiKey, string $action, string $resource, ?string $ip = null): void
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("INSERT INTO api_access_logs (api_key, action, resource, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$apiKey, $action, $resource, $ip]);
        } catch (\Throwable $e) {
        }
    }

    public function validateApiKey(?string $key): bool
    {
        if (!$key) return false;
        
        $db = Database::connection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM api_keys WHERE api_key = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$key]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    public function validateWebhookSecret(?string $secret): bool
    {
        if (!$secret) return false;
        
        $db = Database::connection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM api_keys WHERE webhook_secret = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$secret]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    public function ensureTablesExist(): void
    {
        $db = Database::connection();
        
        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS api_keys (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    api_key VARCHAR(255) NULL,
                    webhook_secret VARCHAR(255) NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                    expires_at DATETIME NULL,
                    UNIQUE KEY uk_api_key (api_key),
                    UNIQUE KEY uk_webhook_secret (webhook_secret)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Throwable $e) {
        }

        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS api_access_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    api_key VARCHAR(50) NULL,
                    action VARCHAR(100) NOT NULL,
                    resource VARCHAR(255) NULL,
                    ip_address VARCHAR(45) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_created_at (created_at),
                    INDEX idx_api_key (api_key),
                    INDEX idx_action (action)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Throwable $e) {
        }
    }
}