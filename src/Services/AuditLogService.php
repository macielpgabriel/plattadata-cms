<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

final class AuditLogService
{
    private const TABLE = 'audit_logs';

    public static function log(
        int $userId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null
    ): bool {
        try {
            $db = Database::connection();
            
            $changes = null;
            if ($oldValues && $newValues) {
                $changes = self::buildChangesJson($oldValues, $newValues);
            }

            $stmt = $db->prepare("
                INSERT INTO " . self::TABLE . " 
                (user_id, action, entity_type, entity_id, old_values, new_values, changes, ip_address, created_at)
                VALUES 
                (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :changes, :ip_address, NOW())
            ");

            return $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                'changes' => $changes,
                'ip_address' => $ipAddress ?? self::getClientIp(),
            ]);
        } catch (\Exception $e) {
            Logger::error('Audit log failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function logCreate(int $userId, string $entityType, int $entityId, array $data): bool
    {
        return self::log($userId, 'create', $entityType, $entityId, null, $data);
    }

    public static function logUpdate(int $userId, string $entityType, int $entityId, array $oldData, array $newData): bool
    {
        return self::log($userId, 'update', $entityType, $entityId, $oldData, $newData);
    }

    public static function logDelete(int $userId, string $entityType, int $entityId, array $data): bool
    {
        return self::log($userId, 'delete', $entityType, $entityId, $data, null);
    }

    public static function logLogin(int $userId, bool $success, ?string $reason = null): bool
    {
        return self::log(
            $success ? $userId : 0,
            $success ? 'login_success' : 'login_failed',
            'user',
            $success ? $userId : null,
            null,
            ['reason' => $reason],
            self::getClientIp()
        );
    }

    public static function logAccess(int $userId, string $resource): bool
    {
        return self::log($userId, 'access', 'resource', null, null, ['resource' => $resource]);
    }

    public static function getRecentLogs(int $limit = 50, ?string $action = null, ?int $userId = null): array
    {
        try {
            $db = Database::connection();
            
            $sql = "SELECT * FROM " . self::TABLE . " WHERE 1=1";
            $params = [];

            if ($action) {
                $sql .= " AND action = :action";
                $params['action'] = $action;
            }

            if ($userId) {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = $userId;
            }

            $sql .= " ORDER BY created_at DESC LIMIT :limit";
            $params['limit'] = $limit;

            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, \PDO::PARAM_INT);
            }
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    private static function buildChangesJson(array $old, array $new): string
    {
        $changes = [];
        
        foreach ($new as $key => $value) {
            if (!isset($old[$key]) || $old[$key] !== $value) {
                $changes[$key] = [
                    'old' => $old[$key] ?? null,
                    'new' => $value,
                ];
            }
        }
        
        return json_encode($changes, JSON_UNESCAPED_UNICODE);
    }

    private static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }
        
        return $ip;
    }
}