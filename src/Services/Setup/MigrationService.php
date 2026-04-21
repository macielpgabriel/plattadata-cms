<?php

declare(strict_types=1);

namespace App\Services\Setup;

use App\Core\Logger;
use App\Services\CnpjAlphanumericValidator;
use PDO;
use PDOException;

final class MigrationService
{
    public function applyAllMigrations(PDO $pdo): array
    {
        $results = [
            'cnpj_alphanumeric' => $this->applyCnpjAlphanumericMigration($pdo),
        ];
        
        return $results;
    }

    public function applyCnpjAlphanumericMigration(PDO $pdo): bool
    {
        try {
            $checkStmt = $pdo->query("DESCRIBE companies cnpj");
            $columnInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($columnInfo && str_starts_with($columnInfo['Type'], 'char(14)')) {
                Logger::info('Migration: Aplicando CNPJ Alfanumérico...');
                
                $alterStatements = [
                    "ALTER TABLE companies MODIFY cnpj VARCHAR(14) NOT NULL UNIQUE",
                    "ALTER TABLE company_partners MODIFY cnpj VARCHAR(14) NULL",
                    "ALTER TABLE company_partners MODIFY partner_cnpj VARCHAR(14) NULL",
                    "ALTER TABLE company_enrichments MODIFY cnpj VARCHAR(14) NOT NULL",
                    "ALTER TABLE company_source_payloads MODIFY cnpj VARCHAR(14) NOT NULL",
                    "ALTER TABLE company_query_logs MODIFY cnpj VARCHAR(14) NOT NULL",
                    "ALTER TABLE company_changes MODIFY cnpj VARCHAR(14) NOT NULL",
                    "ALTER TABLE company_removal_requests MODIFY cnpj VARCHAR(14) NOT NULL",
                    "ALTER TABLE company_competitors MODIFY competitor_cnpj VARCHAR(14) NOT NULL",
                    "ALTER TABLE company_mentions MODIFY cnpj VARCHAR(14) NOT NULL",
                    "ALTER TABLE company_mentions_history MODIFY cnpj VARCHAR(14) NOT NULL",
                    "ALTER TABLE favorite_groups MODIFY entity_cnpj VARCHAR(14) NULL",
                    "ALTER TABLE user_favorites MODIFY entity_cnpj VARCHAR(14) NULL",
                    "ALTER TABLE cnpj_blacklist MODIFY cnpj VARCHAR(14) NOT NULL UNIQUE",
                    "ALTER TABLE api_access_logs MODIFY cnpj VARCHAR(14) NULL",
                ];
                
                $executed = 0;
                foreach ($alterStatements as $sql) {
                    try {
                        $pdo->exec($sql);
                        $executed++;
                    } catch (PDOException $e) {
                        Logger::warning('Migration: ALTER ignorado: ' . $e->getMessage());
                    }
                }
                
                Logger::info("Migration: CNPJ Alfanumérico concluído ({$executed} alters)");
                return true;
            }
            
            Logger::info('Migration: CNPJ Alfanumérico já aplicado ou não necessário');
            return true;
        } catch (PDOException $e) {
            Logger::warning('Migration: CNPJ error: ' . $e->getMessage());
            return false;
        }
    }
    
    public function applyColumnIfNotExists(PDO $pdo, string $table, string $column, string $definition): bool
    {
        try {
            if (!$this->columnExists($pdo, $table, $column)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
                Logger::info("Migration: Coluna $table.$column adicionada");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            Logger::warning("Migration: Erro ao adicionar $column: " . $e->getMessage());
            return false;
        }
    }
    
    public function applyIndexIfNotExists(PDO $pdo, string $table, string $indexName, string $definition): bool
    {
        try {
            if (!$this->indexExists($pdo, $table, $indexName)) {
                $pdo->exec("CREATE INDEX $indexName ON $table ($definition)");
                Logger::info("Migration: Índice $indexName criado");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            Logger::warning("Migration: Erro ao criar índice: " . $e->getMessage());
            return false;
        }
    }
    
    public function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            return (bool) $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->query("DESCRIBE $table $column");
            return (bool) $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function indexExists(PDO $pdo, string $table, string $indexName): bool
    {
        try {
            $stmt = $pdo->query("SHOW INDEX FROM $table WHERE Key_name = '$indexName'");
            return (bool) $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
}