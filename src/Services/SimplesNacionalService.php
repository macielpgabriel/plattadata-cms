<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CompanyTaxRepository;
use Throwable;

final class SimplesNacionalService
{
    private CompanyTaxRepository $repository;
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private int $cacheTtlHours;

    public function __construct()
    {
        $this->repository = new CompanyTaxRepository();
        $this->baseUrl = (string) config('simples_nacional.base_url', 'https://brasilapi.com.br/api/cnpj/v1');
        $this->apiKey = (string) config('simples_nacional.api_key', '');
        $this->timeout = (int) config('simples_nacional.timeout', 10);
        $this->cacheTtlHours = (int) config('simples_nacional.cache_ttl_hours', 24);
    }

    public function syncFromPayload(int $companyId, string $cnpj, array $data): array
    {
        $taxData = [
            'simples_opt_in' => $data['opcao_pelo_simples'] ?? null,
            'simples_since' => $data['data_opcao_pelo_simples'] ?? null,
            'mei_opt_in' => $data['opcao_pelo_mei'] ?? null,
            'mei_since' => $data['data_opcao_pelo_mei'] ?? null,
            'state_registrations' => [],
        ];

        if (isset($data['inscricoes_estaduais']) && is_array($data['inscricoes_estaduais'])) {
            foreach ($data['inscricoes_estaduais'] as $ie) {
                $taxData['state_registrations'][] = [
                    'uf' => $ie['uf'] ?? '',
                    'ie' => $ie['inscricao_estadual'] ?? '',
                    'active' => ($ie['ativo'] ?? $ie['situacao'] ?? '') === true || ($ie['ativo'] ?? $ie['situacao'] ?? '') === 'ATIVO',
                ];
            }
        }

        $this->repository->upsert($companyId, $cnpj, $taxData, 'BrasilAPI (Sync)');

        return $taxData;
    }

    /**
     * Fetch Simples Nacional and IE info for a company with caching.
     * Uses DB cache with configurable TTL (default 24 hours).
     */
    public function fetchAndCache(int $companyId, string $cnpj, bool $force = false): ?array
    {
        if (!$force && !$this->repository->needsRefresh($companyId, $this->cacheTtlHours)) {
            return $this->repository->findByCompanyId($companyId);
        }

        $cnpj = preg_replace('/\D+/', '', $cnpj) ?? '';

        $url = rtrim($this->baseUrl, '/') . '/' . $cnpj;
        $data = $this->fetchJson($url);

        if ((!$data || !isset($data['cnpj'])) && strpos($this->baseUrl, '/v2') !== false) {
            $v1Url = str_replace('/v2', '/v1', $this->baseUrl) . '/' . $cnpj;
            $data = $this->fetchJson($v1Url);
        }

        if (!$data || !isset($data['cnpj'])) {
            return null;
        }

        $taxData = [
            'simples_opt_in' => $data['opcao_pelo_simples'] ?? null,
            'simples_since' => $data['data_opcao_pelo_simples'] ?? null,
            'mei_opt_in' => $data['opcao_pelo_mei'] ?? null,
            'mei_since' => $data['data_opcao_pelo_mei'] ?? null,
            'state_registrations' => [],
        ];

        if (isset($data['inscricoes_estaduais']) && is_array($data['inscricoes_estaduais'])) {
            foreach ($data['inscricoes_estaduais'] as $ie) {
                $taxData['state_registrations'][] = [
                    'uf' => $ie['uf'] ?? '',
                    'ie' => $ie['inscricao_estadual'] ?? '',
                    'active' => ($ie['ativo'] ?? $ie['situacao'] ?? '') === true || ($ie['ativo'] ?? $ie['situacao'] ?? '') === 'ATIVO',
                ];
            }
        }

        $this->repository->upsert($companyId, $cnpj, $taxData, 'BrasilAPI');

        return $taxData;
    }

    public function getTaxData(int $companyId, string $cnpj, bool $force = false): ?array
    {
        return $this->fetchAndCache($companyId, $cnpj, $force);
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

        if ($this->apiKey) {
            $opts['http']['header'] .= "Authorization: Bearer {$this->apiKey}\r\n";
        }

        $content = @file_get_contents($url, false, stream_context_create($opts));

        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }
}