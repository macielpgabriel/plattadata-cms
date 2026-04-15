<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Logger;
use App\Repositories\ExchangeRateRepository;
use App\Repositories\EconomicIndicatorRepository;
use Throwable;

/**
 * Service to interact with Banco Central do Brasil (BCB) APIs.
 * Provides economic data such as PTAX exchange rates and economic indicators.
 */
final class BcbService
{
    private string $baseUrl;
    private int $timeout;
    private ExchangeRateRepository $repository;
    private EconomicIndicatorRepository $indicatorRepository;
    private array $currencyNames;
    private array $sgsIndicators;

    public function __construct()
    {
        $this->baseUrl = (string) config('app.bcb.base_url', 'https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata');
        $this->timeout = (int) config('app.bcb.timeout', 5);
        $this->repository = new ExchangeRateRepository();
        $this->indicatorRepository = new EconomicIndicatorRepository();
        $this->currencyNames = [
            'USD' => 'Dolar Americano',
            'EUR' => 'Euro',
            'GBP' => 'Libra Esterlina',
            'JPY' => 'Iene Japones',
            'CHF' => 'Franco Suico',
            'CAD' => 'Dolar Canadense',
            'AUD' => 'Dolar Australiano',
            'CNY' => 'Yuan Chines',
            'ARS' => 'Peso Argentino',
            'CLP' => 'Peso Chileno',
            'MXN' => 'Peso Mexicano',
            'PYG' => 'Guarani Paraguaio',
            'UYU' => 'Peso Uruguaio',
        ];
        $this->sgsIndicators = [
            'selic' => ['code' => 432, 'name' => 'Taxa SELIC', 'unit' => '%', 'description' => 'Taxa basica de juros'],
            'cdi' => ['code' => 4390, 'name' => 'CDI', 'unit' => '%', 'description' => 'Taxa referencial overnight'],
            'ipca' => ['code' => 27879, 'name' => 'IPCA', 'unit' => '%', 'description' => 'Inflacao acumulada 12 meses'],
            'ipca_mensal' => ['code' => 433, 'name' => 'IPCA Mensal', 'unit' => '%', 'description' => 'Inflacao mensal'],
            'inpc' => ['code' => 188, 'name' => 'INPC', 'unit' => '%', 'description' => 'Inflacao dos mais pobres'],
            'igpm' => ['code' => 189, 'name' => 'IGP-M', 'unit' => '%', 'description' => 'Indice Geral de Precos'],
            'igpdi' => ['code' => 191, 'name' => 'IGP-DI', 'unit' => '%', 'description' => 'Indice Geral de Precos - DI'],
            'ppcf' => ['code' => 7458, 'name' => 'PCCF', 'unit' => '%', 'description' => 'Precos ao Produtor - CF'],
            'pcc' => ['code' => 7449, 'name' => 'PCC', 'unit' => '%', 'description' => 'Precos ao Produtor - C'],
            'piubf' => ['code' => 7459, 'name' => 'PIUBF', 'unit' => '%', 'description' => 'IPA-F - Bens Finais'],
            'piub' => ['code' => 7450, 'name' => 'PIUB', 'unit' => '%', 'description' => 'IPA - Bens Intermediarios'],
            'pib' => ['code' => 4380, 'name' => 'PIB Trimestral', 'unit' => '%', 'description' => 'Crescimento trim. acumul. 4 trimes.'],
            'producao_fisica' => ['code' => 2183, 'name' => 'Prod. Industrial', 'unit' => '%', 'description' => 'Producao industrial mensal'],
            'vendas_rec' => ['code' => 1455, 'name' => 'Vendas Varejo', 'unit' => '%', 'description' => 'Vendas no varejo ampliado'],
            'divida_liquida' => ['code' => 1379, 'name' => 'Div. Liquida', 'unit' => '% PIB', 'description' => 'Divida liquida setor publico'],
            'poupanca' => ['code' => 195, 'name' => 'Poupanca', 'unit' => '%', 'description' => 'Rendimento da poupanca'],
            'tr' => ['code' => 226, 'name' => 'TR', 'unit' => '%', 'description' => 'Taxa Referencial'],
            'taxa_longa' => ['code' => 4392, 'name' => 'Taxa Longa', 'unit' => '%', 'description' => 'Taxa de juros - Longo prazo'],
            'inadimplencia_pf' => ['code' => 21112, 'name' => 'Inadimp. PF', 'unit' => '%', 'description' => 'Inadimplencia - Pessoas fisicas'],
        ];
    }

