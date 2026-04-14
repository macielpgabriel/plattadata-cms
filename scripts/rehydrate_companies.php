<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Simular ambiente CLI para evitar problemas de sessão
$_SERVER['REQUEST_URI'] = '/cli';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once __DIR__ . '/../bootstrap/app.php';

// Forçar debug ON depois do bootstrap
$_ENV['APP_DEBUG'] = 'true';
$_SERVER['APP_DEBUG'] = 'true';
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\MunicipalityRepository;

/**
 * Script de patch para re-hidratar dados faltantes em empresas antigas.
 * 
 * Corrige: municipal_ibge_code, cidade/UF, CNAE principal
 * Usando dados do raw_data JSON + fallback na tabela municipalities.
 * 
 * Uso:
 * php scripts/rehydrate_companies.php [--dry-run] [--limit=N] [--batch=N]
 * 
 * --dry-run   Simula sem fazer alterações
 * --limit=N   Limite total de empresas processadas (padrão: 0 = todas)
 * --batch=N   Tamanho do batch por transação (padrão: 100)
 */

if (php_sapi_name() !== 'cli') {
    die("Este script só pode ser executado via CLI.\n");
}

$opts = getopt('', ['dry-run', 'limit::', 'batch::']);
$dryRun = isset($opts['dry-run']);
$limit = isset($opts['limit']) ? (int) $opts['limit'] : 0;
$batchSize = isset($opts['batch']) ? (int) $opts['batch'] : 100;

// Forçar CLI mode
$_SERVER['REQUEST_URI'] = '/cli';

echo "========================================================\n";
echo "Re-hidratação de empresas antigas\n";
echo "- Dry-run: " . ($dryRun ? 'SIM' : 'NÃO') . "\n";
echo "- Limite: " . ($limit > 0 ? $limit : 'TODAS') . "\n";
echo "- Batch size: {$batchSize}\n";
echo "========================================================\n\n";

$db = Database::connection();
$muniRepo = new MunicipalityRepository();

function normalizeCnaeCode(?string $cnae): ?string {
    if (empty($cnae)) return null;
    $digits = preg_replace('/\D/', '', $cnae);
    if (strlen($digits) >= 2) {
        return substr($digits, 0, 7);
    }
    return null;
}

$whereClause = 'WHERE (municipal_ibge_code IS NULL OR municipal_ibge_code = 0 OR cnae_main_code IS NULL OR cnae_main_code = "") AND is_hidden = 0 AND raw_data IS NOT NULL';
$params = [];

if ($limit > 0) {
    $whereClause .= ' LIMIT :limit';
    $params['limit'] = $limit;
}

$sql = "SELECT id, cnpj, city, state, raw_data FROM companies {$whereClause}";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v, $v === 'limit' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($companies)) {
    echo "Nenhuma empresa precisa de re-hidratação.\n";
    exit(0);
}

echo "Encontradas " . count($companies) . " empresas para processar...\n\n";

$updated = 0;
$errors = 0;
$skipped = 0;
$batch = [];

foreach ($companies as $company) {
    $companyId = (int) $company['id'];
    $rawData = json_decode($company['raw_data'], true);
    
    if (!is_array($rawData)) {
        $skipped++;
        continue;
    }

    $updates = [];
    $params = ['id' => $companyId];

    // 1. Tentar resolver municipal_ibge_code do raw_data
    $ibgeCode = null;
    
    // Prioridade: codigo_municipio_ibge direto
    if (empty($ibgeCode)) {
        $ibgeCode = $rawData['codigo_municipio_ibge'] ?? null;
    }
    // Do _cep_details
    if (empty($ibgeCode) && !empty($rawData['_cep_details'])) {
        $ibgeCode = $rawData['_cep_details']['ibge_code'] ?? $rawData['_cep_details']['ibge'] ?? null;
    }
    // Do _municipality_details
    if (empty($ibgeCode) && !empty($rawData['_municipality_details'])) {
        $ibgeCode = $rawData['_municipality_details']['id'] ?? null;
    }
    // Do payload direto (alguns dados têm codigo_municipio no root)
    if (empty($ibgeCode)) {
        $ibgeCode = $rawData['codigo_municipio'] ?? null;
    }
    // Da chave 'ibge' no payload
    if (empty($ibgeCode)) {
        $ibgeCode = $rawData['ibge'] ?? null;
    }

    if (!empty($ibgeCode)) {
        $ibgeCode = (int) preg_replace('/\D/', '', (string) $ibgeCode);
        if ($ibgeCode > 0) {
            $updates[] = 'municipal_ibge_code = :ibge_code';
            $params['ibge_code'] = $ibgeCode;
        }
    }

    // 2. Fallback: buscar na tabela municipalities por city + state
    if (empty($updates)) {
        $city = trim($company['city'] ?? '');
        $state = strtoupper(trim($company['state'] ?? ''));
        
        if (!empty($city) && !empty($state) && strlen($state) === 2) {
            $muni = $muniRepo->findByNameAndState($city, $state);
            if (!empty($muni['ibge_code'])) {
                $updates[] = 'municipal_ibge_code = :ibge_code_fallback';
                $params['ibge_code_fallback'] = (int) $muni['ibge_code'];
            }
        }
    }

    // 3. Normalizar CNAE principal
    $cnae = $rawData['cnae_fiscal'] ?? $rawData['cnae'] ?? null;
    if (!empty($cnae)) {
        $normalizedCnae = normalizeCnaeCode($cnae);
        if ($normalizedCnae) {
            $updates[] = 'cnae_main_code = :cnae_code';
            $params['cnae_code'] = $normalizedCnae;
        }
    }

    if (empty($updates)) {
        $skipped++;
        continue;
    }

    $batch[] = ['id' => $companyId, 'updates' => $updates, 'params' => $params];

    // Processar batch
    if (count($batch) >= $batchSize) {
        $batchUpdated = processBatch($db, $batch, $dryRun);
        $updated += $batchUpdated['success'];
        $errors += $batchUpdated['error'];
        $batch = [];
        
        echo "Processado: {$batchUpdated['success']} atualizados, {$batchUpdated['error']} erros. Total: {$updated}\n";
    }
}

// Processar batch final
if (!empty($batch)) {
    $batchUpdated = processBatch($db, $batch, $dryRun);
    $updated += $batchUpdated['success'];
    $errors += $batchUpdated['error'];
    echo "Processado: {$batchUpdated['success']} atualizados, {$batchUpdated['error']} erros. Total: {$updated}\n";
}

echo "\n========================================================\n";
echo "Resumo:\n";
echo "- Atualizados: {$updated}\n";
echo "- Erros: {$errors}\n";
echo "- Ignorados (sem dados): {$skipped}\n";
echo "========================================================\n";

if ($dryRun) {
    echo "\n[DRY-RUN] Nenhuma alteração foi aplicada ao banco de dados.\n";
}

function processBatch(PDO $db, array $batch, bool $dryRun): array {
    $success = 0;
    $error = 0;

    foreach ($batch as $item) {
        $sql = "UPDATE companies SET " . implode(', ', $item['updates']) . ", updated_at = NOW() WHERE id = :id";
        
        if ($dryRun) {
            echo "  [DRY-RUN] ID {$item['id']}: {$sql}\n";
            $success++;
        } else {
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute($item['params']);
                $success++;
            } catch (Throwable $e) {
                Logger::error("Rehydrate error ID {$item['id']}: " . $e->getMessage());
                $error++;
            }
        }
    }

    return ['success' => $success, 'error' => $error];
}