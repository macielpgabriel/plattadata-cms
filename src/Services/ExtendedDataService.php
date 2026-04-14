<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Logger;
use Throwable;

final class ExtendedDataService
{
    public function getExtendedData(array $company): array
    {
        $cnpj = $company['cnpj'] ?? '';
        $cacheKey = "extended_data:" . preg_replace('/[^0-9]/', '', $cnpj);
        
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $data = [
            'source' => 'calculated',
            'cnaes_secundarios' => [],
            'natureza_juridica' => null,
            'bcb_indicators' => [],
            'ibge_data' => [],
            'last_updated' => date('Y-m-d H:i:s'),
        ];

        $this->fillCompanyData($company, $data);
        $this->fillBcbIndicators($data);
        $this->fillIbgeData($company, $data);

        Cache::set($cacheKey, $data, 3600);

        return $data;
    }

    private function fillCompanyData(array $company, array &$data): void
    {
        $cnaesSecundarios = [];
        
        // Try direct field first
        if (is_array($company['cnaes_secundarios'] ?? null)) {
            $cnaesSecundarios = $company['cnaes_secundarios'];
        }
        
        // Se estiver vazio, tenta extrair do raw_data
        if (empty($cnaesSecundarios) && !empty($company['raw_data'])) {
            $raw = is_array($company['raw_data']) ? $company['raw_data'] : json_decode((string)$company['raw_data'], true);
            $cnaesSecundarios = $raw['cnaes_secundarios'] ?? $raw['atividades_secundarias'] ?? $raw['cnaes'] ?? [];
        }

        if (!empty($cnaesSecundarios) && is_array($cnaesSecundarios)) {
            $data['cnaes_secundarios'] = array_slice($cnaesSecundarios, 0, 15);
            $data['source'] = 'receita_federal';
        }

        $natureza = $company['natureza_juridica'] ?? $company['legal_nature'] ?? null;
        if (empty($natureza) && !empty($company['raw_data'])) {
             $raw = is_array($company['raw_data']) ? $company['raw_data'] : json_decode((string)$company['raw_data'], true);
             $natureza = (string) ($raw['natureza_juridica'] ?? '');
        }

        if (!empty($natureza)) {
            $data['natureza_juridica'] = $this->formatNaturezaJuridica((string)$natureza);
        }
    }

    private function formatNaturezaJuridica(string $codigo): string
    {
        $naturezas = [
            '2011' => 'Empresa Pública',
            '2038' => 'Sociedade de Economia Mista',
            '2062' => 'Sociedade Empresária Limitada',
            '2135' => 'Empresário Individual',
            '2140' => 'Sociedade de Economia Mista',
            '2305' => 'Empresa Individual de Responsabilidade Limitada',
            '2312' => 'Sociedade em Conta de Participação',
            '2321' => 'Sociedade Simples Pura',
            '2330' => 'Cooperativa',
            '3069' => 'Fundação Pública de Direito Privado Municipal',
            '3077' => 'Serviço Social Autônomo',
            '3085' => 'Condomínio Edilício',
            '3999' => 'Associação Privada',
            '4014' => 'Empresa Individual Imobiliária',
            '4081' => 'Contribuinte Individual',
        ];

        // Tenta extrair o código (ex: "2062 - Sociedade..." -> "2062")
        $cod = preg_match('/^\d{4}/', $codigo, $matches) ? $matches[0] : substr($codigo, 0, 4);
        return $naturezas[$cod] ?? $codigo;
    }

    private function fillBcbIndicators(array &$data): void
    {
        try {
            $bcb = new BcbService();
            $indicators = $bcb->getSgsIndicators();
            
            if (empty($indicators)) {
                return;
            }
            
            $interesting = ['selic', 'ipca', 'pib', 'poupanca', 'cdi', 'tr'];
            
            foreach ($interesting as $key) {
                if (isset($indicators[$key])) {
                    $ind = $indicators[$key];
                    $data['bcb_indicators'][$key] = [
                        'name' => $ind['name'],
                        'value' => $ind['value'],
                        'unit' => $ind['unit'],
                        'period' => $ind['period'] ?? null,
                    ];
                }
            }

            if (!empty($data['bcb_indicators'])) {
                $data['source'] = 'indicators';
            }
        } catch (Throwable $e) {
            Logger::error('BCB indicators enrichment failed: ' . $e->getMessage());
        }
    }

    private function fillIbgeData(array $company, array &$data): void
    {
        try {
            $ibgeService = new IbgeService();
            $ibgeCode = (int) ($company['municipal_ibge_code'] ?? $company['ibge_code'] ?? 0);
            
            if ($ibgeCode <= 0) {
                $city = $company['city'] ?? null;
                $state = $company['state'] ?? $company['uf'] ?? null;
                if ($city && $state) {
                    $municipio = $ibgeService->getMunicipalityByNameAndState($city, $state);
                    $ibgeCode = (int) ($municipio['ibge_code'] ?? 0);
                }
            }
            
            if ($ibgeCode > 0) {
                $stats = $ibgeService->fetchMunicipalityStats($ibgeCode);
                if ($stats) {
                    $data['ibge_data'] = [
                        'ibge_code' => $ibgeCode,
                        'population' => $stats['population'] ?? null,
                        'pib' => $stats['gdp'] ?? null,
                        'vehicles' => $stats['frota'] ?? null,
                        'business_units' => $stats['companies'] ?? null,
                    ];
                    $data['source'] = 'ibge';
                }
            }
        } catch (Throwable $e) {
            Logger::error('IBGE indicators enrichment failed: ' . $e->getMessage());
        }
    }

    public function refresh(string $cnpj): array
    {
        $cacheKey = "extended_data:" . preg_replace('/[^0-9]/', '', $cnpj);
        Cache::forget($cacheKey);
        
        return $this->getExtendedData(['cnpj' => $cnpj]);
    }
}