<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Services\ReceitaImportService;
use App\Core\Logger;

/**
 * Script CLI para rodar via Cron Job.
 * Exemplo: php scripts/worker_import.php /path/to/estabelecimentos.csv
 */

if (php_sapi_name() !== 'cli') {
    die("Este script deve ser executado via CLI.");
}

$file = $argv[1] ?? null;

if (!$file || !is_file($file)) {
    die("Uso: php worker_import.php [caminho_do_arquivo_csv]\n");
}

echo "Iniciando importação de $file...\n";
$service = new ReceitaImportService();
$total = $service->importEstabelecimentos($file);
echo "Finalizado! $total registros processados.\n";