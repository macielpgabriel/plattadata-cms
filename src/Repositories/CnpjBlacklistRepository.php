<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Cache;

final class CnpjBlacklistRepository
{
    private const CACHE_KEY_PREFIX = 'cnpj_blacklist_';
    private const CACHE_TTL = 86400;

    public function isBlacklisted(string $cnpj): bool
    {
        $cnpj = preg_replace('/[^A-Za-z0-9]/', '', $cnpj);

        $cached = Cache::get(self::CACHE_KEY_PREFIX . $cnpj);
        if ($cached !== null) {
            return $cached;
        }

        $stmt = Database::connection()->prepare(
            "SELECT id FROM cnpj_blacklist WHERE cnpj = :cnpj AND status = 'approved' LIMIT 1"
        );
        $stmt->execute(['cnpj' => $cnpj]);
        $result = $stmt->fetch();

        $isBlacklisted = $result !== false;
        Cache::set(self::CACHE_KEY_PREFIX . $cnpj, $isBlacklisted, self::CACHE_TTL);

        return $isBlacklisted;
    }

    public function add(string $cnpj, ?string $reason = null, ?int $requestedBy = null): int
    {
        $cnpj = preg_replace('/[^A-Za-z0-9]/', '', $cnpj);

        $stmt = Database::connection()->prepare(
            "INSERT INTO cnpj_blacklist (cnpj, reason, requested_by, status) 
             VALUES (:cnpj, :reason, :requested_by, 'pending')
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), updated_at = NOW()"
        );
        $stmt->execute([
            'cnpj' => $cnpj,
            'reason' => $reason,
            'requested_by' => $requestedBy,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function approve(int $id, int $approvedBy): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE cnpj_blacklist 
             SET status = 'approved', approved_by = :approved_by, processed_at = NOW(), updated_at = NOW() 
             WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute([
            'id' => $id,
            'approved_by' => $approvedBy,
        ]);

        $this->refreshCache($id);
    }

    public function reject(int $id, int $approvedBy, ?string $notes = null): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE cnpj_blacklist 
             SET status = 'rejected', approved_by = :approved_by, notes = :notes, processed_at = NOW(), updated_at = NOW() 
             WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute([
            'id' => $id,
            'approved_by' => $approvedBy,
            'notes' => $notes,
        ]);
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT bl.*, u1.name as requested_by_name, u2.name as approved_by_name
             FROM cnpj_blacklist bl
             LEFT JOIN users u1 ON u1.id = bl.requested_by
             LEFT JOIN users u2 ON u2.id = bl.approved_by
             WHERE bl.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findPending(): array
    {
        $stmt = Database::connection()->query(
            "SELECT bl.*, u1.name as requested_by_name
             FROM cnpj_blacklist bl
             LEFT JOIN users u1 ON u1.id = bl.requested_by
             WHERE bl.status = 'pending'
             ORDER BY bl.created_at DESC"
        );

        return $stmt->fetchAll();
    }

    public function paginate(int $page = 1, int $perPage = 20, ?string $status = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = ['limit' => $perPage, 'offset' => $offset];

        $where = '';
        if ($status !== null) {
            $where = 'WHERE bl.status = :status';
            $params['status'] = $status;
        }

        $countSql = "SELECT COUNT(*) as total FROM cnpj_blacklist bl $where";
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($status !== null ? ['status' => $status] : []);
        $total = (int) $countStmt->fetch()['total'];

        $sql = "SELECT bl.*, u1.name as requested_by_name, u2.name as approved_by_name
                FROM cnpj_blacklist bl
                LEFT JOIN users u1 ON u1.id = bl.requested_by
                LEFT JOIN users u2 ON u2.id = bl.approved_by
                $where
                ORDER BY bl.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        if ($status !== null) {
            $stmt->bindValue(':status', $status);
        }
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public function removeFromBlacklist(int $id): void
    {
        $entry = $this->findById($id);
        if ($entry) {
            Cache::forget(self::CACHE_KEY_PREFIX . $entry['cnpj']);
        }

        $stmt = Database::connection()->prepare('DELETE FROM cnpj_blacklist WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function refreshCache(int $id): void
    {
        $entry = $this->findById($id);
        if ($entry) {
            $isBlacklisted = $entry['status'] === 'approved';
            Cache::set(self::CACHE_KEY_PREFIX . $entry['cnpj'], $isBlacklisted, self::CACHE_TTL);
        }
    }

    public function getBlacklistedCnpjs(): array
    {
        $stmt = Database::connection()->query(
            "SELECT cnpj FROM cnpj_blacklist WHERE status = 'approved'"
        );
        $results = $stmt->fetchAll();

        return array_column($results, 'cnpj');
    }
}
