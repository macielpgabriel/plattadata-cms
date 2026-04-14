<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Cache;
use App\Services\BcbService;
use App\Repositories\ExchangeRateRepository;

final class EconomicController
{
    private const CACHE_KEY_RATES = 'economic_rates';
    private const CACHE_KEY_INDICATORS = 'economic_indicators';
    private const CACHE_TTL = 300;

    public function index(): void
    {
        $startTime = microtime(true);

        $cachedData = Cache::get(self::CACHE_KEY_RATES);
        if ($cachedData !== null) {
            $exchangeRates = $cachedData;
        } else {
            $bcb = new BcbService();
            $repo = new ExchangeRateRepository();
            $exchangeRates = $this->getExchangeRates($bcb, $repo);
            Cache::set(self::CACHE_KEY_RATES, $exchangeRates, self::CACHE_TTL);
        }

        $cachedIndicators = Cache::get(self::CACHE_KEY_INDICATORS);
        if ($cachedIndicators !== null) {
            $economicIndicators = $cachedIndicators;
        } else {
            $bcb = new BcbService();
            $economicIndicators = $this->getEconomicIndicators($bcb);
            Cache::set(self::CACHE_KEY_INDICATORS, $economicIndicators, self::CACHE_TTL);
        }

        $history = Cache::get('economic_history');
        if ($history === null) {
            $repo = new ExchangeRateRepository();
            $history = [];
            foreach (['USD', 'EUR', 'GBP'] as $code) {
                $history[$code] = $repo->getRateHistory($code, 30);
            }
            Cache::set('economic_history', $history, 1800);
        }

$schemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'Dataset',
            'name' => 'Indicadores Economicos do Brasil',
            'description' => 'Dados atualizados sobre cotacoes de cambio, taxas de juros, inflacao e outros indicadores economicos brasileiros.',
            'url' => config('app.url') . '/indicadores-economicos',
            'creator' => [
                '@type' => 'Organization',
                'name' => 'Banco Central do Brasil',
                'url' => 'https://www.bcb.gov.br',
            ],
            'license' => 'https://creativecommons.org/licenses/by/4.0/',
        ];

        View::render('public/economic', [
            'title' => 'Indicadores Economicos do Brasil',
            'currencies' => $exchangeRates['currencies'],
            'economicIndicators' => $economicIndicators,
            'dbUpdatedAt' => $exchangeRates['db_updated_at'],
            'dbFetchedAt' => $exchangeRates['db_fetched_at'],
            'fromDatabase' => $exchangeRates['from_database'],
            'history' => $history,
            'metaRobots' => 'index,follow',
            'schemaData' => $schemaData,
        ]);
    }

    private function getExchangeRates(BcbService $bcb, ExchangeRateRepository $repo): array
    {
        $dbRates = $repo->getLatestRates();

        if (empty($dbRates)) {
            $bcb->fetchAndSaveAllRates();
            $dbRates = $repo->getLatestRates();
        }

        if (empty($dbRates)) {
            return ['currencies' => [], 'from_database' => false, 'db_updated_at' => null, 'db_fetched_at' => null];
        }

        $result = [
            'currencies' => [],
            'from_database' => true,
            'db_updated_at' => $repo->getLastUpdateDate(),
            'db_fetched_at' => $repo->getLastFetchedAt(),
        ];

        foreach ($dbRates as $rate) {
            $prevRate = $repo->getPreviousRate($rate['currency_code'], $rate['data_cotacao']);
            $variacao = null;
            if ($prevRate && (float) $prevRate['cotacao_venda'] > 0) {
                $variacao = round((($rate['cotacao_venda'] - $prevRate['cotacao_venda']) / $prevRate['cotacao_venda']) * 100, 2);
            }

            $result['currencies'][] = [
                'code' => $rate['currency_code'],
                'name' => $rate['currency_name'],
                'compra' => (float) $rate['cotacao_compra'],
                'venda' => (float) $rate['cotacao_venda'],
                'paridade' => (float) ($rate['paridade_compra'] ?? 0),
                'data' => $rate['data_cotacao'],
                'variacao' => $variacao,
            ];
        }

        return $result;
    }

    private function getEconomicIndicators(BcbService $bcb): array
    {
        $indicators = $bcb->getIndicatorsFromDatabase();
        return $indicators ?: [];
    }
}
