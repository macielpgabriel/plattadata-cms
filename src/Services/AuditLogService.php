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
        if (empty($userId) || empty($entityId)) {
            return false;
        }
        return self::log($userId, 'create', $entityType, $entityId, null, $data);
    }

    public static function logUpdate(int $userId, string $entityType, int $entityId, array $oldData, array $newData): bool
    {
        if (empty($userId) || empty($entityId)) {
            return false;
        }
        return self::log($userId, 'update', $entityType, $entityId, $oldData, $newData);
    }

    public static function logDelete(int $userId, string $entityType, int $entityId, array $data): bool
    {
        if (empty($userId) || empty($entityId)) {
            return false;
        }
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

    public static function getRecentLogs(int $limit = 50, ?string $action = null, ?int $userId = null, ?string $entityType = null, ?string $startDate = null, ?string $endDate = null, ?string $search = null): array
    {
        try {
            $db = Database::connection();

            $sql = "
                SELECT al.*, u.name as user_name, u.email as user_email
                FROM " . self::TABLE . " al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1";
            $params = [];

            if ($action) {
                $sql .= " AND al.action = :action";
                $params['action'] = $action;
            }

            if ($userId) {
                $sql .= " AND al.user_id = :user_id";
                $params['user_id'] = $userId;
            }

            if ($entityType) {
                $sql .= " AND al.entity_type = :entity_type";
                $params['entity_type'] = $entityType;
            }

            if ($startDate) {
                $sql .= " AND al.created_at >= :start_date";
                $params['start_date'] = $startDate . ' 00:00:00';
            }

            if ($endDate) {
                $sql .= " AND al.created_at <= :end_date";
                $params['end_date'] = $endDate . ' 23:59:59';
            }

            if ($search) {
                $sql .= " AND (al.old_values LIKE :search OR al.new_values LIKE :search OR al.changes LIKE :search)";
                $params['search'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY al.created_at DESC LIMIT :limit";
            $params['limit'] = $limit;

            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, \PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function countLogs(?string $action = null, ?int $userId = null, ?string $entityType = null, ?string $startDate = null, ?string $endDate = null, ?string $search = null): int
    {
        try {
            $db = Database::connection();

            $sql = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE 1=1";
            $params = [];

            if ($action) {
                $sql .= " AND action = :action";
                $params['action'] = $action;
            }

            if ($userId) {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = $userId;
            }

            if ($entityType) {
                $sql .= " AND entity_type = :entity_type";
                $params['entity_type'] = $entityType;
            }

            if ($startDate) {
                $sql .= " AND created_at >= :start_date";
                $params['start_date'] = $startDate . ' 00:00:00';
            }

            if ($endDate) {
                $sql .= " AND created_at <= :end_date";
                $params['end_date'] = $endDate . ' 23:59:59';
            }

            if ($search) {
                $sql .= " AND (old_values LIKE :search OR new_values LIKE :search OR changes LIKE :search)";
                $params['search'] = '%' . $search . '%';
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function getUsersWithActivity(): array
    {
        try {
            $db = Database::connection();
            $stmt = $db->query("
                SELECT DISTINCT u.id, u.name, u.email
                FROM " . self::TABLE . " al
                JOIN users u ON al.user_id = u.id
                ORDER BY u.name
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getDistinctActions(): array
    {
        try {
            $db = Database::connection();
            $stmt = $db->query("SELECT DISTINCT action FROM " . self::TABLE . " ORDER BY action");
            return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'action');
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getDistinctEntityTypes(): array
    {
        try {
            $db = Database::connection();
            $stmt = $db->query("SELECT DISTINCT entity_type FROM " . self::TABLE . " ORDER BY entity_type");
            return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'entity_type');
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