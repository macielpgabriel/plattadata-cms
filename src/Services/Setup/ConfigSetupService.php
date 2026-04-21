<?php

declare(strict_types=1);

namespace App\Services\Setup;

use App\Core\Logger;
use PDO;
use PDOException;

final class ConfigSetupService
{
    public function ensureSiteSettingsTable(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                key_name VARCHAR(100) NOT NULL UNIQUE,
                value_text TEXT NULL,
                value_json JSON NULL,
                updated_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            Logger::info('Setup: Tabela site_settings verificada/criada');
        } catch (PDOException $e) {
            Logger::warning('Setup: Erro ao criar site_settings: ' . $e->getMessage());
        }
    }

    public function ensureDefaultSiteSettings(PDO $pdo): void
    {
        $defaults = [
            'site_name' => 'Plattadata',
            'site_description' => 'Dados empresariais, Compliance e Analytics',
            'site_logo' => '',
            'homepage_search_placeholder' => '00.000.000/0001-00',
            'homepage_public_notice' => 'A busca pública consulta e salva cache local para acelerar futuras consultas.',
            'contact_email' => '',
            'contact_phone' => '',
            'contact_whatsapp' => '',
            'footer_text' => 'Dados empresariais públicos com foco em transparência e compliance.',
            'seo_default_robots' => 'index,follow',
            'companies_per_page' => '15',
            'public_search_rate_limit_per_minute' => '20',
            'sitemap_company_limit' => '10000',
            'public_search_enabled' => '1',
        ];

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO site_settings (key_name, value_text, updated_at) VALUES (:key_name, :value_text, NOW())
                 ON DUPLICATE KEY UPDATE value_text = :value_text'
            );

            foreach ($defaults as $key => $value) {
                try {
                    $stmt->execute([
                        'key_name' => $key,
                        'value_text' => $value,
                    ]);
                } catch (PDOException $e) {
                    // Ignora duplicatas
                }
            }

            Logger::info('Setup: Configurações padrão aplicadas');
        } catch (PDOException $e) {
            Logger::warning('Setup: Erro ao definir configurações: ' . $e->getMessage());
        }
    }

    public function ensureInitialAdmin(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query('SELECT COUNT(*) AS total FROM users');
            $count = (int) (($stmt->fetch()['total'] ?? 0));
            if ($count > 0) {
                return false;
            }

            $adminName = (string) env('CMS_ADMIN_NAME', 'Administrador');
            $adminEmail = (string) env('CMS_ADMIN_EMAIL', 'admin@local.test');
            $adminPassword = (string) env('CMS_ADMIN_PASSWORD', 'Plattadata#2026!');

            if ($adminEmail === 'admin@local.test' || $adminPassword === 'Plattadata#2026!') {
                Logger::warning('Setup: Credenciais padrão não foram alteradas no .env');
            }

            $hash = password_hash($adminPassword, PASSWORD_ARGON2ID);

            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, two_factor_enabled, is_active, email_verified_at) 
                 VALUES (:name, :email, :password_hash, :role, :two_factor_enabled, :is_active, NOW())'
            );

            $stmt->execute([
                'name' => $adminName,
                'email' => $adminEmail,
                'password_hash' => $hash,
                'role' => 'admin',
                'two_factor_enabled' => 0,
                'is_active' => 1,
            ]);

            Logger::info("Setup: Usuário admin criado: $adminEmail");
            return true;
        } catch (PDOException $e) {
            Logger::error('Setup: Erro ao criar admin: ' . $e->getMessage());
            return false;
        }
    }

    public function ensureUsersTable(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(160) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('admin', 'moderator', 'editor', 'viewer') NOT NULL DEFAULT 'viewer',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
                failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                locked_until DATETIME NULL,
                last_login_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_users_email (email),
                INDEX idx_users_role (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            Logger::warning('Setup: users table: ' . $e->getMessage());
        }
    }
}