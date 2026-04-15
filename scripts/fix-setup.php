<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Database;
use App\Services\SetupService;

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DO SETUP ===\n\n";

// 1. Verificar lock
$lockFile = base_path('storage/.setup_completed');
echo "1. Lock do setup existe? " . (is_file($lockFile) ? "SIM" : "NÃO") . "\n";

// 2. Verificar banco de dados
echo "\n2. Testando conexão com banco...\n";
try {
    $pdo = Database::connection();
    $stmt = $pdo->query("SELECT DATABASE() as db");
    $db = $stmt->fetch();
    echo "   ✓ Conectado ao banco: " . $db['db'] . "\n";
} catch (Exception $e) {
    echo "   ✗ ERRO: " . $e->getMessage() . "\n";
    exit;
}

// 3. Listar tabelas existentes
echo "\n3. Tabelas existentes:\n";
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($tables)) {
    echo "   (nenhuma tabela - banco vazio!)\n";
} else {
    foreach ($tables as $t) {
        echo "   - $t\n";
    }
}

// 4. Criar tabelas se não existirem
echo "\n4. Criando tabelas críticas...\n";

$tablesToCreate = [
    'users' => 'CREATE TABLE IF NOT EXISTS users (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    
    'companies' => 'CREATE TABLE IF NOT EXISTS companies (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    
    'site_settings' => 'CREATE TABLE IF NOT EXISTS site_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        key_name VARCHAR(120) NOT NULL UNIQUE,
        value_text TEXT NOT NULL,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
];

foreach ($tablesToCreate as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "   ✓ $name criada\n";
    } catch (Exception $e) {
        echo "   ✗ ERRO em $name: " . $e->getMessage() . "\n";
    }
}

// 5. Verificar novamente
echo "\n5. Tabelas após criação:\n";
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "   - $t\n";
}

// 6. Criar lock
echo "\n6. Criando lock do setup...\n";
file_put_contents($lockFile, date('c'));
echo "   ✓ Lock criado\n";

echo "\n=== PRONTO! ===\n";
echo "Acesse: https://plattadata.com/\n";