#!/usr/bin/env php
<?php

/**
 * Script de Recuperação - Criar Tabelas Faltantes
 * 
 * Uso: php scripts/fix-missing-tables.php
 * 
 * Este script verifica quais tabelas críticas estão faltando
 * e tenta criá-las executando database/schema.sql
 */

// Carrega o autoloader e configurações
require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Logger;
use App\Core\Database;
use PDO;
use PDOException;

echo "========================================\n";
echo "Ferramenta de Recuperação de Tabelas\n";
echo "========================================\n\n";

// Tenta conectar ao banco
try {
    $pdo = Database::connection();
    echo "✓ Conexão com banco de dados estabelecida\n\n";
} catch (Exception $e) {
    echo "✗ ERRO: Não foi possível conectar ao banco de dados\n";
    echo "  Mensagem: " . $e->getMessage() . "\n";
    exit(1);
}

// Tabelas críticas que devem existir
$criticalTables = [
    'users' => 'Tabela de usuários',
    'companies' => 'Tabela de empresas',
    'site_settings' => 'Tabela de configurações',
];

echo "Verificando tabelas críticas...\n";
$missingTables = [];
foreach ($criticalTables as $tableName => $description) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '" . addslashes($tableName) . "'");
        $exists = (bool) $stmt->fetch();
        if ($exists) {
            echo "  ✓ $tableName - OK\n";
        } else {
            echo "  ✗ $tableName - FALTANDO\n";
            $missingTables[] = $tableName;
        }
    } catch (PDOException $e) {
        echo "  ⚠ $tableName - ERRO AO VERIFICAR: " . $e->getMessage() . "\n";
    }
}

if (empty($missingTables)) {
    echo "\n✓ Todas as tabelas críticas existem!\n";
    exit(0);
}

echo "\n⚠ Encontradas " . count($missingTables) . " tabela(s) faltando\n";
echo "Tabelas faltando: " . implode(', ', $missingTables) . "\n\n";

// Verifica se schema.sql existe
$schemaFile = base_path('database/schema.sql');
if (!is_file($schemaFile)) {
    echo "✗ ERRO: Arquivo database/schema.sql não encontrado\n";
    exit(1);
}

echo "Tentando recuperar tabelas...\n";
echo "(Isso pode levar um tempo)\n\n";

// Lê o schema.sql
$sql = file_get_contents($schemaFile);
if (empty($sql)) {
    echo "✗ ERRO: Arquivo database/schema.sql está vazio\n";
    exit(1);
}

// Função para dividir statements SQL
function splitSqlStatements(string $sql): array {
    $statements = [];
    $statement = '';
    $inQuote = false;
    $quoteChar = '';

    for ($i = 0; $i < strlen($sql); $i++) {
        $char = $sql[$i];
        $prevChar = $i > 0 ? $sql[$i - 1] : '';
        $nextChar = $i < strlen($sql) - 1 ? $sql[$i + 1] : '';

        if (($char === '"' || $char === "'") && $prevChar !== '\\') {
            if (!$inQuote) {
                $inQuote = true;
                $quoteChar = $char;
            } elseif ($char === $quoteChar) {
                $inQuote = false;
                $quoteChar = '';
            }
        }

        $statement .= $char;

        if ($char === ';' && !$inQuote) {
            $trimmed = trim($statement);
            if (!empty($trimmed) && $trimmed !== ';') {
                $statements[] = $trimmed;
            }
            $statement = '';
        }
    }

    $trimmed = trim($statement);
    if (!empty($trimmed) && $trimmed !== ';') {
        $statements[] = $trimmed;
    }

    return $statements;
}

$statements = splitSqlStatements($sql);
echo "Encontrados " . count($statements) . " statements SQL\n\n";

// Executa apenas os statements que criam as tabelas faltantes
$executedCount = 0;
$failedCount = 0;
$skippedCount = 0;

foreach ($statements as $index => $statement) {
    if (empty(trim($statement))) {
        continue;
    }

    // Verifica se o statement é para uma tabela que falta
    $shouldExecute = false;
    foreach ($missingTables as $table) {
        if (stripos($statement, "CREATE TABLE") !== false && 
            stripos($statement, $table) !== false) {
            $shouldExecute = true;
            break;
        }
    }

    if (!$shouldExecute && count($missingTables) > 0) {
        $skippedCount++;
        continue;
    }

    try {
        $pdo->exec($statement);
        $executedCount++;
        echo ".";
    } catch (PDOException $e) {
        $failedCount++;
        echo "E";
        Logger::warning('Fix: Erro ao executar statement #' . ($index + 1) . ': ' . $e->getMessage());
    }

    // Quebra linha a cada 50 pontos
    if (($executedCount + $failedCount) % 50 === 0) {
        echo " (" . ($executedCount + $failedCount) . ")\n";
    }
}

echo "\n\n========================================\n";
echo "Resultado:\n";
echo "  Executados: " . $executedCount . "\n";
echo "  Falhados: " . $failedCount . "\n";
echo "  Pulados: " . $skippedCount . "\n";
echo "========================================\n\n";

// Valida novamente
echo "Validando tabelas novamente...\n";
$stillMissing = [];
foreach ($missingTables as $tableName) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '" . addslashes($tableName) . "'");
        $exists = (bool) $stmt->fetch();
        if ($exists) {
            echo "  ✓ $tableName - RECUPERADA COM SUCESSO\n";
        } else {
            echo "  ✗ $tableName - AINDA FALTANDO\n";
            $stillMissing[] = $tableName;
        }
    } catch (PDOException $e) {
        echo "  ⚠ $tableName - ERRO: " . $e->getMessage() . "\n";
        $stillMissing[] = $tableName;
    }
}

echo "\n";
if (empty($stillMissing)) {
    echo "✓ SUCESSO! Todas as tabelas foram recuperadas\n";
    exit(0);
} else {
    echo "✗ FALHA! Ainda há tabelas faltando: " . implode(', ', $stillMissing) . "\n";
    echo "  Entre em contato com o suporte\n";
    exit(1);
}
