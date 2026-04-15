<?php

require dirname(__DIR__) . '/bootstrap/app.php';

echo "=== DEBUG: Verificação de Configuração ===\n\n";

echo "DB_NAME do .env: " . env('DB_NAME', 'NÃO DEFINIDO') . "\n";
echo "DB_USER do .env: " . env('DB_USER', 'NÃO DEFINIDO') . "\n";

try {
    $pdo = \App\Core\Database::connection();
    echo "\n✓ Conexão OK\n";
    
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $currentDb = $stmt->fetch();
    echo "Banco atual: " . $currentDb['db_name'] . "\n\n";
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tabelas existentes:\n";
    if (empty($tables)) {
        echo "  (nenhuma tabela encontrada)\n";
    } else {
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n✗ ERRO: " . $e->getMessage() . "\n";
}