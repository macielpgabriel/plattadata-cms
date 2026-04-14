#!/usr/bin/env php
<?php

declare(strict_types=1);

define('BASE_PATH', realpath(__DIR__ . '/..'));
chdir(BASE_PATH);

function base_path(string $path = ''): string
{
    return BASE_PATH . ($path ? DIRECTORY_SEPARATOR . $path : '');
}

require BASE_PATH . '/vendor/autoload.php';

use App\Core\Cache\FileCacheDriver;
use App\Core\Logger;

echo "=== Cache & Log Cleanup Script ===\n\n";

$cache = new FileCacheDriver();

echo "1. Limpando arquivos de cache expirados...\n";
$removed = $cache->gc();
echo "   Removidos: {$removed} arquivos expirados\n\n";

echo "2. Verificando arquivos restantes...\n";
$cachePath = BASE_PATH . '/storage/cache';
$files = glob($cachePath . '/*.cache');
$totalSize = 0;
foreach ($files as $file) {
    $totalSize += filesize($file);
}
echo "   Arquivos restantes: " . count($files) . "\n";
echo "   Tamanho total: " . number_format($totalSize / 1024, 2) . " KB\n\n";

echo "3. Detalhes dos arquivos:\n";
foreach ($files as $file) {
    $data = @unserialize(file_get_contents($file));
    $expiresAt = $data['expires_at'] ?? 0;
    $remaining = max(0, $expiresAt - time());
    $size = filesize($file);
    echo "   - " . basename($file) . " (" . number_format($size) . " bytes, expira em " . formatDuration($remaining) . ")\n";
}

echo "\n4. Limpando logs antigos (mais de 7 dias)...\n";
$logsRemoved = Logger::cleanupOldLogs(7);
echo "   Logs antigos removidos: {$logsRemoved}\n\n";

echo "5. Estatísticas do log:\n";
$stats = Logger::getStats();
echo "   Total de entradas: " . number_format($stats['total']) . "\n";
foreach ($stats['by_level'] as $level => $count) {
    if ($count > 0) {
        echo "   - {$level}: {$count}\n";
    }
}

echo "\n=== Concluído ===\n";

function formatDuration(int $seconds): string
{
    if ($seconds <= 0) return 'EXPIRADO';
    if ($seconds < 60) return "{$seconds}s";
    if ($seconds < 3600) return round($seconds / 60) . "m";
    if ($seconds < 86400) return round($seconds / 3600) . "h";
    return round($seconds / 86400) . "d";
}
