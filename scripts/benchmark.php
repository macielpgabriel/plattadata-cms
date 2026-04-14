#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Script para medir tempo de carregamento
 * 
 * Uso: php scripts/benchmark.php
 */

$start = microtime(true);

// Simular um request HTTP
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'localhost';

require __DIR__ . '/../public/index.php';

$end = microtime(true);
$memory = memory_get_peak_usage(true);

$time = round(($end - $start) * 1000, 2);
$memoryMB = round($memory / 1024 / 1024, 2);

echo "========================================\n";
echo "📊 Benchmark do CMS Plattadata\n";
echo "========================================\n";
echo "⏱️  Tempo de carregamento: {$time} ms\n";
echo "💾 Memória usada: {$memoryMB} MB\n";
echo "========================================\n";
