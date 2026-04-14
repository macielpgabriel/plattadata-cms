#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Script para gerar cache de configurações
 * 
 * Uso: php scripts/cache-config.php
 */

require __DIR__ . '/../bootstrap/app.php';

$cacheDir = base_path('storage/cache');
$cacheFile = $cacheDir . '/config.php';

// Criar diretório de cache se não existir
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$configDir = base_path('config');
$configs = [];

// Ler todos os arquivos de configuração
foreach (glob($configDir . '/*.php') as $file) {
    $key = basename($file, '.php');
    $configs[$key] = require $file;
}

// Salvar no arquivo de cache
$content = "<?php\n\n// Cache de configurações gerado em " . date('Y-m-d H:i:s') . "\n// Não editar manualmente\n\nreturn " . var_export($configs, true) . ";\n";

if (file_put_contents($cacheFile, $content)) {
    echo "✅ Cache de configurações gerado: $cacheFile\n";
    echo "   Tamanho: " . number_format(filesize($cacheFile) / 1024, 2) . " KB\n";
} else {
    echo "❌ Erro ao gerar cache de configurações\n";
    exit(1);
}
