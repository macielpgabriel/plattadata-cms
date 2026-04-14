<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\BcbService;
use App\Services\GoogleDriveService;
use App\Services\GoogleDriveServiceOAuth;

final class InfoApiController extends BaseApiController
{
    public function index(): never
    {
        $this->success([
            'name' => 'Plattadata CNPJ API',
            'version' => '1.0.0',
            'description' => 'API para consulta de CNPJ e dados empresariais brasileiros',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/v1',
                    'description' => 'Informações da API',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/health',
                    'description' => 'Verificação de saúde da API',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/cnpj',
                    'description' => 'Buscar CNPJ (parâmetro: cnpj)',
                    'parameters' => [
                        'cnpj' => 'CNPJ com 14 dígitos',
                    ],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/cnpj/{cnpj}',
                    'description' => 'Obter dados de um CNPJ específico',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/companies',
                    'description' => 'Listar empresas (com paginação)',
                    'parameters' => [
                        'page' => 'Número da página (padrão: 1)',
                        'per_page' => 'Itens por página (padrão: 20, máx: 100)',
                        'search' => 'Buscar por nome ou CNPJ',
                        'state' => 'Filtrar por UF',
                        'city' => 'Filtrar por cidade',
                    ],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/exchange-rates',
                    'description' => 'Cotação PTAX do dia',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/exchange-rates/{currency}/history',
                    'description' => 'Histórico de cotação (parâmetro: days)',
                    'parameters' => [
                        'currency' => 'Código da moeda (USD, EUR, etc)',
                        'days' => 'Número de dias (padrão: 30, máx: 365)',
                    ],
                ],
            ],
            'documentation' => '/api/docs',
            'rate_limits' => [
                'search' => '60 requests por minuto',
                'get' => '120 requests por minuto',
                'list' => '30 requests por minuto',
            ],
        ]);
    }

    public function health(): never
    {
        $healthy = true;
        $checks = [];

        try {
            \App\Core\Database::connection()->query('SELECT 1');
            $checks['database'] = 'healthy';
        } catch (\Throwable $e) {
            $checks['database'] = 'unhealthy';
            $healthy = false;
        }

        $checks['cache'] = is_dir(base_path('storage/cache')) ? 'healthy' : 'unhealthy';

        $response = [
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'timestamp' => date('c'),
        ];

        $this->json($response, $healthy ? 200 : 503);
    }

    public function exchangeRates(): never
    {
        $bcb = new BcbService();
        $repo = new \App\Repositories\ExchangeRateRepository();

        $indicators = $bcb->getEconomicIndicators();
        $dbRates = $repo->getLatestRates();
        
        $currencies = $indicators['currencies'] ?? [];
        if (!empty($dbRates)) {
            foreach ($currencies as &$curr) {
                $dbRate = array_filter($dbRates, fn($r) => $r['currency_code'] === $curr['code']);
                if (!empty($dbRate)) {
                    $dbRate = reset($dbRate);
                    $prevRate = $repo->getPreviousRate($curr['code'], $curr['data']);
                    if ($prevRate) {
                        $variacao = (($curr['venda'] - $prevRate['cotacao_venda']) / $prevRate['cotacao_venda']) * 100;
                        $curr['variacao'] = round($variacao, 2);
                    }
                }
            }
            unset($curr);
        }

        $this->success([
            'source' => 'BCB PTAX',
            'updated_at' => $indicators['updated_at'] ?? null,
            'database_updated_at' => $repo->getLastUpdateDate(),
            'currencies' => $currencies,
        ]);
    }

    public function exchangeRateHistory(array $params): never
    {
        $currency = strtoupper(trim($params['currency'] ?? 'USD'));
        $days = min(365, max(1, (int) ($_GET['days'] ?? 30)));

        $repo = new \App\Repositories\ExchangeRateRepository();
        $history = $repo->getRateHistory($currency, $days);

        if (empty($history)) {
            $bcb = new BcbService();
            $history = $bcb->getExchangeRateHistory($currency, $days);
        }

        $this->success([
            'currency' => $currency,
            'days' => $days,
            'count' => count($history),
            'data' => $history,
        ]);
    }

    public function refreshExchangeRates(): never
    {
        $this->checkRateLimit('exchange_refresh', 5, 60);

        try {
            $bcb = new BcbService();
            $rates = $bcb->fetchAndSaveLatestRates();

            // Clear cache for locations
            $cacheKey = "muni_extra_data_";
            $pdo = \App\Core\Database::connection();
            $stmt = $pdo->query("SELECT ibge_code FROM municipalities LIMIT 100");
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                \App\Core\Cache::forget($cacheKey . $row['ibge_code']);
            }

            $this->success([
                'currencies' => array_keys($rates),
                'count' => count($rates),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            $this->error('Failed to refresh exchange rates: ' . $e->getMessage(), 500);
        }
    }

    public function testDriveConnection(): never
    {
        $driveOAuth = new GoogleDriveServiceOAuth();
        $drive = new GoogleDriveService();
        
        if ($driveOAuth->isAuthenticated()) {
            $result = $driveOAuth->testConnection();
            
            if ($result['status'] === 'connected') {
                $this->success([
                    'status' => 'connected',
                    'type' => 'oauth',
                    'message' => 'Conexão com Google Drive (OAuth) funcionando!',
                    'user' => $result['user'] ?? null,
                    'storage' => $result['storageQuota'] ?? null,
                ]);
            } else {
                $this->error('Erro OAuth: ' . $result['message'], 500);
            }
        } elseif ($drive->isEnabled()) {
            $result = $drive->testConnection();
            
            if ($result['status'] === 'connected') {
                $this->success([
                    'status' => 'connected',
                    'type' => 'service_account',
                    'message' => 'Conexão com Google Drive (conta de serviço) funcionando!',
                    'user' => $result['user'] ?? null,
                    'storage' => $result['storageQuota'] ?? null,
                    'config' => [
                        'folder_id' => !empty(env('GOOGLE_DRIVE_FOLDER_ID')),
                    ],
                ]);
            } else {
                $this->error('Erro: ' . $result['message'], 500);
            }
        } else {
            $this->error('Google Drive não configurado. Configure OAuth no .env ou credenciais de service account.', 503);
        }
    }
}
