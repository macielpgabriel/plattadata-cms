<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;
use PDO;
use PDOException;

final class SafeDatabase
{
    public static function query(string $sql, array $params = [], $default = null)
    {
        try {
            $db = self::connection();
            if (!$db) {
                return $default;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?? $default;
        } catch (PDOException $e) {
            Logger::error('SafeDatabase Query Error: ' . $e->getMessage());
            return $default;
        }
    }
    
    public static function queryOne(string $sql, array $params = [], $default = null)
    {
        try {
            $db = self::connection();
            if (!$db) {
                return $default;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch() ?? $default;
        } catch (PDOException $e) {
            Logger::error('SafeDatabase QueryOne Error: ' . $e->getMessage());
            return $default;
        }
    }
    
    public static function execute(string $sql, array $params = [], bool $default = false): bool
    {
        try {
            $db = self::connection();
            if (!$db) {
                return $default;
            }
            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            Logger::error('SafeDatabase Execute Error: ' . $e->getMessage());
            return $default;
        }
    }
    
    public static function tableExists(string $table): bool
    {
        try {
            $db = self::connection();
            if (!$db) {
                return false;
            }
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return (bool) $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public static function columnExists(string $table, string $column): bool
    {
        try {
            $db = self::connection();
            if (!$db) {
                return false;
            }
            $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
            $stmt->execute([$column]);
            return (bool) $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    private static function connection()
    {
        try {
            return Database::connection();
        } catch (Throwable $e) {
            return null;
        }
    }
}