<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use PDO;
use PDOException;
use App\Services\Setup\DatabaseSetupService;
use App\Services\Setup\SchemaSetupService;
use App\Services\Setup\CompanySchemaService;

final class SetupService
{
    private ?string $lastConnectionError = null;
    private DatabaseSetupService $databaseService;
    private SchemaSetupService $schemaService;
    private CompanySchemaService $companySchemaService;

    public function __construct()
    {
        $this->databaseService = new DatabaseSetupService();
        $this->schemaService = new SchemaSetupService();
        $this->companySchemaService = new CompanySchemaService();
    }

    private function execWithErrorLogging(PDO $pdo, string $sql, ?string $context = null): void
    {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $msg = $context ? "Setup ({$context}): " : 'Setup: ';
            Logger::warning($msg . $e->getMessage());
        }
    }

    private function alterWithErrorLogging(PDO $pdo, string $sql, ?string $context = null): void
    {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $msg = $context ? "Setup ({$context}): " : 'Setup: ';
            Logger::warning($msg . $e->getMessage());
        }
    }

    public function isDatabaseReady(): bool
    {
        return $this->databaseService->isDatabaseReady();
    }

    public function hasCriticalTables(): bool
    {
        $pdo = $this->databaseService->connectApplicationDatabase();
        if ($pdo === null) {
            return false;
        }

        $criticalTables = ['users', 'companies', 'site_settings'];
        foreach ($criticalTables as $tableName) {
            if (!$this->schemaService->tableExists($pdo, $tableName)) {
                return false;
            }
        }

        return true;
    }

    public function getLastConnectionError(): ?string
    {
        return $this->lastConnectionError;
    }

     public function runInitialSetup(): void
    {
        $this->databaseService->createDatabaseAndUserIfConfigured();
        $pdo = $this->databaseService->connectApplicationDatabase();
        if ($pdo === null) {
            return;
        }

        $this->ensureCompaniesTable($pdo);
        $this->ensureUsersTable($pdo);
        $this->ensureSiteSettingsTable($pdo);
        $this->ensureDefaultSiteSettings($pdo);
        $this->companySchemaService->ensureAdvancedCompanySchema($pdo);
        $this->ensureLocationAndTaxSchema($pdo);
        $this->ensureGdpSectorsSchema($pdo);
        $this->ensureRemovalSystemSchema($pdo);
        $this->ensureFavoritesSchema($pdo);
        $this->ensureQsaAndCnaeSchema($pdo);
        $this->ensureQueueSchema($pdo);
        $this->ensureBlacklistSchema($pdo);
        $this->ensureNotificationsSchema($pdo);
        $this->ensurePasswordResetSchema($pdo);
        $this->ensureUserExtensionsSchema($pdo);
        $this->ensureEmailVerificationSchema($pdo);
        $this->ensureImpostometroArrecadacaoSchema($pdo);
        $this->ensureSearchOptimizationSchema($pdo);
        $this->ensureRemovalCancelledStatus($pdo);
        $this->ensureIpBlocklistSchema($pdo);
        $this->ensureEnrichmentSchema($pdo);
        $this->ensureCacheTables($pdo);
        $this->ensureFavoriteGroupsSchema($pdo);
        $this->ensureApiKeysSchema($pdo);
        $this->ensureExtendedDataSchema($pdo);
        $this->ensureMentionAlertsSchema($pdo);

        // AGORA verifica o lock - apenas após criar todas as tabelas críticas
        if ($this->isSetupLocked()) {
            return;
        }

        $this->runSchemaIfNeeded($pdo);
        $this->ensureSiteSettingsTable($pdo);
        $this->ensureDefaultSiteSettings($pdo);
        $this->companySchemaService->ensureAdvancedCompanySchema($pdo);
        $this->ensureLocationAndTaxSchema($pdo);
        $this->ensureGdpSectorsSchema($pdo);
        $this->ensureRemovalSystemSchema($pdo);
        $this->ensureFavoritesSchema($pdo);
        $this->ensureQsaAndCnaeSchema($pdo);
        $this->ensureQueueSchema($pdo);
        $this->ensureEmailVerificationSchema($pdo);
        $this->ensureBlacklistSchema($pdo);
        $this->ensureNotificationsSchema($pdo);
        $this->ensurePasswordResetSchema($pdo);
        $this->ensureUserExtensionsSchema($pdo);
        $this->ensureImpostometroArrecadacaoSchema($pdo);
        $this->ensureSearchOptimizationSchema($pdo);
        $this->ensureRemovalCancelledStatus($pdo);
        $this->ensureVehicleFleetTypesSchema($pdo);
        $this->ensureInitialAdminInDatabase($pdo);

        // Valida que tabelas críticas foram criadas
        $this->validateCriticalTables($pdo);

        $this->writeSetupLock();
    }

    public function ensureInitialAdmin(): void
    {
        $pdo = $this->databaseService->connectApplicationDatabase();
        if ($pdo === null) {
            return;
        }

        $this->runSchemaIfNeeded($pdo);
        $this->ensureInitialAdminInDatabase($pdo);
    }

    public function saveInstallerConfig(array $config): bool
    {
        $envPath = base_path('.env');
        if (!is_file($envPath) || !is_writable($envPath)) {
            $this->log('Nao foi possivel escrever no .env (arquivo ausente ou sem permissao).');
            return false;
        }

        $current = (string) file_get_contents($envPath);
        $lines = explode("\n", $current);
        $replacements = [
            'APP_URL' => (string) ($config['app_url'] ?? env('APP_URL', 'https://plattadata.com')),
            'DB_HOST' => (string) ($config['db_host'] ?? env('DB_HOST', 'localhost')),
            'DB_PORT' => (string) ($config['db_port'] ?? env('DB_PORT', '3306')),
            'DB_NAME' => (string) ($config['db_name'] ?? env('DB_NAME', '')),
            'DB_USER' => (string) ($config['db_user'] ?? env('DB_USER', '')),
            'DB_PASS' => (string) ($config['db_pass'] ?? env('DB_PASS', '')),
            'CMS_ADMIN_NAME' => (string) ($config['admin_name'] ?? env('CMS_ADMIN_NAME', 'Administrador')),
            'CMS_ADMIN_EMAIL' => (string) ($config['admin_email'] ?? env('CMS_ADMIN_EMAIL', 'admin@plattadata.com')),
            'CMS_ADMIN_PASSWORD' => (string) ($config['admin_password'] ?? env('CMS_ADMIN_PASSWORD', 'Plattadata#2026!')),
        ];

        foreach ($replacements as $key => $value) {
            $rawValue = (string) $value;
            $value = $this->formatEnvValue($rawValue);
            $updated = false;

            foreach ($lines as $index => $line) {
                if (str_starts_with($line, $key . '=')) {
                    $lines[$index] = $key . '=' . $value;
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                $lines[] = $key . '=' . $value;
            }

            $_ENV[$key] = $rawValue;
            $_SERVER[$key] = $rawValue;
        }

        $saved = file_put_contents($envPath, implode("\n", $lines) . "\n");
        if ($saved === false) {
            $this->log('Falha ao persistir configuracoes do instalador no .env.');
            return false;
        }

        $this->clearSetupLock();
        $this->log('Configuracoes salvas via instalador web.');
        return true;
    }

    private function runSchemaIfNeeded(PDO $pdo): void
    {
        // Verifica se a tabela 'companies' já existe
        $companiesExists = false;
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'companies'");
            $companiesExists = (bool) $stmt->fetch();
            if ($companiesExists) {
                Logger::info('Setup: Tabela companies já existe, pulando execução de schema.sql');
                return;
            }
        } catch (PDOException $exception) {
            Logger::warning('Setup: Erro ao verificar existência de tabela companies: ' . $exception->getMessage());
            // Continua mesmo com erro na verificação
        }

        // Valida que o arquivo schema.sql existe
        $schemaFile = base_path('database/schema.sql');
        if (!is_file($schemaFile)) {
            Logger::error('Setup: Arquivo database/schema.sql não encontrado em: ' . $schemaFile);
            return;
        }

        // Lê o arquivo
        $sql = (string) file_get_contents($schemaFile);
        if (empty($sql)) {
            Logger::error('Setup: Arquivo database/schema.sql está vazio');
            return;
        }

        Logger::info('Setup: Iniciando execução de database/schema.sql (tabela companies não existe)');

        // Divide em statements individuais
        $statements = $this->schemaService->splitSqlStatements($sql);
        if (empty($statements)) {
            Logger::warning('Setup: Nenhum statement SQL encontrado em schema.sql');
            return;
        }

        Logger::info('Setup: Encontrados ' . count($statements) . ' statements SQL para executar');

        // Executa cada statement
        $executedCount = 0;
        $failedCount = 0;
        foreach ($statements as $index => $statement) {
            if (trim($statement) === '') {
                continue;
            }

            try {
                $pdo->exec($statement);
                $executedCount++;
            } catch (PDOException $exception) {
                $failedCount++;
                Logger::warning('Setup: Falha ao executar statement SQL #' . ($index + 1) . ': ' . $exception->getMessage());
                // Continua executando os demais statements
            }
        }

        Logger::info('Setup: Execução de schema.sql concluída. Executados: ' . $executedCount . ', Falhados: ' . $failedCount);

        // Valida que a tabela companies foi criada
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'companies'");
            $companiesNowExists = (bool) $stmt->fetch();
            if (!$companiesNowExists) {
                Logger::error('Setup: CRÍTICO - Tabela companies não foi criada após execução de schema.sql');
            } else {
                Logger::info('Setup: Tabela companies criada com sucesso');
            }
        } catch (PDOException $exception) {
            Logger::error('Setup: Erro ao validar criação de tabela companies: ' . $exception->getMessage());
        }
    }

    private function ensureInitialAdminInDatabase(PDO $pdo): void
    {
        try {
            $stmt = $pdo->query('SELECT COUNT(*) AS total FROM users');
            $count = (int) (($stmt->fetch()['total'] ?? 0));
            if ($count > 0) {
                return;
            }

            $adminName = (string) env('CMS_ADMIN_NAME', 'Administrador');
            $adminEmail = (string) env('CMS_ADMIN_EMAIL', 'admin@local.test');
            $adminPassword = (string) env('CMS_ADMIN_PASSWORD', 'Plattadata#2026!');

            $passwordError = (new PasswordPolicyService())->validate($adminPassword);
            if ($passwordError !== null) {
            }

            $hash = password_hash($adminPassword, PASSWORD_ARGON2ID);

            $insert = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, two_factor_enabled, is_active, email_verified_at) VALUES (:name, :email, :password_hash, :role, :two_factor_enabled, :is_active, NOW())'
            );
            $insert->execute([
                'name' => $adminName,
                'email' => strtolower(trim($adminEmail)),
                'password_hash' => $hash,
                'role' => 'admin',
                'two_factor_enabled' => 1,
                'is_active' => 1,
            ]);
        } catch (PDOException $exception) {
            Logger::warning('Setup: ' . $exception->getMessage());
        }
    }

    private function ensureUsersTable(PDO $pdo): void
    {
        if ($this->schemaService->tableExists($pdo, 'users')) {
            return;
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS users (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(120) NOT NULL,
                    email VARCHAR(160) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    role ENUM(\'admin\', \'moderator\', \'editor\', \'viewer\') NOT NULL DEFAULT \'viewer\',
                    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
                    notification_preferences JSON NULL,
                    failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    locked_until DATETIME NULL,
                    last_login_at DATETIME NULL,
                    email_verified_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_users_role_active (role, is_active),
                    INDEX idx_users_lock (locked_until),
                    INDEX idx_users_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            Logger::info('Setup: Tabela users criada com sucesso');
        } catch (PDOException $exception) {
            Logger::warning('Setup: Falha ao criar tabela users: ' . $exception->getMessage());
        }
    }

    private function ensureCompaniesTable(PDO $pdo): void
    {
        if ($this->schemaService->tableExists($pdo, 'companies')) {
            return;
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS companies (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    cnpj VARCHAR(14) NOT NULL UNIQUE,
                    legal_name VARCHAR(255) NOT NULL,
                    trade_name VARCHAR(255) NULL,
                    city VARCHAR(100) NULL,
                    state CHAR(2) NULL,
                    status VARCHAR(20) NULL,
                    opened_at DATE NULL,
                    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_cnpj (cnpj),
                    INDEX idx_legal_name (legal_name(50)),
                    INDEX idx_state (state),
                    INDEX idx_city (city),
                    INDEX idx_status (status),
                    INDEX idx_hidden (is_hidden)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            Logger::info('Setup: Tabela companies criada com sucesso');
        } catch (PDOException $exception) {
            Logger::warning('Setup: Falha ao criar tabela companies: ' . $exception->getMessage());
        }
    }

    private function ensureSiteSettingsTable(PDO $pdo): void
    {
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS site_settings (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    key_name VARCHAR(120) NOT NULL UNIQUE,
                    value_text TEXT NOT NULL,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (PDOException $exception) {
            Logger::warning('Setup: ' . $exception->getMessage());
        }
    }

    private function ensureDefaultSiteSettings(PDO $pdo): void
    {
        $defaults = [
            'site_name' => (string) env('APP_NAME', 'Plattadata'),
            'site_description' => 'Consulta de CNPJ, QSA, CNAE e dados empresariais em tempo real.',
            'homepage_title' => 'Consulta CNPJ',
            'homepage_subtitle' => 'Busque CNPJ gratuitamente sem precisar de login.',
            'homepage_search_placeholder' => '00.000.000/0001-00',
            'homepage_public_notice' => 'A busca publica consulta e salva cache local para acelerar futuras consultas.',
            'contact_email' => (string) env('ADMIN_EMAIL', ''),
            'contact_phone' => '',
            'contact_whatsapp' => '',
            'footer_text' => 'Dados empresariais publicos com foco em transparencia e compliance.',
            'seo_default_robots' => 'index,follow',
            'companies_per_page' => '15',
            'public_search_rate_limit_per_minute' => (string) config('app.rate_limit.cnpj_search_public_per_minute', 20),
            'sitemap_company_limit' => '10000',
            'public_search_enabled' => '1',
        ];

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO site_settings (key_name, value_text, updated_at) VALUES (:key_name, :value_text, NOW())
                 ON DUPLICATE KEY UPDATE value_text = value_text'
            );

            foreach ($defaults as $key => $value) {
                $stmt->execute([
                    'key_name' => $key,
                    'value_text' => $value,
                ]);
            }
        } catch (PDOException $exception) {
            Logger::warning('Setup: ' . $exception->getMessage());
        }
    }

    private function ensureRemovalSystemSchema(PDO $pdo): void
    {
        try {
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'moderator', 'editor', 'viewer') NOT NULL DEFAULT 'viewer'");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        if (!$this->schemaService->columnExists($pdo, 'companies', 'is_hidden')) {
            try {
                $pdo->exec("ALTER TABLE companies ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0 AFTER query_failures");
                $pdo->exec("CREATE INDEX idx_companies_hidden ON companies(is_hidden)");
            } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
            }
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS company_removal_requests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id BIGINT UNSIGNED NOT NULL,
                cnpj CHAR(14) NOT NULL,
                requester_name VARCHAR(120) NOT NULL,
                requester_email VARCHAR(160) NOT NULL,
                verification_type ENUM('email', 'document') NOT NULL,
                verification_code CHAR(6) NULL,
                document_path VARCHAR(255) NULL,
                status ENUM('pending', 'verified', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
                admin_notes TEXT NULL,
                verified_at DATETIME NULL,
                resolved_at DATETIME NULL,
                resolved_by INT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_removal_status (status),
                INDEX idx_removal_cnpj (cnpj),
                CONSTRAINT fk_removal_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                CONSTRAINT fk_removal_admin FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS exchange_rates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                currency_code CHAR(3) NOT NULL,
                currency_name VARCHAR(60) NOT NULL,
                paridade_compra DECIMAL(12,6) NULL,
                paridade_venda DECIMAL(12,6) NULL,
                cotacao_compra DECIMAL(12,6) NOT NULL,
                cotacao_venda DECIMAL(12,6) NOT NULL,
                tipo_boletim VARCHAR(20) NULL,
                data_cotacao DATE NOT NULL,
                fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_currency_date (currency_code, data_cotacao),
                INDEX idx_exchange_date (data_cotacao),
                INDEX idx_exchange_currency (currency_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS economic_indicators (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                indicator_code VARCHAR(30) NOT NULL,
                indicator_name VARCHAR(60) NOT NULL,
                indicator_value DECIMAL(16,4) NOT NULL,
                indicator_unit VARCHAR(20) NOT NULL DEFAULT '%',
                indicator_period VARCHAR(20) NULL,
                data_referencia DATE NOT NULL,
                fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_indicator_date (indicator_code, data_referencia),
                INDEX idx_indicator_code (indicator_code),
                INDEX idx_indicator_date (data_referencia)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }
    }

    private function ensureImpostometroArrecadacaoSchema(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS impostometro_arrecadacao (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ano INT NOT NULL,
                mes TINYINT NOT NULL,
                total DECIMAL(20,2) NOT NULL DEFAULT 0,
                rfb DECIMAL(20,2) NOT NULL DEFAULT 0,
                outros DECIMAL(20,2) NOT NULL DEFAULT 0,
                fonte VARCHAR(100) NULL,
                data_publicacao DATE NULL,
                oficial TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_ano_mes (ano, mes),
                INDEX idx_arrecadacao_ano (ano),
                INDEX idx_arrecadacao_mes (mes),
                INDEX idx_arrecadacao_oficial (oficial)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }
    }

    private function ensureFavoritesSchema(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS favorite_groups (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                name VARCHAR(100) NOT NULL,
                color VARCHAR(20) DEFAULT 'primary',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_groups_user (user_id),
                CONSTRAINT fk_groups_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS user_favorites (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                company_id BIGINT UNSIGNED NOT NULL,
                group_id INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_company_favorite (user_id, company_id),
                INDEX idx_favorites_user (user_id),
                INDEX idx_favorites_company (company_id),
                INDEX idx_favorites_group (group_id),
                CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_favorites_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                CONSTRAINT fk_favorites_group FOREIGN KEY (group_id) REFERENCES favorite_groups(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS company_changes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id BIGINT UNSIGNED NOT NULL,
                change_type ENUM('status', 'address', 'capital', 'cnae', 'name', 'contact', 'other') NOT NULL,
                field_name VARCHAR(50) NOT NULL,
                old_value TEXT NULL,
                new_value TEXT NULL,
                changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_company (company_id),
                INDEX idx_change_type (change_type),
                INDEX idx_changed_at (changed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS company_change_subscriptions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                company_cnpj VARCHAR(14) NOT NULL,
                notify_email TINYINT(1) NOT NULL DEFAULT 1,
                notify_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
                whatsapp_phone VARCHAR(20) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_cnpj (company_cnpj),
                UNIQUE KEY uq_user_company (user_id, company_cnpj)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                action VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id BIGINT UNSIGNED NULL,
                old_values JSON NULL,
                new_values JSON NULL,
                changes JSON NULL,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_action (action),
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }
    }

    private function ensureLocationAndTaxSchema(PDO $pdo): void
    {
        $lock = base_path('storage/.schema_location_completed');
        if (is_file($lock) && $this->schemaService->tableExists($pdo, 'states') && $this->schemaService->tableExists($pdo, 'municipalities') && $this->schemaService->tableExists($pdo, 'company_tax_data')) {
            return;
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS states (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uf CHAR(2) NOT NULL UNIQUE,
                name VARCHAR(80) NOT NULL,
                region VARCHAR(20) NOT NULL,
                ibge_code TINYINT UNSIGNED NOT NULL,
                population BIGINT UNSIGNED NULL,
                gdp DECIMAL(18,2) NULL,
                gdp_per_capita DECIMAL(12,2) NULL,
                area_km2 DECIMAL(12,2) NULL,
                capital_city VARCHAR(100) NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_states_region (region),
                INDEX idx_states_ibge (ibge_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $count = $pdo->query("SELECT COUNT(*) FROM states")->fetchColumn();
            if ((int) $count === 0) {
                $pdo->exec("INSERT INTO states (uf, name, region, ibge_code) VALUES
                    ('AC', 'Acre', 'Norte', 12), ('AL', 'Alagoas', 'Nordeste', 27), ('AP', 'Amapá', 'Norte', 16),
                    ('AM', 'Amazonas', 'Norte', 13), ('BA', 'Bahia', 'Nordeste', 29), ('CE', 'Ceará', 'Nordeste', 23),
                    ('DF', 'Distrito Federal', 'Centro-Oeste', 53), ('ES', 'Espírito Santo', 'Sudeste', 32),
                    ('GO', 'Goiás', 'Centro-Oeste', 52), ('MA', 'Maranhão', 'Nordeste', 21), ('MT', 'Mato Grosso', 'Centro-Oeste', 51),
                    ('MS', 'Mato Grosso do Sul', 'Centro-Oeste', 50), ('MG', 'Minas Gerais', 'Sudeste', 31),
                    ('PA', 'Pará', 'Norte', 15), ('PB', 'Paraíba', 'Nordeste', 25), ('PR', 'Paraná', 'Sul', 41),
                    ('PE', 'Pernambuco', 'Nordeste', 26), ('PI', 'Piauí', 'Nordeste', 22), ('RJ', 'Rio de Janeiro', 'Sudeste', 33),
                    ('RN', 'Rio Grande do Norte', 'Nordeste', 24), ('RS', 'Rio Grande do Sul', 'Sul', 43),
                    ('RO', 'Rondônia', 'Norte', 11), ('RR', 'Roraima', 'Norte', 14), ('SC', 'Santa Catarina', 'Sul', 42),
                    ('SP', 'São Paulo', 'Sudeste', 35), ('SE', 'Sergipe', 'Nordeste', 28), ('TO', 'Tocantins', 'Norte', 17)");
            }
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS municipalities (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ibge_code INT UNSIGNED NOT NULL UNIQUE,
                name VARCHAR(120) NOT NULL,
                state_uf CHAR(2) NOT NULL,
                mesoregion VARCHAR(120) NULL,
                microregion VARCHAR(120) NULL,
                population BIGINT UNSIGNED NULL,
                gdp DECIMAL(18,2) NULL,
                gdp_per_capita DECIMAL(12,2) NULL,
                area_km2 DECIMAL(12,2) NULL,
                ddd VARCHAR(4) NULL,
                slug VARCHAR(150) NULL,
                views INT UNSIGNED NOT NULL DEFAULT 0,
                weather_updated_at DATETIME NULL,
                weather_data JSON NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_municipalities_state (state_uf),
                INDEX idx_municipalities_ibge (ibge_code),
                INDEX idx_municipalities_slug (slug),
                INDEX idx_municipalities_name_state (name, state_uf),
                CONSTRAINT fk_municipality_state FOREIGN KEY (state_uf) REFERENCES states(uf) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            if (!$this->schemaService->columnExists($pdo, 'municipalities', 'views')) {
                $pdo->exec("ALTER TABLE municipalities ADD COLUMN views INT UNSIGNED NOT NULL DEFAULT 0 AFTER ddd");
            }
            if (!$this->schemaService->columnExists($pdo, 'municipalities', 'slug')) {
                $pdo->exec("ALTER TABLE municipalities ADD COLUMN slug VARCHAR(150) NULL AFTER ddd");
                $pdo->exec("CREATE INDEX idx_municipalities_slug ON municipalities(slug)");
            }
            if (!$this->schemaService->columnExists($pdo, 'municipalities', 'vehicle_fleet')) {
                $pdo->exec("ALTER TABLE municipalities ADD COLUMN vehicle_fleet INT UNSIGNED NULL AFTER population");
            }
            if (!$this->schemaService->columnExists($pdo, 'municipalities', 'business_units')) {
                $pdo->exec("ALTER TABLE municipalities ADD COLUMN business_units INT UNSIGNED NULL AFTER vehicle_fleet");
            }
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS company_tax_data (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id BIGINT UNSIGNED NOT NULL,
                cnpj CHAR(14) NOT NULL,
                simples_opt_in TINYINT(1) NULL,
                simples_since DATE NULL,
                mei_opt_in TINYINT(1) NULL,
                mei_since DATE NULL,
                state_registrations JSON NULL,
                source VARCHAR(32) NOT NULL,
                fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tax_cnpj (cnpj),
                INDEX idx_tax_fetched (fetched_at),
                INDEX idx_tax_company (company_id),
                CONSTRAINT fk_tax_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS brasil_info (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                indicador VARCHAR(50) NOT NULL UNIQUE,
                valor DECIMAL(18,2) NULL,
                texto VARCHAR(100) NULL,
                fuente VARCHAR(100) NULL,
                ano INT NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_brasil_info_indicador (indicador)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            $count = $pdo->query("SELECT COUNT(*) FROM brasil_info")->fetchColumn();
            if ((int) $count === 0) {
                $pdo->exec("INSERT INTO brasil_info (indicador, valor, texto, fuente, ano) VALUES
                    ('populacao', 203080400, '203.080.400', 'IBGE - Census 2022', 2022),
                    ('pib', 9983000000000, '9,983 trilhões', 'IBGE - PIB 2020', 2020),
                    ('pib_per_capita', 49186, 'R$ 49.186', 'IBGE - PIB 2020', 2020),
                    ('area_km2', 8515767, '8.515.767', 'IBGE', 2022),
                    ('municipios', 5570, '5.570', 'IBGE', 2022),
                    ('estados', 27, '27', 'IBGE', 2022),
                    ('capital', NULL, 'Brasília', 'IBGE', 2022),
                    ('taxa_desemprego', 7.8, '7,8%', 'IBGE', 2024),
                    ('ipca', 4.62, '4,62%', 'IBGE', 2024),
                    ('hospitais', 6220, '6.220', 'CNES', 2023),
                    ('medicos_por_10mil', 26.9, '26,9/10mil', 'CNES', 2023),
                    ('leitos_por_10mil', 20.3, '20,3/10mil', 'CNES', 2023),
                    ('mortalidade_infantil', 12.62, '12,62', 'IBGE', 2022),
                    ('escolas', 178156, '178.156', 'INEP', 2023),
                    ('analfabetismo', 7.2, '7,2%', 'IBGE', 2022),
                    ('atividade_fisica', 37.9, '37,9%', 'IBGE', 2022),
                    ('taxa_fecundidade', 1.76, '1,76', 'IBGE', 2022),
                    ('frota_veiculos', 104742583, '104.742.583', 'DENATRAN', 2023),
                    ('aeroportos', 693, '693', 'ANAC', 2023),
                    ('energia_eletrica', 385912, '385.912 GWh', 'MME', 2022),
                    ('densidade_2022', 23.86, '23,86 hab/km²', 'IBGE', 2022),
                    ('exportacoes', 335610000000, 'US$ 335 bi', 'MDIC', 2023),
                    ('importacoes', 267800000000, 'US$ 268 bi', 'MDIC', 2023),
                    ('iluminacao_eletrica', 99.7, '99,7%', 'IBGE', 2022),
                    ('agua_rede', 85.5, '85,5%', 'IBGE', 2022),
                    ('esgotamento', 63.2, '63,2%', 'IBGE', 2022),
                    ('internet', 90.0, '90,0%', 'IBGE', 2022),
                    ('telefone_movel', 96.3, '96,3%', 'IBGE', 2022),
                    ('microcomputador', 42.6, '42,6%', 'IBGE', 2022),
                    ('televisao', 95.5, '95,5%', 'IBGE', 2022),
                    ('nome_masculino', NULL, 'José', 'IBGE - Nomes', 2022),
                    ('nome_feminino', NULL, 'Maria', 'IBGE - Nomes', 2022),
                    ('sobrenome', NULL, 'Silva', 'IBGE - Nomes', 2022)");
            }
        } catch (PDOException $e) {
            Logger::warning('Setup brasil_info: ' . $e->getMessage());
        }

        @file_put_contents($lock, date('c'));
    }

    private function ensureGdpSectorsSchema(PDO $pdo): void
    {
        $lock = base_path('storage/.schema_gdp_v1_completed');
        if (is_file($lock) && $this->schemaService->columnExists($pdo, 'municipalities', 'gdp_agri') && $this->schemaService->columnExists($pdo, 'states', 'gdp_agri')) {
            return;
        }

        $columns = ['gdp_agri', 'gdp_industry', 'gdp_services', 'gdp_admin'];

        foreach ($columns as $column) {
            if (!$this->schemaService->columnExists($pdo, 'municipalities', $column)) {
                try {
                    $pdo->exec("ALTER TABLE municipalities ADD COLUMN $column DECIMAL(18,2) NULL");
                } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
                }
            }
        }

        foreach ($columns as $column) {
            if (!$this->schemaService->columnExists($pdo, 'states', $column)) {
                try {
                    $pdo->exec("ALTER TABLE states ADD COLUMN $column DECIMAL(18,2) NULL");
                } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
                }
            }
        }

        @file_put_contents($lock, date('c'));
    }

    private function ensureQsaAndCnaeSchema(PDO $pdo): void
    {
        $lock = base_path('storage/.schema_qsa_v1_completed');
        if (is_file($lock) && $this->schemaService->tableExists($pdo, 'company_partners') && $this->schemaService->tableExists($pdo, 'company_secondary_cnaes') && $this->schemaService->tableExists($pdo, 'cnae_activities')) {
            return;
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS company_partners (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                role VARCHAR(100) NULL,
                document_masked VARCHAR(20) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_partner_name (name),
                INDEX idx_partner_company (company_id),
                CONSTRAINT fk_partner_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS company_secondary_cnaes (
                company_id BIGINT UNSIGNED NOT NULL,
                cnae_code VARCHAR(20) NOT NULL,
                description TEXT NULL,
                PRIMARY KEY (company_id, cnae_code),
                INDEX idx_sec_cnae_code (cnae_code),
                CONSTRAINT fk_sec_cnae_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS cnae_activities (
                code VARCHAR(20) PRIMARY KEY,
                slug VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                section VARCHAR(10) NULL,
                INDEX idx_cnae_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            if (!$this->schemaService->indexExists($pdo, 'companies', 'idx_companies_fulltext_names')) {
                $pdo->exec("ALTER TABLE companies ADD FULLTEXT idx_companies_fulltext_names (legal_name, trade_name)");
            }

            if (!$this->schemaService->columnExists($pdo, 'companies', 'district')) {
                $pdo->exec("ALTER TABLE companies ADD COLUMN district VARCHAR(120) NULL AFTER street");
                $pdo->exec("CREATE INDEX idx_companies_district ON companies(district)");
            }

            $check = $pdo->query("SELECT COUNT(*) FROM cnae_activities")->fetchColumn();
            if ((int) $check === 0) {
                $pdo->exec("INSERT INTO cnae_activities (code, slug, description, section) VALUES 
                    ('6201-5/00', 'desenvolvimento-de-software', 'Desenvolvimento de programas de computador sob encomenda', 'J'),
                    ('6202-3/00', 'consultoria-em-ti', 'Consultoria em tecnologia da informação', 'J'),
                    ('6203-1/00', 'software-customizavel', 'Desenvolvimento e licenciamento de programas de computador customizáveis', 'J'),
                    ('4711-3/02', 'supermercados', 'Comério varejista de mercadorias em geral, com predominância de produtos alimentícios - supermercados', 'G'),
                    ('5611-2/01', 'restaurantes', 'Restaurantes e similares', 'I'),
                    ('7311-4/00', 'agencias-de-publicidade', 'Agências de publicidade', 'M'),
                    ('7319-0/03', 'marketing-direto', 'Marketing direto', 'M')");
            }

            @file_put_contents($lock, date('c'));
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }
    }

    private function ensureQueueSchema(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                handler VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                error_message TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_jobs_status (status),
                INDEX idx_jobs_handler (handler)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }
    }

    private function ensureBlacklistSchema(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS cnpj_blacklist (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cnpj CHAR(14) NOT NULL UNIQUE,
                reason VARCHAR(255) NULL,
                requested_by INT UNSIGNED NULL,
                approved_by INT UNSIGNED NULL,
                status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                legal_document VARCHAR(180) NULL,
                legal_document_type VARCHAR(20) NULL,
                notes TEXT NULL,
                processed_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_blacklist_status (status),
                INDEX idx_blacklist_cnpj (cnpj),
                INDEX idx_blacklist_created (created_at),
                CONSTRAINT fk_blacklist_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_blacklist_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }
    }

    private function ensureNotificationsSchema(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS notification_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                type VARCHAR(50) NOT NULL,
                data_json LONGTEXT NULL,
                sent TINYINT(1) NOT NULL DEFAULT 0,
                read_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_notification_user_created (user_id, created_at),
                INDEX idx_notification_type (type),
                CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }
    }

    private function ensurePasswordResetSchema(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(160) NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reset_email (email),
                INDEX idx_reset_token (token),
                INDEX idx_reset_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }
    }

    private function ensureUserExtensionsSchema(PDO $pdo): void
    {
        try {
            if (!$this->schemaService->columnExists($pdo, 'users', 'notifications_enabled')) {
                $pdo->exec("ALTER TABLE users ADD COLUMN notifications_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active");
            }
            if (!$this->schemaService->columnExists($pdo, 'users', 'notification_preferences')) {
                $pdo->exec("ALTER TABLE users ADD COLUMN notification_preferences JSON NULL AFTER notifications_enabled");
            }
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }
    }

    private function ensureEmailVerificationSchema(PDO $pdo): void
    {
        try {
            if (!$this->schemaService->columnExists($pdo, 'users', 'email_verified_at')) {
                $pdo->exec("ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER notification_preferences");
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS email_verification_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                verified_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_verification_user (user_id),
                INDEX idx_verification_token (token),
                INDEX idx_verification_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }
    }

    private function ensureSearchOptimizationSchema(PDO $pdo): void
    {
        $indexes = [
            'ft_companies_search' => 'ALTER TABLE companies ADD FULLTEXT INDEX ft_companies_search (legal_name, trade_name, city)',
            'idx_companies_state_status' => 'CREATE INDEX idx_companies_state_status ON companies (state, status, updated_at)',
        ];

        foreach ($indexes as $index => $sql) {
            if ($this->schemaService->indexExists($pdo, 'companies', $index)) {
                continue;
            }
            try {
                $pdo->exec($sql);
            } catch (PDOException $exception) {
            Logger::warning('Setup: ' . $exception->getMessage());
            }
        }
    }

    private function ensureCacheTables(PDO $pdo): void
    {
        $tables = [
            'compliance_cache' => "CREATE TABLE IF NOT EXISTS compliance_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cnpj VARCHAR(14) NOT NULL,
                result_json JSON NOT NULL,
                source VARCHAR(50) NOT NULL,
                cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                INDEX idx_cnpj (cnpj),
                INDEX idx_expires (expires_at),
                UNIQUE INDEX idx_cnpj_unique (cnpj)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'address_cache' => "CREATE TABLE IF NOT EXISTS address_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cep VARCHAR(8) NOT NULL,
                result_json JSON NOT NULL,
                cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                INDEX idx_cep (cep),
                INDEX idx_expires (expires_at),
                UNIQUE INDEX idx_cep_unique (cep)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'municipality_cache' => "CREATE TABLE IF NOT EXISTS municipality_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ibge_code INT NOT NULL,
                result_json JSON NOT NULL,
                cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                INDEX idx_ibge (ibge_code),
                INDEX idx_expires (expires_at),
                UNIQUE INDEX idx_ibge_unique (ibge_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($tables as $table => $sql) {
            if ($this->schemaService->tableExists($pdo, $table)) {
                continue;
            }
            try {
                $pdo->exec($sql);
            } catch (PDOException $exception) {
            Logger::warning('Setup: ' . $exception->getMessage());
            }
        }
    }

    private function ensureFavoriteGroupsSchema(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS favorite_groups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                color VARCHAR(20) DEFAULT 'primary',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $exception) {
            Logger::warning('Setup: ' . $exception->getMessage());
        }

        try {
            $pdo->exec("ALTER TABLE favorites ADD COLUMN group_id INT NULL AFTER user_id");
        } catch (PDOException $exception) {
            Logger::warning('Setup: ' . $exception->getMessage());
        }
    }

    private function ensureEnrichmentSchema(PDO $pdo): void
    {
        $columns = [
            'website' => 'ALTER TABLE companies ADD COLUMN website VARCHAR(500) NULL',
            'logo_url' => 'ALTER TABLE companies ADD COLUMN logo_url TEXT NULL',
            'employees_estimate' => 'ALTER TABLE companies ADD COLUMN employees_estimate VARCHAR(20) NULL',
            'enriched_at' => 'ALTER TABLE companies ADD COLUMN enriched_at DATETIME NULL',
        ];

        foreach ($columns as $column => $sql) {
            if ($this->schemaService->columnExists($pdo, 'companies', $column)) {
                continue;
            }
            try {
                $pdo->exec($sql);
            } catch (PDOException $exception) {
            Logger::warning('Setup: ' . $exception->getMessage());
                Logger::warning('Falha ao adicionar coluna de enrichment.', [
                    'column' => $column,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $requiredColumns = array_keys($columns);
        $missing = [];
        foreach ($requiredColumns as $column) {
            if (!$this->schemaService->columnExists($pdo, 'companies', $column)) {
                $missing[] = $column;
            }
        }

        if (!empty($missing)) {
            $this->applySqlMigrationFile($pdo, base_path('database/migration_company_enrichment_safe.sql'));

            $stillMissing = [];
            foreach ($requiredColumns as $column) {
                if (!$this->schemaService->columnExists($pdo, 'companies', $column)) {
                    $stillMissing[] = $column;
                }
            }

            if (!empty($stillMissing)) {
                Logger::warning('Colunas de enrichment ainda ausentes apos tentativas de migracao.', [
                    'missing_columns' => $stillMissing,
                ]);
            }
        }
    }

    private function applySqlMigrationFile(PDO $pdo, string $filePath): void
    {
        if (!is_file($filePath)) {
            return;
        }

        $sql = (string) file_get_contents($filePath);
        if ($sql === '') {
            return;
        }

        $statements = $this->schemaService->splitSqlStatements($sql);
        foreach ($statements as $statement) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $exception) {
            Logger::warning('Setup: ' . $exception->getMessage());
                Logger::warning('Falha ao executar statement de migracao SQL.', [
                    'file' => $filePath,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function ensureRemovalCancelledStatus(PDO $pdo): void
    {
        try {
            $pdo->exec("ALTER TABLE company_removal_requests MODIFY COLUMN status ENUM('pending', 'verified', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending'");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }
    }

    private function ensureVehicleFleetTypesSchema(PDO $pdo): void
    {
        $lock = base_path('storage/.schema_vehicle_types_v1_completed');
        if (is_file($lock) && $this->schemaService->tableExists($pdo, 'municipality_vehicle_types')) {
            return;
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS municipality_vehicle_types (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ibge_code INT UNSIGNED NOT NULL,
                vehicle_type VARCHAR(100) NOT NULL,
                vehicle_count INT UNSIGNED NULL,
                year YEAR NULL,
                fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_ibge_type (ibge_code, vehicle_type),
                INDEX idx_ibge_code (ibge_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        if (!$this->schemaService->columnExists($pdo, 'municipalities', 'vehicle_types_data')) {
            try {
                $pdo->exec("ALTER TABLE municipalities ADD COLUMN vehicle_types_data JSON NULL AFTER vehicle_fleet");
            } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
            }
        }

        @file_put_contents($lock, date('c'));
    }

    private function ensureIpBlocklistSchema(PDO $pdo): void
    {
        $lock = base_path('storage/.ipblocklist_completed');
        if (is_file($lock) && $this->schemaService->tableExists($pdo, 'blocked_ips') && $this->schemaService->tableExists($pdo, 'ip_failed_attempts')) {
            return;
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_ips (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL UNIQUE,
                reason VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NULL,
                INDEX idx_blocked_ip (ip),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ip_failed_attempts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL UNIQUE,
                attempts INT UNSIGNED NOT NULL DEFAULT 1,
                last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip_attempts (ip, attempts)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        @file_put_contents($lock, date('c'));
    }

    private function ensureApiKeysSchema(PDO $pdo): void
    {
        $lock = base_path('storage/.api_keys_completed');
        if (is_file($lock) && $this->schemaService->tableExists($pdo, 'api_keys') && $this->schemaService->tableExists($pdo, 'api_access_logs')) {
            return;
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                api_key VARCHAR(255) NULL,
                webhook_secret VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                expires_at DATETIME NULL,
                UNIQUE KEY uk_api_key (api_key),
                UNIQUE KEY uk_webhook_secret (webhook_secret)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS api_access_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_key VARCHAR(50) NULL,
                action VARCHAR(100) NOT NULL,
                resource VARCHAR(255) NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created_at (created_at),
                INDEX idx_api_key (api_key),
                INDEX idx_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            if (!$this->schemaService->indexExists($pdo, 'companies', 'idx_companies_capital')) {
                $pdo->exec("CREATE INDEX idx_companies_capital ON companies(capital_social)");
            }
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        @file_put_contents($lock, date('c'));
    }

    private function ensureExtendedDataSchema(PDO $pdo): void
    {
        $lock = base_path('storage/.extended_data_completed');
        if (is_file($lock) && $this->schemaService->tableExists($pdo, 'partner_history') && $this->schemaService->tableExists($pdo, 'company_competitors') && $this->schemaService->tableExists($pdo, 'compliance_records') && $this->schemaService->tableExists($pdo, 'company_scores') && $this->schemaService->tableExists($pdo, 'cnae_statistics')) {
            return;
        }

        $columns = [
            'revenue_estimate', 'tax_history', 'installment_plans', 'tax_debts',
            'partners_data', 'total_partners', 'latitude', 'longitude', 'map_url',
            'region_type', 'competitors_count', 'market_trend', 'competition_score',
            'compliance_status', 'negative_certificates', 'last_balance_sheet',
            'risk_score', 'risk_level', 'social_media', 'photos', 'google_place_id',
            'ratings', 'credit_score', 'inactivity_probability', 'growth_potential',
            'recommended_porte', 'whatsapp_business_id', 'email_verified', 'website_verified'
        ];

        foreach ($columns as $column) {
            if ($this->schemaService->columnExists($pdo, 'companies', $column)) {
                continue;
            }
            try {
                if ($column === 'region_type') {
                    $pdo->exec("ALTER TABLE companies ADD COLUMN region_type ENUM('metropolitana','interior','capital','rural') NULL");
                } elseif ($column === 'market_trend') {
                    $pdo->exec("ALTER TABLE companies ADD COLUMN market_trend ENUM('crescendo','estavel','declinando') NULL");
                } elseif ($column === 'risk_level') {
                    $pdo->exec("ALTER TABLE companies ADD COLUMN risk_level ENUM('baixo','medio','alto','critico') NULL");
                } elseif ($column === 'growth_potential') {
                    $pdo->exec("ALTER TABLE companies ADD COLUMN growth_potential ENUM('alto','medio','baixo') NULL");
                } elseif ($column === 'inactivity_probability') {
                    $pdo->exec("ALTER TABLE companies ADD COLUMN inactivity_probability DECIMAL(5,2) NULL");
                } else {
                    $pdo->exec("ALTER TABLE companies ADD COLUMN $column VARCHAR(500) NULL");
                }
            } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
            }
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS partner_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id BIGINT UNSIGNED NOT NULL,
                partner_name VARCHAR(255) NOT NULL,
                partner_document VARCHAR(20) NULL,
                role VARCHAR(100) NULL,
                participation_percentage DECIMAL(5,2) NULL,
                entered_at DATE NULL,
                exited_at DATE NULL,
                is_current TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_partner_company (company_id),
                INDEX idx_partner_document (partner_document),
                CONSTRAINT fk_partner_history_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS company_competitors (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id BIGINT UNSIGNED NOT NULL,
                competitor_cnpj CHAR(14) NOT NULL,
                competitor_name VARCHAR(255) NOT NULL,
                distance_km DECIMAL(8,2) NULL,
                similarity_score TINYINT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_company_competitor (company_id, competitor_cnpj),
                CONSTRAINT fk_competitor_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS compliance_records (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id BIGINT UNSIGNED NOT NULL,
                certificate_type ENUM('federal','estadual','municipal','trabalhista','previdenciaria') NOT NULL,
                certificate_status ENUM('regular','irregular','pendente','vencida') NOT NULL,
                certificate_number VARCHAR(100) NULL,
                issued_at DATE NULL,
                expires_at DATE NULL,
                source VARCHAR(50) NOT NULL,
                details JSON NULL,
                fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_compliance_company (company_id),
                INDEX idx_compliance_type (certificate_type),
                CONSTRAINT fk_compliance_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS company_scores (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id BIGINT UNSIGNED NOT NULL,
                score_type ENUM('credito','inatividade','compliance','geral') NOT NULL,
                score_value DECIMAL(6,2) NOT NULL,
                factors JSON NULL,
                model_version VARCHAR(20) NULL,
                calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_score_company (company_id),
                INDEX idx_score_type (score_type),
                CONSTRAINT fk_score_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS cnae_statistics (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cnae_code VARCHAR(20) NOT NULL UNIQUE,
                total_companies INT UNSIGNED DEFAULT 0,
                avg_capital DECIMAL(18,2) DEFAULT 0,
                avg_revenue DECIMAL(18,2) DEFAULT 0,
                revenue_median DECIMAL(18,2) DEFAULT 0,
                market_trend ENUM('crescendo','estavel','declinando') DEFAULT 'estavel',
                competition_score TINYINT UNSIGNED DEFAULT 50,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        @file_put_contents($lock, date('c'));
    }

    private function ensureMentionAlertsSchema(PDO $pdo): void
    {
        $lock = base_path('storage/.mention_alerts_completed');
        if (is_file($lock) && $this->schemaService->tableExists($pdo, 'company_mentions') && $this->schemaService->tableExists($pdo, 'company_mentions_history') && $this->schemaService->tableExists($pdo, 'mention_alert_subscriptions')) {
            return;
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS company_mentions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cnpj CHAR(14) NOT NULL,
                company_name VARCHAR(255) NOT NULL,
                mention_data JSON NULL,
                checked_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_cnpj (cnpj),
                INDEX idx_checked_at (checked_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS company_mentions_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cnpj CHAR(14) NOT NULL,
                company_name VARCHAR(255) NOT NULL,
                mention_data JSON NULL,
                checked_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cnpj (cnpj),
                INDEX idx_checked_at (checked_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS mention_alert_subscriptions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cnpj CHAR(14) NOT NULL,
                email VARCHAR(160) NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_cnpj_email (cnpj, email),
                INDEX idx_email (email),
                INDEX idx_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: ' . $e->getMessage());
        }

        @file_put_contents($lock, date('c'));
    }

    private function isSetupLocked(): bool
    {
        return is_file(base_path('storage/.setup_completed'));
    }

    private function writeSetupLock(): void
    {
        @file_put_contents(base_path('storage/.setup_completed'), date('c'));
    }

    private function clearSetupLock(): void
    {
        $lock = base_path('storage/.setup_completed');
        if (is_file($lock)) {
            @unlink($lock);
        }
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/\s|[#\'"\\\\]/', $value)) {
            return '"' . str_replace('"', '\"', $value) . '"';
        }

        return $value;
    }

    private function validateCriticalTables(PDO $pdo): void
    {
        $criticalTables = [
            'users' => 'Tabela de usuários',
            'companies' => 'Tabela de empresas',
            'site_settings' => 'Tabela de configurações',
        ];

        $missingTables = [];
        foreach ($criticalTables as $tableName => $description) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '" . addslashes($tableName) . "'");
                $exists = (bool) $stmt->fetch();
                if (!$exists) {
                    $missingTables[] = $tableName . ' (' . $description . ')';
                    Logger::error('Setup: CRÍTICO - Tabela crítica não encontrada: ' . $tableName);
                } else {
                    Logger::info('Setup: Validação OK - Tabela ' . $tableName . ' existe');
                }
            } catch (PDOException $exception) {
                Logger::error('Setup: Erro ao validar tabela ' . $tableName . ': ' . $exception->getMessage());
                $missingTables[] = $tableName;
            }
        }

        if (!empty($missingTables)) {
            Logger::error('Setup: FALHA NA VALIDAÇÃO - Tabelas críticas ausentes: ' . implode(', ', $missingTables));
        } else {
            Logger::info('Setup: VALIDAÇÃO COMPLETA - Todas as tabelas críticas foram criadas com sucesso');
        }
    }

    private function log(string $message): void
    {
        \App\Core\Logger::info('Setup: ' . $message);
    }
}
