#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Script para limpar todos os caches
 * 
 * Uso: php scripts/clear-cache.php
 */

require __DIR__ . '/../bootstrap/app.php';

$caches = [
    base_path('storage/cache/config.php'),
];

$cleared = 0;

foreach ($caches as $cache) {
    if (is_file($cache)) {
        if (unlink($cache)) {
            echo "✅ Removido: " . basename($cache) . "\n";
            $cleared++;
        } else {
            echo "❌ Erro ao remover: " . basename($cache) . "\n";
        }
    }
}

// Also clear OPcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache limpo\n";
}

echo "\n📊 Resumo: {$cleared} arquivo(s) de cache removido(s)\n";
