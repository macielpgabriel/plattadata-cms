<?php

declare(strict_types=1);

namespace App\Services\Cnpj;

use App\Core\Database;
use App\Repositories\MunicipalityRepository;
use App\Services\IbgeService;
use App\Services\DddService;
use App\Core\Logger;

final class CnpjEnrichmentService
{
    private MunicipalityRepository $municipalityRepository;
    private IbgeService $ibgeService;
    private DddService $dddService;

    public function __construct()
    {
        $this->municipalityRepository = new MunicipalityRepository();
        $this->ibgeService = new IbgeService();
        $this->dddService = new DddService();
    }

    public function enrichData(string $cnpj, array $payload): array
    {
        $enrichment = $this->fetchEnrichmentDataParallel($cnpj, $payload);
        return array_merge($payload, $enrichment);
    }

    private function fetchEnrichmentDataParallel(string $cnpj, array $payload): array
    {
        $enrichment = [];
        $cep = $this->extractCep($payload);

        if ($cep) {
            $cepData = $this->fetchViaCep($cep);
            if ($cepData) {
                $enrichment['_cep_details'] = $cepData;
                $enrichment = $this->parseMunicipalityData($payload, $enrichment);
            }
        }

        if (empty($enrichment['_municipality_details'])) {
            $muniData = $this->resolveMunicipalityFromPayload($payload);
            if ($muniData) {
                $enrichment['_municipality_details'] = $muniData;
            }
        }

        return $enrichment;
    }

    private function extractCep(array $payload): ?string
    {
        $cep = $payload['cep'] ?? $payload['cep'] ?? null;
        if ($cep) {
            return preg_replace('/\D/', '', (string) $cep);
        }
        return null;
    }

    private function fetchViaCep(string $cep): ?array
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true,
            ]
        ]);

        // Try BrasilAPI first (returns more complete data including DDD)
        $url = "https://brasilapi.com.br/api/cep/v2/{$cep}";
        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);
            if (is_array($data) && !isset($data['type'])) {
                return [
                    'cep' => $data['cep'] ?? null,
                    'logradouro' => $data['street'] ?? null,
                    'bairro' => $data['neighborhood'] ?? null,
                    'cidade' => $data['city'] ?? null,
                    'state' => $data['state'] ?? null,
                    'ibge' => $data['ibge'] ?? null,
                    'ddd' => $data['ddd'] ?? null,
                    'source' => 'brasilapi',
                ];
            }
        }

        // Fallback to ViaCEP
        $url = "https://viacep.com.br/ws/{$cep}/json/";
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || isset($data['erro'])) {
            return null;
        }

        return [
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['localidade'] ?? null,
            'state' => $data['uf'] ?? null,
            'ibge' => $data['ibge'] ?? null,
            'ddd' => $data['ddd'] ?? null,
            'source' => 'viacep',
        ];
    }

    private function parseMunicipalityData(array $payload, array $enrichment): array
    {
        $city = $payload['municipio'] ?? $payload['cidade'] ?? $enrichment['_cep_details']['cidade'] ?? null;
        $uf = $payload['uf'] ?? $payload['estado'] ?? $enrichment['_cep_details']['state'] ?? null;
        $ddd = $payload['ddd_telefone_1'] ?? $payload['telefone'] ?? null;

        if ($ddd && strlen($ddd) >= 2) {
            $ddd = preg_replace('/\D/', '', $ddd);
            $enrichment['_cep_details']['ddd'] = substr($ddd, 0, 2);
        }

        if (empty($enrichment['_cep_details']['ddd']) && $uf) {
            $ddds = $this->dddService->getDddsByState($uf);
            if (!empty($ddds)) {
                $enrichment['_cep_details']['ddd'] = $ddds[0];
                $enrichment['_cep_details']['ddd_source'] = 'DddService';
            }
        }

        if ($city && $uf) {
            $muniData = $this->resolveMunicipalityByName($city, $uf);
            if ($muniData) {
                $enrichment['_municipality_details'] = [
                    'id' => $muniData['ibge_code'] ?? null,
                    'name' => $muniData['name'] ?? $city,
                    'slug' => slugify($city),
                    'mesoregion' => $muniData['mesoregion'] ?? null,
                    'microregion' => $muniData['microregion'] ?? null,
                    'regiao' => $this->getRegiaoNome($uf),
                ];
            }
        }

        return $enrichment;
    }

    private function resolveMunicipalityFromPayload(array $payload): ?array
    {
        $city = $payload['municipio'] ?? $payload['cidade'] ?? null;
        $uf = $payload['uf'] ?? $payload['estado'] ?? null;

        if (!$city || !$uf) {
            return null;
        }

        return $this->resolveMunicipalityByName($city, $uf);
    }

    private function resolveMunicipalityByName(string $city, string $uf): ?array
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT ibge_code, name, mesoregion, microregion FROM municipalities 
                 WHERE name LIKE :city AND state_uf = :uf LIMIT 1"
            );
            $stmt->execute(['city' => '%' . $city . '%', 'uf' => $uf]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                return $result;
            }

            $muni = $this->municipalityRepository->findByNameAndState($city, $uf);
            return $muni;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getRegiaoNome(string $sigla): string
    {
        $regioes = [
            'AC' => 'Norte', 'AM' => 'Norte', 'AP' => 'Norte', 'PA' => 'Norte',
            'RO' => 'Norte', 'RR' => 'Norte', 'TO' => 'Norte',
            'AL' => 'Nordeste', 'BA' => 'Nordeste', 'CE' => 'Nordeste',
            'MA' => 'Nordeste', 'PB' => 'Nordeste', 'PE' => 'Nordeste',
            'PI' => 'Nordeste', 'RN' => 'Nordeste', 'SE' => 'Nordeste',
            'DF' => 'Centro-Oeste', 'GO' => 'Centro-Oeste',
            'MT' => 'Centro-Oeste', 'MS' => 'Centro-Oeste',
            'ES' => 'Sudeste', 'MG' => 'Sudeste', 'RJ' => 'Sudeste', 'SP' => 'Sudeste',
            'PR' => 'Sul', 'RS' => 'Sul', 'SC' => 'Sul',
        ];

        return $regioes[strtoupper($sigla)] ?? 'N/A';
    }

    public function checkComplianceSync(string $cnpj): array
    {
        try {
            $complianceService = new \App\Services\ComplianceService();
            return $complianceService->checkSanctions($cnpj);
        } catch (\Throwable $e) {
            Logger::warning('Compliance check failed: ' . $e->getMessage());
            return [];
        }
    }
}