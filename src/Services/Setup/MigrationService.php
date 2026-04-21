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
            'company_edit_requests' => $this->applyCompanyEditRequestsTable($pdo),
            'company_profile_fields' => $this->applyCompanyProfileFields($pdo),
            'company_reviews' => $this->applyCompanyReviews($pdo),
        ];
        
        return $results;
    }

    private function applyCompanyEditRequestsTable(PDO $pdo): bool
    {
        try {
            if (!$this->tableExists($pdo, 'company_edit_requests')) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS company_edit_requests (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    company_id BIGINT UNSIGNED NOT NULL,
                    cnpj VARCHAR(14) NOT NULL,
                    requester_name VARCHAR(120) NOT NULL,
                    requester_email VARCHAR(160) NOT NULL,
                    verification_type ENUM('email', 'document') NOT NULL,
                    verification_code CHAR(6) NULL,
                    document_path VARCHAR(255) NULL,
                    status ENUM('pending', 'verified', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
                    verified_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_edit_cnpj (cnpj),
                    INDEX idx_edit_status (status),
                    CONSTRAINT fk_edit_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                Logger::info('Migration: Tabela company_edit_requests criada');
            }
            return true;
        } catch (PDOException $e) {
            Logger::warning('Migration: company_edit_requests: ' . $e->getMessage());
            return false;
        }
    }

    private function applyCompanyProfileFields(PDO $pdo): bool
    {
        try {
            $this->applyColumnIfNotExists($pdo, 'companies', 'description', 'TEXT NULL');
            $this->applyColumnIfNotExists($pdo, 'companies', 'facebook', 'VARCHAR(255) NULL');
            $this->applyColumnIfNotExists($pdo, 'companies', 'instagram', 'VARCHAR(50) NULL');
            $this->applyColumnIfNotExists($pdo, 'companies', 'linkedin', 'VARCHAR(255) NULL');
            $this->applyColumnIfNotExists($pdo, 'companies', 'whatsapp', 'VARCHAR(20) NULL');
            return true;
        } catch (PDOException $e) {
            Logger::warning('Migration: company_profile: ' . $e->getMessage());
            return false;
        }
    }

    private function applyCompanyReviews(PDO $pdo): bool
    {
        try {
            if (!$this->tableExists($pdo, 'company_reviews')) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS company_reviews (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    company_id BIGINT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    rating TINYINT UNSIGNED NOT NULL,
                    comment TEXT NULL,
                    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                    reply TEXT NULL,
                    reply_at DATETIME NULL,
                    reports_count INT UNSIGNED NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_review_company (company_id),
                    INDEX idx_review_user (user_id),
                    INDEX idx_review_status (status),
                    INDEX idx_review_created (created_at),
                    CONSTRAINT fk_review_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                    CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                Logger::info('Migration: Tabela company_reviews criada');
            }
            return true;
        } catch (PDOException $e) {
            Logger::warning('Migration: company_reviews: ' . $e->getMessage());
            return false;
        }
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