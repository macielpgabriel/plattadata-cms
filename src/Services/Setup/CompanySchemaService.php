<?php

declare(strict_types=1);

namespace App\Services\Setup;

use PDO;
use PDOException;

final class CompanySchemaService
{
    private SchemaSetupService $schemaService;

    public function __construct()
    {
        $this->schemaService = new SchemaSetupService();
    }

    public function ensureAdvancedCompanySchema(PDO $pdo): void
    {
        $lock = base_path('storage/.schema_v4_completed');
        if (is_file($lock) && $this->schemaService->columnExists($pdo, 'companies', 'query_failures') && $this->schemaService->tableExists($pdo, 'company_enrichments') && $this->schemaService->tableExists($pdo, 'company_source_payloads')) {
            return;
        }

        if (!$this->schemaService->tableExists($pdo, 'companies')) {
            return;
        }

        $columns = [
            'postal_code' => 'ALTER TABLE companies ADD COLUMN postal_code CHAR(8) NULL AFTER cnpj',
            'district' => 'ALTER TABLE companies ADD COLUMN district VARCHAR(120) NULL AFTER postal_code',
            'street' => 'ALTER TABLE companies ADD COLUMN street VARCHAR(120) NULL AFTER district',
            'address_number' => 'ALTER TABLE companies ADD COLUMN address_number VARCHAR(30) NULL AFTER street',
            'opened_at' => 'ALTER TABLE companies MODIFY COLUMN opened_at DATE NULL',
            'address_complement' => 'ALTER TABLE companies ADD COLUMN address_complement VARCHAR(80) NULL AFTER address_number',
            'municipal_ibge_code' => 'ALTER TABLE companies ADD COLUMN municipal_ibge_code INT UNSIGNED NULL AFTER address_complement',
            'cnae_main_code' => 'ALTER TABLE companies ADD COLUMN cnae_main_code CHAR(7) NULL AFTER municipal_ibge_code',
            'legal_nature' => 'ALTER TABLE companies ADD COLUMN legal_nature VARCHAR(120) NULL AFTER cnae_main_code',
            'company_size' => 'ALTER TABLE companies ADD COLUMN company_size VARCHAR(100) NULL AFTER legal_nature',
            'simples_opt_in' => 'ALTER TABLE companies ADD COLUMN simples_opt_in TINYINT(1) NULL AFTER company_size',
            'mei_opt_in' => 'ALTER TABLE companies ADD COLUMN mei_opt_in TINYINT(1) NULL AFTER simples_opt_in',
            'capital_social' => 'ALTER TABLE companies ADD COLUMN capital_social DECIMAL(18,2) NULL AFTER mei_opt_in',
            'source_provider' => 'ALTER TABLE companies ADD COLUMN source_provider VARCHAR(64) NULL AFTER capital_social',
            'revenue' => 'ALTER TABLE companies ADD COLUMN revenue DECIMAL(18,2) NULL AFTER source_provider',
            'last_synced_at' => 'ALTER TABLE companies ADD COLUMN last_synced_at DATETIME NULL AFTER revenue',
            'query_failures' => 'ALTER TABLE companies ADD COLUMN query_failures INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_synced_at',
            'employees_estimate' => 'ALTER TABLE companies ADD COLUMN employees_estimate VARCHAR(50) NULL AFTER query_failures',
            'revenue_estimate' => 'ALTER TABLE companies ADD COLUMN revenue_estimate DECIMAL(18,2) NULL AFTER employees_estimate',
        ];

        foreach ($columns as $column => $sql) {
            if ($this->schemaService->columnExists($pdo, 'companies', $column)) {
                continue;
            }
            try {
                $pdo->exec($sql);
            } catch (PDOException $exception) {
            }
        }

        if (!$this->schemaService->columnExists($pdo, 'companies', 'views')) {
            $pdo->exec("ALTER TABLE companies ADD COLUMN views INT UNSIGNED NOT NULL DEFAULT 0 AFTER query_failures");
        }

        $indexes = [
            'idx_companies_cnpj_unique' => 'CREATE UNIQUE INDEX idx_companies_cnpj_unique ON companies (cnpj)',
            'idx_companies_status_state' => 'CREATE INDEX idx_companies_status_state ON companies (status, state)',
            'idx_companies_city_state' => 'CREATE INDEX idx_companies_city_state ON companies (city, state)',
            'idx_companies_ibge' => 'CREATE INDEX idx_companies_ibge ON companies (municipal_ibge_code)',
            'idx_companies_opened' => 'CREATE INDEX idx_companies_opened ON companies (opened_at)',
            'idx_companies_cnae' => 'CREATE INDEX idx_companies_cnae ON companies (cnae_main_code)',
            'idx_companies_revenue' => 'CREATE INDEX idx_companies_revenue ON companies (revenue)',
        ];

        foreach ($indexes as $index => $sql) {
            if ($this->schemaService->indexExists($pdo, 'companies', $index)) {
                continue;
            }
            try {
                $pdo->exec($sql);
            } catch (PDOException $exception) {
            }
        }

        $userColumns = [
            'two_factor_enabled' => 'ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER role',
            'failed_login_attempts' => 'ALTER TABLE users ADD COLUMN failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_active',
            'locked_until' => 'ALTER TABLE users ADD COLUMN locked_until DATETIME NULL AFTER failed_login_attempts',
            'last_login_at' => 'ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER locked_until',
        ];

        foreach ($userColumns as $column => $sql) {
            if ($this->schemaService->columnExists($pdo, 'users', $column)) {
                continue;
            }
            try {
                $pdo->exec($sql);
            } catch (PDOException $exception) {
            }
        }

        $userIndexes = [
            'idx_users_role_active' => 'CREATE INDEX idx_users_role_active ON users (role, is_active)',
            'idx_users_lock' => 'CREATE INDEX idx_users_lock ON users (locked_until)',
        ];

        foreach ($userIndexes as $index => $sql) {
            if ($this->schemaService->indexExists($pdo, 'users', $index)) {
                continue;
            }
            try {
                $pdo->exec($sql);
            } catch (PDOException $exception) {
            }
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS company_enrichments (
                    company_id BIGINT UNSIGNED PRIMARY KEY,
                    cep_source VARCHAR(32) NULL,
                    ddd VARCHAR(8) NULL,
                    region_name VARCHAR(80) NULL,
                    mesoregion VARCHAR(120) NULL,
                    microregion VARCHAR(120) NULL,
                    geocode_source VARCHAR(64) NULL,
                    latitude DECIMAL(10,7) NULL,
                    longitude DECIMAL(10,7) NULL,
                    ibge_code INT UNSIGNED NULL,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_enrichment_ibge (ibge_code),
                    INDEX idx_enrichment_region (region_name),
                    INDEX idx_enrichment_geo (latitude, longitude),
                    CONSTRAINT fk_enrichment_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (PDOException $exception) {
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS company_source_payloads (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    company_id BIGINT UNSIGNED NULL,
                    cnpj CHAR(14) NOT NULL,
                    provider VARCHAR(64) NOT NULL,
                    request_url VARCHAR(255) NULL,
                    status_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    succeeded TINYINT(1) NOT NULL DEFAULT 0,
                    error_message VARCHAR(255) NULL,
                    response_json LONGTEXT NULL,
                    payload_checksum CHAR(64) NULL,
                    fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_source_payloads_cnpj_fetched (cnpj, fetched_at),
                    INDEX idx_source_payloads_provider_fetched (provider, fetched_at),
                    INDEX idx_source_payloads_status_fetched (succeeded, fetched_at),
                    INDEX idx_source_payloads_checksum (payload_checksum),
                    CONSTRAINT fk_source_payloads_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (PDOException $exception) {
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS company_snapshots (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    company_id BIGINT UNSIGNED NOT NULL,
                    source VARCHAR(32) NOT NULL,
                    raw_data LONGTEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_snapshots_company_created (company_id, created_at),
                    CONSTRAINT fk_snapshots_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (PDOException $exception) {
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS company_query_logs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    company_id BIGINT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    source VARCHAR(32) NOT NULL,
                    ip_address VARCHAR(64) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_logs_company_created (company_id, created_at),
                    INDEX idx_logs_user_created (user_id, created_at),
                    CONSTRAINT fk_logs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (PDOException $exception) {
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS email_logs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    recipient VARCHAR(200) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    body LONGTEXT NOT NULL,
                    enabled TINYINT(1) NOT NULL DEFAULT 0,
                    sent TINYINT(1) NOT NULL DEFAULT 0,
                    error_message VARCHAR(255) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_email_logs_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (PDOException $exception) {
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS lgpd_audit_logs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    company_id BIGINT UNSIGNED NULL,
                    cnpj CHAR(14) NOT NULL,
                    user_id INT UNSIGNED NULL,
                    user_role VARCHAR(20) NOT NULL,
                    action_name VARCHAR(40) NOT NULL,
                    accessed_fields_json LONGTEXT NULL,
                    masking_profile VARCHAR(20) NOT NULL,
                    ip_address VARCHAR(64) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_lgpd_audit_company_created (company_id, created_at),
                    INDEX idx_lgpd_audit_user_created (user_id, created_at),
                    INDEX idx_lgpd_audit_action_created (action_name, created_at),
                    CONSTRAINT fk_lgpd_audit_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
                    CONSTRAINT fk_lgpd_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (PDOException $exception) {
        }

        @file_put_contents($lock, date('c'));
    }
}
