<?php

declare(strict_types=1);

namespace App\Services\Setup;

use PDO;
use PDOException;

final class SchemaSetupService
{
    private const CACHE_TTL = 86400;

    public function tableExists(PDO $pdo, string $table): bool
    {
        $dbName = (string) env('DB_NAME', '');
        if ($dbName === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.tables
             WHERE table_schema = :schema_name AND table_name = :table_name'
        );
        $stmt->execute([
            'schema_name' => $dbName,
            'table_name' => $table,
        ]);

        return (int) (($stmt->fetch()['total'] ?? 0)) > 0;
    }

    public function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $dbName = (string) env('DB_NAME', '');
        if ($dbName === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.columns
             WHERE table_schema = :schema_name
               AND table_name = :table_name
               AND column_name = :column_name'
        );
        $stmt->execute([
            'schema_name' => $dbName,
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) (($stmt->fetch()['total'] ?? 0)) > 0;
    }

    public function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $dbName = (string) env('DB_NAME', '');
        if ($dbName === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.statistics
             WHERE table_schema = :schema_name
               AND table_name = :table_name
               AND index_name = :index_name'
        );
        $stmt->execute([
            'schema_name' => $dbName,
            'table_name' => $table,
            'index_name' => $index,
        ]);

        return (int) (($stmt->fetch()['total'] ?? 0)) > 0;
    }

    public function splitSqlStatements(string $sql): array
    {
        $lines = explode("\n", $sql);
        $clean = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '--')) {
                continue;
            }
            $clean[] = $line;
        }

        $joined = implode("\n", $clean);
        $parts = preg_split('/;\s*(?:\r?\n|$)/', $joined) ?: [];
        $statements = [];

        foreach ($parts as $part) {
            $statement = trim($part);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }
}