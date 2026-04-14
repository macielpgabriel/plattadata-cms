<?php

declare(strict_types=1);

namespace App\Services\Cnpj;

use App\Core\Logger;

final class CnpjApiService
{
    private array $fallbackChain;
    private int $timeout;

    private const PROVIDER_ENDPOINTS = [
        'brasilapi' => 'https://brasilapi.com.br/api/cnpj/v1/',
        'receitaws'  => 'https://receitaws.com.br/v1/cnpj/',
        'cnpjws'     => 'https://api.cnpj.ws/v1/cnpj/',
        'opencnpj'   => 'https://api.opencnpj.org/',
    ];

    public function __construct()
    {
        $chain = (string) config('app.cnpj.fallback_chain', 'brasilapi,receitaws,cnpjws,opencnpj');
        $this->fallbackChain = array_values(array_unique(array_filter(array_map('trim', explode(',', $chain)))));
        $this->timeout = (int) config('app.cnpj.timeout', 10);
    }

    public function fetchFromAllProviders(string $cnpj): array
    {
        $attempts = [];
        $mh = curl_multi_init();
        $handles = [];
        $startTime = microtime(true);

        foreach ($this->fallbackChain as $provider) {
            $url = $this->buildUrl($provider, $cnpj);
            if (!$url) continue;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: Plattadata CMS/1.0',
                ],
            ]);

            curl_multi_add_handle($mh, $ch);
            $handles[$provider] = $ch;
        }

        $this->executeMultiCurl($mh);

        foreach ($handles as $provider => $ch) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $response = curl_multi_getcontent($ch);
            $error = curl_error($ch);

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            $attempts[] = [
                'provider' => $provider,
                'http_code' => $httpCode,
                'error' => $error,
            ];

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if ($data) {
                    $normalized = $this->normalizeResponse($provider, $data);
                    if ($normalized) {
                        curl_multi_close($mh);
                        return [
                            'data' => $normalized,
                            'provider' => $provider,
                            'attempts' => $attempts,
                        ];
                    }
                }
            }
        }

        curl_multi_close($mh);
        return ['data' => null, 'provider' => null, 'attempts' => $attempts];
    }

    private function buildUrl(string $provider, string $cnpj): ?string
    {
        $endpoints = self::PROVIDER_ENDPOINTS;
        return isset($endpoints[$provider]) ? $endpoints[$provider] . $cnpj : null;
    }

    private function executeMultiCurl($mh): void
    {
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 0.1);
        } while ($running > 0);
    }

    public function normalizeResponse(string $provider, array $data): ?array
    {
        if (empty($data)) {
            return null;
        }

        return match ($provider) {
            'brasilapi' => $this->normalizeBrasilApi($data),
            'receitaws' => $this->normalizeReceitaWs($data),
            'cnpjws' => $this->normalizeCnpjWs($data),
            'opencnpj' => $this->normalizeOpenCnpj($data),
            default => null,
        };
    }

    private function normalizeBrasilApi(array $d): array
    {
        return [
            'cnpj' => $d['cnpj'] ?? null,
            'legal_name' => $d['razao_social'] ?? null,
            'trade_name' => $d['nome_fantasia'] ?? null,
            'status' => $d['situacao_cadastral'] ?? null,
            'city' => $d['municipio'] ?? null,
            'uf' => $d['uf'] ?? null,
            'cep' => $d['cep'] ?? null,
            'street' => $d['logradouro'] ?? null,
            'address_number' => $d['numero'] ?? null,
            'district' => $d['bairro'] ?? null,
            'address_complement' => $d['complemento'] ?? null,
            'phone' => $d['ddd_telefone_1'] ?? null,
            'email' => $d['email'] ?? null,
            'opened_at' => isset($d['data_inicio_atividade']) ? date('Y-m-d', strtotime($d['data_inicio_atividade'])) : null,
            'company_size' => $d['porte'] ?? null,
            'legal_nature' => $d['natureza_juridica'] ?? null,
            'cnae_main_code' => $d['cnae_fiscal'] ?? null,
            'cnae_fiscal_descricao' => $d['cnae_fiscal_descricao'] ?? null,
            'capital_social' => $this->parseDecimal($d['capital_social'] ?? null),
            'simples_opt_in' => ($d['simples'] ?? null) === 'SIM',
            'mei_opt_in' => ($d['mei'] ?? null) === 'SIM',
            'qsa' => $d['qsa'] ?? [],
            'cnaes_secundarios' => normalize_cnaes_secundarios($d['cnaes_secundarios'] ?? null),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function normalizeReceitaWs(array $d): array
    {
        return [
            'cnpj' => $d['cnpj'] ?? null,
            'legal_name' => $d['nome'] ?? $d['razao_social'] ?? null,
            'trade_name' => $d['fantasia'] ?? null,
            'status' => $d['situacao'] ?? null,
            'city' => $d['municipio'] ?? null,
            'uf' => $d['uf'] ?? null,
            'cep' => $d['cep'] ?? null,
            'street' => $d['logradouro'] ?? null,
            'address_number' => $d['numero'] ?? null,
            'district' => $d['bairro'] ?? null,
            'phone' => $d['telefone1'] ?? null,
            'email' => $d['email'] ?? null,
            'opened_at' => (isset($d['abertura']) && ($ts = strtotime($d['abertura'])) !== false) ? date('Y-m-d', $ts) : null,
            'company_size' => $d['porte']['descricao'] ?? null,
            'legal_nature' => $d['natureza_juridica'] ?? null,
            'cnae_main_code' => $d['atividade_principal'][0]['code'] ?? null,
            'cnae_fiscal_descricao' => $d['atividade_principal'][0]['text'] ?? null,
            'capital_social' => $this->parseDecimal($d['capital_social'] ?? null),
            'qsa' => $d['qsa'] ?? [],
            'cnaes_secundarios' => normalize_cnaes_secundarios($d['atividades_secundarias'] ?? null),
        ];
    }

    private function normalizeCnpjWs(array $d): array
    {
        $est = is_array($d['estabelecimento'] ?? null) ? $d['estabelecimento'] : [];
        $principal = is_array($est['atividade_principal'] ?? null) ? $est['atividade_principal'] : [];
        $secundarias = is_array($est['atividades_secundarias'] ?? null) ? $est['atividades_secundarias'] : [];
        $cidade = is_array($est['cidade'] ?? null) ? $est['cidade'] : [];
        $estado = is_array($est['estado'] ?? null) ? $est['estado'] : [];
        $porte = is_array($d['porte'] ?? null) ? $d['porte'] : [];
        $natureza = is_array($d['natureza_juridica'] ?? null) ? $d['natureza_juridica'] : [];
        $simples = is_array($d['simples'] ?? null) ? $d['simples'] : [];
        $simei = is_array($simples['simei'] ?? null) ? $simples['simei'] : [];

        return [
            'cnpj' => $d['cnpj'] ?? null,
            'legal_name' => $d['razao_social'] ?? null,
            'trade_name' => $est['nome_fantasia'] ?? ($d['nome_fantasia'] ?? null),
            'status' => $est['situacao_cadastral'] ?? ($d['situacao_cadastral'] ?? null),
            'city' => $cidade['nome'] ?? ($est['cidade_nome'] ?? $d['municipio'] ?? null),
            'uf' => $estado['sigla'] ?? ($est['uf'] ?? $d['uf'] ?? null),
            'cep' => $est['cep'] ?? ($d['cep'] ?? null),
            'street' => $est['logradouro'] ?? ($d['logradouro'] ?? null),
            'address_number' => $est['numero'] ?? ($d['numero'] ?? null),
            'district' => $est['bairro'] ?? ($d['bairro'] ?? null),
            'phone' => $est['telefone1'] ?? ($est['ddd1'] ?? null),
            'email' => $est['email'] ?? ($d['email'] ?? null),
            'capital_social' => $this->parseDecimal($d['capital_social'] ?? null),
            'opened_at' => isset($est['data_inicio_atividade'])
                ? date('Y-m-d', strtotime((string) $est['data_inicio_atividade']))
                : (isset($d['data_inicio_atividade']) ? date('Y-m-d', strtotime((string) $d['data_inicio_atividade'])) : null),
            'company_size' => $porte['descricao'] ?? null,
            'legal_nature' => $natureza['descricao'] ?? ($d['natureza_juridica'] ?? null),
            'cnae_main_code' => $principal['id'] ?? ($principal['codigo'] ?? null),
            'cnae_fiscal_descricao' => $principal['descricao'] ?? null,
            'cnaes_secundarios' => normalize_cnaes_secundarios($secundarias),
            'simples_opt_in' => isset($simples['optante']) ? (bool) $simples['optante'] : null,
            'mei_opt_in' => isset($simei['optante']) ? (bool) $simei['optante'] : null,
            'motivo_situacao' => $est['motivo_situacao_cadastral'] ?? null,
            'data_situacao' => $est['data_situacao_cadastral'] ?? null,
            'codigo_municipio_ibge' => $cidade['ibge_id'] ?? null,
        ];
    }

    private function normalizeOpenCnpj(array $d): array
    {
        return [
            'cnpj' => $d['cnpj'] ?? null,
            'legal_name' => $d['razao_social'] ?? null,
            'trade_name' => $d['nome_fantasia'] ?? null,
            'status' => $d['status'] ?? null,
            'city' => $d['cidade'] ?? null,
            'uf' => $d['estado'] ?? null,
        ];
    }

    private function parseDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        $text = str_replace(['R$', ' '], '', $text);
        $hasComma = str_contains($text, ',');
        $hasDot = str_contains($text, '.');

        if ($hasComma && $hasDot) {
            // Formato BR: 1.234.567,89
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif ($hasComma) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }
}
