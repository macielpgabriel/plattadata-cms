<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class FavoriteRepository
{
    public function add(int $userId, int $companyId, ?int $groupId = null): bool
    {
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO user_favorites (user_id, company_id, group_id) VALUES (:user_id, :company_id, :group_id)'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'company_id' => $companyId,
            'group_id' => $groupId,
        ]);
    }

    public function remove(int $userId, int $companyId): bool
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM user_favorites WHERE user_id = :user_id AND company_id = :company_id'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);
    }

    public function isFavorite(int $userId, int $companyId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM user_favorites WHERE user_id = :user_id AND company_id = :company_id LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);

        return (bool) $stmt->fetch();
    }

    public function findByUserId(int $userId, ?int $groupId = null, int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT c.*, f.group_id 
                FROM companies c
                INNER JOIN user_favorites f ON c.id = f.company_id
                WHERE f.user_id = :user_id';
        
        if ($groupId !== null) {
            $sql .= ' AND f.group_id = :group_id';
        } else {
            $sql .= ' AND f.group_id IS NULL';
        }
        
        $sql .= ' ORDER BY f.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        if ($groupId !== null) {
            $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countByUserId(int $userId, ?int $groupId = null): int
    {
        $sql = 'SELECT COUNT(*) as total FROM user_favorites WHERE user_id = :user_id';
        if ($groupId !== null) {
            $sql .= ' AND group_id = :group_id';
        } else {
            $sql .= ' AND group_id IS NULL';
        }
        
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        if ($groupId !== null) {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'group_id' => $groupId]);
        }
        $row = $stmt->fetch();

        return (int) ($row['total'] ?? 0);
    }

    public function getGroups(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT g.*, COUNT(f.id) as company_count 
             FROM favorite_groups g 
             LEFT JOIN user_favorites f ON g.id = f.group_id 
             WHERE g.user_id = :user_id 
             GROUP BY g.id 
             ORDER BY g.name'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function createGroup(int $userId, string $name, string $color = 'primary'): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO favorite_groups (user_id, name, color) VALUES (:user_id, :name, :color)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'color' => $color,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function updateGroup(int $groupId, int $userId, string $name, string $color): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE favorite_groups SET name = :name, color = :color WHERE id = :id AND user_id = :user_id'
        );

        return $stmt->execute([
            'id' => $groupId,
            'user_id' => $userId,
            'name' => $name,
            'color' => $color,
        ]);
    }

    public function deleteGroup(int $groupId, int $userId): bool
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE user_favorites SET group_id = NULL WHERE group_id = :group_id AND user_id = :user_id');
            $stmt->execute(['group_id' => $groupId, 'user_id' => $userId]);
            
            $stmt = $pdo->prepare('DELETE FROM favorite_groups WHERE id = :id AND user_id = :user_id');
            $stmt->execute(['id' => $groupId, 'user_id' => $userId]);
            
            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public function moveToGroup(int $userId, int $companyId, ?int $groupId): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE user_favorites SET group_id = :group_id WHERE user_id = :user_id AND company_id = :company_id'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'company_id' => $companyId,
            'group_id' => $groupId,
        ]);
    }
}
