<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class RetentionService
{
    public function runDaily(): void
    {
        if (!(bool) config('app.lgpd.retention.enabled', true)) {
            return;
        }

        if (!(new SetupService())->isDatabaseReady()) {
            return;
        }

        $stampFile = base_path('storage/.retention_last_run');
        $today = date('Y-m-d');

        if (is_file($stampFile)) {
            $last = trim((string) @file_get_contents($stampFile));
            if ($last === $today) {
                return;
            }
        }

        $this->executeRetention();
        @file_put_contents($stampFile, $today);
    }

    private function executeRetention(): void
    {
        $rules = (array) config('app.lgpd.retention.days', []);

        $this->deleteOlderThan('company_query_logs', 'created_at', (int) ($rules['company_query_logs'] ?? 180));
        $this->deleteOlderThan('company_source_payloads', 'fetched_at', (int) ($rules['company_source_payloads'] ?? 180));
        $this->deleteOlderThan('company_snapshots', 'created_at', (int) ($rules['company_snapshots'] ?? 365));
        $this->deleteOlderThan('email_logs', 'created_at', (int) ($rules['email_logs'] ?? 365));
        $this->deleteOlderThan('lgpd_audit_logs', 'created_at', (int) ($rules['lgpd_audit_logs'] ?? 365));
        $this->deleteOlderThan('audit_logs', 'created_at', (int) ($rules['audit_logs'] ?? 730));
    }

    private function deleteOlderThan(string $table, string $dateColumn, int $days): void
    {
        $days = max(1, $days);

        try {
            $sql = 'DELETE FROM ' . $table . ' WHERE ' . $dateColumn . ' < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';
            Database::connection()->exec($sql);
        } catch (Throwable) {
            // Falhas de retenção nao devem quebrar requisicoes.
        }
    }
}
