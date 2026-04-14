#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Database;

echo "===========================================\n";
echo " CMS Plattadata - Reset de Schema\n";
echo "===========================================\n\n";

echo "Removendo arquivos de lock de migrations...\n";

$lockFiles = [
    'storage/.schema_location_completed',
    'storage/.schema_qsa_v1_completed',
    'storage/.setup_completed',
];

foreach ($lockFiles as $file) {
    $path = base_path($file);
    if (is_file($path)) {
        unlink($path);
        echo "  Removido: $file\n";
    }
}

echo "\nExecutando migrations...\n";

try {
    $pdo = Database::connection();
    echo "  Conexao com banco de dados: OK\n";

    $setupService = new \App\Services\SetupService();
    $reflection = new ReflectionClass($setupService);
    
    $methods = [
        'ensureBlacklistSchema',
        'ensureNotificationsSchema', 
        'ensurePasswordResetSchema',
        'ensureUserExtensionsSchema',
        'ensureLocationAndTaxSchema',
        'ensureFavoritesSchema',
        'ensureEnrichmentSchema',
    ];

    foreach ($methods as $methodName) {
        if ($reflection->hasMethod($methodName)) {
            $method = $reflection->getMethod($methodName);
            $method->setAccessible(true);
            
            echo "  Executando $methodName...\n";
            try {
                $method->invoke($setupService, $pdo);
                echo "    OK\n";
            } catch (Throwable $e) {
                echo "    ERRO: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\nVerificando tabelas...\n";
    
    $tables = [
        'states',
        'municipalities', 
        'cnpj_blacklist',
        'notification_logs',
        'password_reset_tokens',
        'user_favorites',
        'company_partners',
        'company_secondary_cnaes',
    ];

    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            echo "  $table: " . ($exists ? "EXISTE" : "NAO EXISTE") . "\n";
        } catch (Throwable $e) {
            echo "  $table: ERRO (" . $e->getMessage() . ")\n";
        }
    }

    echo "\n===========================================\n";
    echo " Concluido! Tente acessar as paginas novamente.\n";
    echo "===========================================\n";

} catch (Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
