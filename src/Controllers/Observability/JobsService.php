<?php

declare(strict_types=1);

namespace App\Controllers\Observability;

use App\Core\Database;

final class JobsService
{
    public function getAllJobs(): array
    {
        $db = Database::connection();
        $stmt = $db->query("SELECT * FROM jobs ORDER BY created_at DESC LIMIT 100");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function retryJob(int $jobId): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare("UPDATE jobs SET status = 'pending', attempts = 0, error_message = NULL WHERE id = ?");
        return $stmt->execute([$jobId]);
    }

    public function deleteJob(int $jobId): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare("DELETE FROM jobs WHERE id = ?");
        return $stmt->execute([$jobId]);
    }
}