    public function getAllLatestRates(): array
    {
        $cacheKey = "bcb_all_rates";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->fetchRatesFromApi();
        
        if (!empty($result)) {
            Cache::set($cacheKey, $result, 14400);
        }
        
        return $result;
    }

    public function fetchAndSaveLatestRates(): array
    {
        $result = [];
        
        try {
            $rates = $this->getAllLatestRates();
            
            foreach ($rates as $code => $rate) {
                $result[$code] = [
                    'name' => $rate['name'] ?? $code,
                    'buy' => $rate['cotacaoCompra'] ?? null,
                    'sell' => $rate['cotacaoVenda'] ?? null,
                ];
                
                if (!empty($rate['cotacaoVenda'])) {
                    $this->saveToDatabase($code, $result[$code]['name'], [
                        'cotacaoCompra' => $rate['cotacaoCompra'] ?? null,
                        'cotacaoVenda' => $rate['cotacaoVenda'] ?? null,
                        'dataCotacao' => $rate['dataCotacao'] ?? date('Y-m-d'),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Logger::error('fetchAndSaveLatestRates error: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    private function fetchRatesFromApi(): array
    {
        $mainCurrencies = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD'];
        $result = [];

        foreach ($mainCurrencies as $currency) {
            $rate = $this->getLatestExchangeRateFast($currency);
            if ($rate) {
                $result[$currency] = $rate;
                $result[$currency]['name'] = $this->currencyNames[$currency] ?? $currency;
            }
        }

        if (!empty($result)) {
            return $result;
        }

        for ($i = 0; $i <= 2; $i++) {
            $date = date('m-d-Y', strtotime("-{$i} day"));
            $url = "{$this->baseUrl}/CotacaoMoedaDia(dataCotacao='{$date}')?\$filter=tipoBoletim%20eq%20'Fechamento'&\$format=json&\$orderby=dataCotacao%20desc";

            try {
                $data = $this->fetchJson($url);
                if ($data && !empty($data['value'])) {
                    $ratesByCurrency = [];
                    foreach ($data['value'] as $rate) {
                        $codMoeda = $rate['codMoeda'] ?? null;
                        if ($codMoeda && !isset($ratesByCurrency[$codMoeda])) {
                            $ratesByCurrency[$codMoeda] = $rate;
                        }
                    }
                    
                    $result = [];
                    foreach ($this->currencyNames as $code => $name) {
                        if (isset($ratesByCurrency[$code])) {
                            $result[$code] = $ratesByCurrency[$code];
                            $result[$code]['name'] = $name;
                        }
                    }
                    
                    return $result;
                }
            } catch (Throwable $e) {
                Logger::error('BCB API error: ' . $e->getMessage());
            }
        }

        return [];
    }

    public function getLatestExchangeRate(string $currency = 'USD'): ?array
    {
        $cacheKey = "bcb_rate_{$currency}_" . date('Y-m-d_H');
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $currencyName = $this->currencyNames[$currency] ?? $currency;

        for ($i = 0; $i <= 5; $i++) {
            $date = date('m-d-Y', strtotime("-{$i} day"));
            $url = "{$this->baseUrl}/CotacaoMoedaDia(moeda='{$currency}',dataCotacao='{$date}')?\$top=1&\$format=json";

            try {
                $data = $this->fetchJson($url);
                if ($data && !empty($data['value'])) {
                    $rate = $data['value'][0];
                    Cache::set($cacheKey, $rate, 3600);
                    
                    $this->saveToDatabase($currency, $currencyName, $rate);
                    
                    return $rate;
                }
            } catch (Throwable $e) {
            }
        }

        return null;
    }

    private function getLatestExchangeRateFast(string $currency): ?array
    {
        $cacheKey = "bcb_rate_fast_{$currency}_" . date('Y-m-d_H');
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $date = date('m-d-Y');
        $url = "{$this->baseUrl}/CotacaoMoedaDia(moeda='{$currency}',dataCotacao='{$date}')?\$top=1&\$format=json";

        try {
            $data = $this->fetchJsonFast($url);
            if ($data && !empty($data['value'])) {
                $rate = $data['value'][0];
                Cache::set($cacheKey, $rate, 3600);
                return $rate;
            }
        } catch (Throwable $e) {
        }

        return null;
    }

    private function fetchJsonFast(string $url): ?array
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 3,
                'header' => "Accept: application/json\r\nUser-Agent: CMS-Empresarial/1.0\r\n"
            ]
        ];

        $content = @file_get_contents($url, false, stream_context_create($opts));
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function getExchangeRateRange(string $currency, string $startDate, string $endDate): array
    {
        $start = date('m-d-Y', strtotime($startDate));
        $end = date('m-d-Y', strtotime($endDate));
        $url = "{$this->baseUrl}/CotacaoMoedaPeriodo(moeda='{$currency}',dataInicial='{$start}',dataFinalCotacao='{$end}')?\$format=json";
        
        try {
            $data = $this->fetchJson($url);
            return $data['value'] ?? [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getExchangeRateHistory(string $currency, int $days = 30): array
    {
        $endDate = date('m-d-Y');
        $startDate = date('m-d-Y', strtotime("-{$days} days"));
        
        return $this->getExchangeRateRange($currency, $startDate, $endDate);
    }

    public function getEconomicIndicators(): array
    {
        $cacheKey = "bcb_indicators_" . date('Y-m-d');
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $allRates = $this->getAllLatestRates();
        
        $currencies = [];
        foreach ($this->currencyNames as $code => $name) {
            if (isset($allRates[$code]) && !empty($allRates[$code]['cotacaoCompra'])) {
                $data = $allRates[$code];
                $this->saveToDatabase($code, $name, $data);
                $currencies[] = [
                    'code' => $code,
                    'name' => $name,
                    'compra' => (float) $data['cotacaoCompra'],
                    'venda' => (float) $data['cotacaoVenda'],
                    'paridade' => (float) ($data['paridadeCompra'] ?? 0),
                    'data' => $data['dataCotacao'] ?? null,
                ];
            }
        }

        $indicators = [
            'currencies' => $currencies,
            'updated_at' => date('Y-m-d H:i:s'),
            'last_db_update' => $this->repository->getLastUpdateDate(),
        ];

        Cache::set($cacheKey, $indicators, 3600);
        return $indicators;
    }

    public function saveToDatabase(string $currencyCode, string $currencyName, array $rate): bool
    {
        try {
            return $this->repository->saveRate($rate, $currencyCode, $currencyName);
        } catch (Throwable $e) {
            Logger::error('BcbService saveToDatabase error: ' . $e->getMessage());
            return false;
        }
    }

    public function getFromDatabase(): array
    {
        $rates = $this->repository->getLatestRates();
        
        $result = ['currencies' => [], 'updated_at' => null, 'from_database' => true];
        
        foreach ($rates as $rate) {
            $result['currencies'][] = [
                'code' => $rate['currency_code'],
                'name' => $rate['currency_name'],
                'compra' => (float) $rate['cotacao_compra'],
                'venda' => (float) $rate['cotacao_venda'],
                'paridade' => (float) ($rate['paridade_compra'] ?? 0),
                'data' => $rate['data_cotacao'],
            ];
        }
        
        $result['updated_at'] = $this->repository->getLastUpdateDate();
        
        return $result;
    }

    public function getHistoryFromDatabase(string $currencyCode, int $days = 30): array
    {
        return $this->repository->getRateHistory($currencyCode, $days);
    }

    public function fetchAndSaveAllRates(): array
    {
        $allRates = $this->getAllLatestRates();
        
        $saved = 0;
        foreach ($this->currencyNames as $code => $name) {
            if (isset($allRates[$code]) && !empty($allRates[$code]['cotacaoCompra'])) {
                if ($this->saveToDatabase($code, $name, $allRates[$code])) {
                    $saved++;
                }
            }
        }
        return ['saved' => $saved, 'total' => count($this->currencyNames)];
    }

    public function cleanupOldRates(int $keepDays = 90): int
    {
        return $this->repository->cleanupOldRates($keepDays);
    }

    public function getSgsIndicators(): array
    {
        $cacheKey = "bcb_sgs_indicators_" . date('Y-m-d');
        $cached = Cache::get($cacheKey);
        if ($cached !== null && !empty($cached)) {
            return $cached;
        }

        // Prioriza leitura local para evitar travar páginas públicas em cache frio.
        $dbIndicators = $this->getIndicatorsFromDatabase();
        if (!empty($dbIndicators)) {
            Cache::set($cacheKey, $dbIndicators, 3600);
            return $dbIndicators;
        }

        $indicators = $this->fetchSgsFromApiWithRetry();

        if (empty($indicators)) {
            // Mantém shape compatível para quem espera fetched_at/source.
            foreach ($dbIndicators as $key => $data) {
                $indicators[$key] = [
                    'code' => $key,
                    'name' => $data['name'],
                    'value' => $data['value'],
                    'unit' => $data['unit'],
                    'period' => $data['period'],
                    'description' => $data['description'] ?? '',
                    'fetched_at' => date('Y-m-d H:i:s'),
                    'source' => 'database_fallback',
                ];
            }
        } else {
            $this->saveIndicatorsToDatabase($indicators);
            $this->updateApiStatus('sgs', true);
        }

        if (empty($indicators)) {
            $indicators = $this->getIndicadoresFallback();
            $this->updateApiStatus('sgs', false);
        }

        Cache::set($cacheKey, $indicators, 3600);
        return $indicators;
    }

    private function fetchSgsFromApiWithRetry(int $maxRetries = 0): array
    {
        $indicators = [];
        $baseUrl = 'https://api.bcb.gov.br/dados/serie/bcdata.sgs';
        $deadline = microtime(true) + 8.0;
        $priorityKeys = ['selic', 'cdi', 'ipca', 'pib', 'inadimplencia_pf', 'tr'];
        $priorityIndicators = [];
        foreach ($priorityKeys as $key) {
            if (isset($this->sgsIndicators[$key])) {
                $priorityIndicators[$key] = $this->sgsIndicators[$key];
            }
        }

        foreach ($priorityIndicators as $key => $meta) {
            if (microtime(true) >= $deadline) {
                break;
            }

            $code = $meta['code'];
            $url = "{$baseUrl}.{$code}/dados?formato=json&dataInicial=01/01/2024&dataFinal=" . date('d/m/Y');

            try {
                $data = $this->fetchJsonWithRetry($url, $maxRetries);
                if ($data && is_array($data)) {
                    $lastValue = end($data);
                    if ($lastValue) {
                        $indicators[$key] = [
                            'code' => $key,
                            'name' => $meta['name'],
                            'value' => (float) ($lastValue['valor'] ?? 0),
                            'unit' => $meta['unit'],
                            'period' => $lastValue['data'] ?? null,
                            'description' => $meta['description'],
                            'fetched_at' => date('Y-m-d H:i:s'),
                            'api_version' => 'bcdata',
                        ];
                    }
                }
            } catch (Throwable $e) {
                Logger::error("BCB SGS Error for {$key}: " . $e->getMessage());
            }
        }

        return $indicators;
    }

    private function fetchJsonWithRetry(string $url, int $maxRetries = 2): ?array
    {
        $delays = [1, 2];

        for ($i = 0; $i <= $maxRetries; $i++) {
            if ($i > 0) {
                sleep($delays[$i - 1] ?? 1);
            }

            try {
                $data = $this->fetchJson($url);
                if ($data !== null) {
                    return $data;
                }
            } catch (Throwable $e) {
                if ($i === $maxRetries) {
                    throw $e;
                }
            }
        }

        return null;
    }

    private function saveIndicatorsToDatabase(array $indicators): void
    {
        foreach ($indicators as $key => $indicator) {
            $meta = $this->sgsIndicators[$key] ?? null;
            if ($meta) {
                $data = [
                    'valor' => $indicator['value'],
                    'periodo' => $indicator['period'],
                ];
                try {
                    $this->indicatorRepository->saveIndicator($data, $key, $meta['name'], $meta['unit']);
                } catch (Throwable $e) {
                    Logger::error("Error saving indicator {$key}: " . $e->getMessage());
                }
            }
        }
    }

    private function updateApiStatus(string $api, bool $available): void
    {
        $cacheKey = "bcb_api_status_{$api}";
        Cache::set($cacheKey, [
            'available' => $available,
            'checked_at' => date('Y-m-d H:i:s'),
        ], 86400);
    }

    public function getApiStatus(string $api = 'sgs'): array
    {
        $cacheKey = "bcb_api_status_{$api}";
        $status = Cache::get($cacheKey);

        if ($status === null) {
            $status = ['available' => null, 'checked_at' => null];
        }

        return $status;
    }

    private function getIndicadoresFallback(): array
    {
        return [
            'selic' => ['code' => 'selic', 'name' => 'Taxa SELIC', 'value' => 14.25, 'unit' => '%', 'period' => date('Y-m'), 'description' => 'Taxa basica de juros (estimativa)', 'fetched_at' => date('Y-m-d H:i:s'), 'source' => 'fallback'],
            'cdi' => ['code' => 'cdi', 'name' => 'CDI', 'value' => 14.15, 'unit' => '%', 'period' => date('Y-m'), 'description' => 'Taxa referencial overnight (estimativa)', 'fetched_at' => date('Y-m-d H:i:s'), 'source' => 'fallback'],
            'ipca' => ['code' => 'ipca', 'name' => 'IPCA (12 meses)', 'value' => 5.0, 'unit' => '%', 'period' => date('Y-m'), 'description' => 'Inflacao acumulada 12 meses (estimativa)', 'fetched_at' => date('Y-m-d H:i:s'), 'source' => 'fallback'],
            'pib' => ['code' => 'pib', 'name' => 'PIB Trimestral', 'value' => 2.1, 'unit' => '%', 'period' => date('Y-m'), 'description' => 'Crescimento trim. acumul. 4 trimes. (estimativa)', 'fetched_at' => date('Y-m-d H:i:s'), 'source' => 'fallback'],
            'dolar' => ['code' => 'dolar', 'name' => 'Dolar PTAX', 'value' => 5.25, 'unit' => 'R$', 'period' => date('Y-m-d'), 'description' => 'Taxa de cambio PTAX (estimativa)', 'fetched_at' => date('Y-m-d H:i:s'), 'source' => 'fallback'],
        ];
    }

    public function fetchAndSaveIndicators(): array
    {
        $indicators = $this->getSgsIndicators();

        $saved = 0;
        foreach ($indicators as $key => $indicator) {
            $meta = $this->sgsIndicators[$key] ?? null;
            if ($meta) {
                $data = [
                    'valor' => $indicator['value'],
                    'periodo' => $indicator['period'],
                ];
                if ($this->indicatorRepository->saveIndicator($data, $key, $meta['name'], $meta['unit'])) {
                    $saved++;
                }
            }
        }

        return ['saved' => $saved, 'total' => count($this->sgsIndicators)];
    }

    public function getIndicatorsFromDatabase(): array
    {
        $dbIndicators = $this->indicatorRepository->getLatestIndicators();

        $result = [];
        foreach ($dbIndicators as $row) {
            $key = $row['indicator_code'];
            $meta = $this->sgsIndicators[$key] ?? null;
            $result[$key] = [
                'code' => $key,
                'name' => $row['indicator_name'],
                'value' => (float) $row['indicator_value'],
                'unit' => $row['indicator_unit'],
                'period' => $row['indicator_period'],
                'date' => $row['data_referencia'],
                'description' => $meta['description'] ?? '',
            ];
        }

        return $result;
    }

    private function fetchJson(string $url): ?array
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => "Accept: application/json\r\nUser-Agent: CMS-Empresarial/1.0\r\n"
            ]
        ];

        $content = @file_get_contents($url, false, stream_context_create($opts));
        if ($content === false) {
            return null;
        }

        return json_decode($content, true);
    }
}
