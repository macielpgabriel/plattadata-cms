<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Services\Setup\SchemaSetupService;
use App\Services\Setup\MigrationService;
use App\Services\Setup\ConfigSetupService;
use App\Services\Setup\DatabaseSetupService;
use App\Services\Setup\CompanySchemaService;
use PDO;
use PDOException;
use Throwable;

final class SetupService
{
    private ?string $lastConnectionError = null;
    private SchemaSetupService $schemaService;
    private MigrationService $migrationService;
    private ConfigSetupService $configService;
    private DatabaseSetupService $databaseService;
    private CompanySchemaService $companySchemaService;

    public function __construct()
    {
        $this->schemaService = new SchemaSetupService();
        $this->migrationService = new MigrationService();
        $this->configService = new ConfigSetupService();
        $this->databaseService = new DatabaseSetupService();
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
        return $this->databaseService->getLastConnectionError();
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
        $this->ensureRemovalSystemSchema($pdo);
        $this->ensureFavoritesSchema($pdo);
        $this->ensureImpostometroArrecadacaoSchema($pdo);
        $this->ensureMentionAlertsSchema($pdo);
        $this->ensureReviewsSystemSchema($pdo);
        $this->ensurePerformanceIndexes($pdo);

        // AGORA verifica o lock - apenas após criar todas as tabelas críticas
        if ($this->isSetupLocked()) {
            return;
        }

        $this->runSchemaIfNeeded($pdo);
        $this->ensureSiteSettingsTable($pdo);
        $this->ensureDefaultSiteSettings($pdo);
        $this->companySchemaService->ensureAdvancedCompanySchema($pdo);
        $this->ensureRemovalSystemSchema($pdo);
        $this->ensureFavoritesSchema($pdo);
        $this->ensureImpostometroArrecadacaoSchema($pdo);
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
                
                // Aplica migration CNPJ Alfanumérico automaticamente
                $this->applyCnpjAlphanumericMigration($pdo);
                return;
            }
        } catch (PDOException $exception) {
            Logger::warning('Setup: Erro ao verificar existência de tabela companies: ' . $exception->getMessage());
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
        
        // Aplica migration CNPJ Alfanumérico apos schema
        $this->applyCnpjAlphanumericMigration($pdo);
        
        // Aplica outras migrations
        $this->migrationService->applyAllMigrations($pdo);
    }
    
    private function applyCnpjAlphanumericMigration(PDO $pdo): void
    {
        try {
            // Verifica se ja foi aplicada
            $checkStmt = $pdo->query("DESCRIBE companies cnpj");
            $columnInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($columnInfo && $columnInfo['Type'] === 'varchar(14)') {
                Logger::info('Setup: CNPJ Alfanumérico migration ja aplicada (varchar)');
                return;
            }
            
            if ($columnInfo && str_starts_with($columnInfo['Type'], 'char(14)')) {
                Logger::info('Setup: Aplicando CNPJ Alfanumérico migration...');
                
                // Executa os ALTERs
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
                        Logger::warning('Setup: ALTER CNPJ ignorado: ' . $e->getMessage());
                    }
                }
                
                Logger::info("Setup: CNPJ Alfanumérico migration concluida ({$executed} alters)");
            }
        } catch (PDOException $e) {
            Logger::warning('Setup: CNPJ migration error: ' . $e->getMessage());
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

        @file_put_contents(base_path('storage/.schema_v4_completed'), date('c'));
    }

    private function ensureReviewsSystemSchema(PDO $pdo): void
    {
        $lock = base_path('storage/.reviews_completed');
        if (is_file($lock) && $this->schemaService->tableExists($pdo, 'company_reviews') && $this->schemaService->tableExists($pdo, 'company_edit_requests')) {
            return;
        }

        try {
            if (!$this->schemaService->tableExists($pdo, 'company_reviews')) {
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
            }
        } catch (PDOException $e) {
            Logger::warning('Setup reviews: ' . $e->getMessage());
        }

        try {
            if (!$this->schemaService->tableExists($pdo, 'company_edit_requests')) {
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
            }
        } catch (PDOException $e) {
            Logger::warning('Setup edit_requests: ' . $e->getMessage());
        }

        try {
            if (!$this->schemaService->columnExists($pdo, 'companies', 'description')) {
                $pdo->exec("ALTER TABLE companies ADD COLUMN description TEXT NULL");
            }
            if (!$this->schemaService->columnExists($pdo, 'companies', 'facebook')) {
                $pdo->exec("ALTER TABLE companies ADD COLUMN facebook VARCHAR(255) NULL");
            }
            if (!$this->schemaService->columnExists($pdo, 'companies', 'instagram')) {
                $pdo->exec("ALTER TABLE companies ADD COLUMN instagram VARCHAR(50) NULL");
            }
            if (!$this->schemaService->columnExists($pdo, 'companies', 'linkedin')) {
                $pdo->exec("ALTER TABLE companies ADD COLUMN linkedin VARCHAR(255) NULL");
            }
            if (!$this->schemaService->columnExists($pdo, 'companies', 'whatsapp')) {
                $pdo->exec("ALTER TABLE companies ADD COLUMN whatsapp VARCHAR(20) NULL");
            }
        } catch (PDOException $e) {
            Logger::warning('Setup company columns: ' . $e->getMessage());
        }

        @file_put_contents(base_path('storage/.reviews_completed'), date('c'));
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

    private function ensurePerformanceIndexes(PDO $pdo): void
    {
        $lock = base_path('storage/.performance_indexes_completed');
        if (is_file($lock)) {
            return;
        }

        $indexes = [
            'idx_companies_cnae_main' => 'CREATE INDEX IF NOT EXISTS idx_companies_cnae_main ON companies(cnae_main_code)',
            'idx_companies_opened_at' => 'CREATE INDEX IF NOT EXISTS idx_companies_opened_at ON companies(opened_at)',
            'idx_companies_status_opened' => 'CREATE INDEX IF NOT EXISTS idx_companies_status_opened ON companies(status, opened_at)',
            'idx_companies_city_state' => 'CREATE INDEX IF NOT EXISTS idx_companies_city_state ON companies(city, state)',
            'idx_companies_state_status' => 'CREATE INDEX IF NOT EXISTS idx_companies_state_status ON companies(state, status)',
        ];

        foreach ($indexes as $name => $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                Logger::warning('Setup index ' . $name . ': ' . $e->getMessage());
            }
        }

        @file_put_contents($lock, date('c'));
    }

    private function log(string $message): void
    {
        \App\Core\Logger::info('Setup: ' . $message);
    }
}
