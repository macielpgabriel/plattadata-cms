<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Services\IbgeService;

/**
 * Script para sincronizar todos os municípios brasileiros através da API do IBGE.
 * 
 * Uso:
 * php scripts/sync_municipalities.php           # Apenas lista de municípios
 * php scripts/sync_municipalities.php --population # Apenas população
 * php scripts/sync_municipalities.php --gdp        # Apenas PIB (Total + Per Capita)
 * php scripts/sync_municipalities.php --frota      # Apenas Frota de Veículos
 * php scripts/sync_municipalities.php --companies  # Apenas Unidades Locais (Empresas)
 * php scripts/sync_municipalities.php --ddd        # Apenas Mapeamento de DDD
 * php scripts/sync_municipalities.php --all        # Tudo
 */

if (php_sapi_name() !== 'cli') {
    die("Este script só pode ser executado via CLI.\n");
}

$opts = getopt('', ['population', 'gdp', 'frota', 'companies', 'ddd', 'all']);
$syncPop = isset($opts['population']) || isset($opts['all']);
$syncGdp = isset($opts['gdp']) || isset($opts['all']);
$syncFrota = isset($opts['frota']) || isset($opts['all']);
$syncCompanies = isset($opts['companies']) || isset($opts['all']);
$syncDdd = isset($opts['ddd']) || isset($opts['all']);
$syncList = (!isset($opts['population']) && !isset($opts['gdp']) && !isset($opts['frota']) && !isset($opts['companies']) && !isset($opts['ddd'])) || isset($opts['all']);

echo "========================================================\n";
echo "Iniciando sincronização de dados municipais...\n";
if ($syncList)      echo "- Lista de Municípios: ATIVO\n";
if ($syncPop)       echo "- População: ATIVO\n";
if ($syncGdp)       echo "- PIB (Total + Per Capita): ATIVO\n";
if ($syncFrota)     echo "- Frota de Veículos: ATIVO\n";
if ($syncCompanies) echo "- Empresas (CEMPRE): ATIVO\n";
if ($syncDdd)       echo "- DDD Principal: ATIVO\n";
echo "========================================================\n";

try {
    $service = new IbgeService();
    $startTotal = microtime(true);

    if ($syncList) {
        echo "\nSincronizando lista de municípios... ";
        $start = microtime(true);
        $count = $service->syncAllMunicipalities();
        $end = microtime(true);
        echo "OK! ({$count} municípios, " . round($end - $start, 2) . "s)\n";
    }

    if ($syncPop) {
        echo "Sincronizando população... ";
        $start = microtime(true);
        $count = $service->syncBulkPopulation();
        $end = microtime(true);
        echo "OK! ({$count} registros, " . round($end - $start, 2) . "s)\n";
    }

    if ($syncGdp) {
        echo "Sincronizando PIB (Total + Per Capita)... ";
        $start = microtime(true);
        $results = $service->syncBulkGdp(); 
        $end = microtime(true);
        echo "OK! ({$results['total']} registros, " . round($end - $start, 2) . "s)\n";
    }

    if ($syncFrota) {
        echo "Sincronizando frota de veículos... ";
        $start = microtime(true);
        $count = $service->syncBulkFrota();
        $end = microtime(true);
        echo "OK! ({$count} registros, " . round($end - $start, 2) . "s)\n";
    }

    if ($syncCompanies) {
        echo "Sincronizando empresas (CEMPRE)... ";
        $start = microtime(true);
        $count = $service->syncBulkCompanies();
        $end = microtime(true);
        echo "OK! ({$count} registros, " . round($end - $start, 2) . "s)\n";
    }

    if ($syncDdd) {
        echo "Mapeando DDDs principais... ";
        $start = microtime(true);
        $count = $service->syncBulkDdd();
        $end = microtime(true);
        echo "OK! ({$count} cidades mapeadas, " . round($end - $start, 2) . "s)\n";
    }

    $endTotal = microtime(true);
    echo "\n========================================================\n";
    echo "Sucesso! Sincronização concluída em " . round($endTotal - $startTotal, 2) . "s.\n";
    echo "========================================================\n";

} catch (Throwable $e) {
    echo "\nERRO FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
