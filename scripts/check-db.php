<?php

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Database;

echo "=== DEBUG: Verificação do Banco de Dados ===\n\n";

try {
    $pdo = Database::connection();
    
    // Mostrar qual banco está conectado
    $stmt = $pdo->query("SELECT DATABASE() as db");
    $db = $stmt->fetch();
    echo "Banco conectado: " . $db['db'] . "\n\n";
    
    // Listar todas as tabelas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tabelas encontradas: " . count($tables) . "\n";
    if (empty($tables)) {
        echo "\n⚠️ Nenhuma tabela! O banco está vazio.\n";
    } else {
        echo "\nLista de tabelas:\n";
        foreach ($tables as $t) {
            echo "  - $t\n";
        }
    }
    
    // Verificar specifically companies
    echo "\n--- Verificação companies ---\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'companies'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "✅ Tabela companies EXISTE\n";
    } else {
        echo "❌ Tabela companies NÃO EXISTE\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}