<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Database;
use PDOException;

header('Content-Type: text/plain; charset=utf-8');

echo "===========================================\n";
echo "CRIAR TABELAS CRÍTICAS - Plattadata CMS\n";
echo "===========================================\n\n";

try {
    $pdo = Database::connection();
    
    $stmt = $pdo->query("SELECT DATABASE() as db");
    $db = $stmt->fetch();
    echo "Banco: " . $db['db'] . "\n\n";
    
    // Verificar se companies existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'companies'");
    if ($stmt->fetch()) {
        echo "⚠️ Tabela 'companies' já existe!\n";
    } else {
        echo "Criando tabela 'companies'...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS companies (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Tabela 'companies' criada!\n";
    }
    
    // Verificar se users existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->fetch()) {
        echo "⚠️ Tabela 'users' já existe!\n";
    } else {
        echo "Criando tabela 'users'...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(160) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('admin', 'moderator', 'editor', 'viewer') NOT NULL DEFAULT 'viewer',
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Tabela 'users' criada!\n";
    }
    
    // Verificar se site_settings existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'site_settings'");
    if ($stmt->fetch()) {
        echo "⚠️ Tabela 'site_settings' já existe!\n";
    } else {
        echo "Criando tabela 'site_settings'...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS site_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                key_name VARCHAR(120) NOT NULL UNIQUE,
                value_text TEXT NOT NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Tabela 'site_settings' criada!\n";
    }
    
    echo "\n===========================================\n";
    echo "✅ PRONTO! Acesse: https://plattadata.com/\n";
    echo "===========================================\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO PDO: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}