<?php

declare(strict_types=1);

namespace App\Services\Ibge;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Logger;
use App\Repositories\MunicipalityRepository;
use Throwable;

final class IbgeSyncService
{
    private MunicipalityRepository $municipalityRepository;
    private IbgeApiService $apiService;
    private int $cacheTtlDays;

    private const CACHE_TTL = 86400;

    public function __construct()
    {
        $this->municipalityRepository = new MunicipalityRepository();
        $this->apiService = new IbgeApiService();
        $this->cacheTtlDays = (int) config('ibge.cache_ttl_days', 30);
    }

    public function syncAllMunicipalities(): int
    {
        $estados = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 
                    'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 
                    'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'];
        
        $totalInserted = 0;
        $errors = [];
        
        foreach ($estados as $uf) {
            $success = false;
            $attempts = 0;
            $maxAttempts = 3;
            
            while (!$success && $attempts < $maxAttempts) {
                $attempts++;
                
                try {
                    $url = "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$uf}/municipios";
                    
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 60,
                            'header' => "User-Agent: Mozilla/5.0\r\n",
                        ],
                    ]);
                    
                    $response = @file_get_contents($url, false, $context);
                    if ($response === false) {
                        throw new \Exception("Falha ao buscar dados");
                    }
                    
                    $data = json_decode($response, true);
                    if (!is_array($data) || empty($data)) {
                        throw new \Exception("Dados vazios ou inválidos");
                    }
                    
                    $municipalitiesData = [];
                    foreach ($data as $item) {
                        $municipalitiesData[] = [
                            'ibge_code' => (int) $item['id'],
                            'name' => (string) $item['nome'],
                            'state_uf' => $uf,
                            'mesoregion' => !empty($item['microrregiao']['mesorregiao']['nome']) 
                                ? (string) $item['microrregiao']['mesorregiao']['nome'] : null,
                            'microregion' => !empty($item['microrregiao']['nome']) 
                                ? (string) $item['microrregiao']['nome'] : null,
                            'slug' => slugify((string) $item['nome']),
                        ];
                    }
                    
                    if ($this->municipalityRepository->upsertMany($municipalitiesData)) {
                        $totalInserted += count($municipalitiesData);
                        $success = true;
                    }
                    
                } catch (\Throwable $e) {
                    if ($attempts >= $maxAttempts) {
                        $errors[] = "{$uf}: " . $e->getMessage();
                    }
                    usleep(500000);
                }
            }
            
            if ($success) {
                usleep(200000);
            }
        }
        
        if (!empty($errors)) {
            Logger::error("IbgeService syncAllMunicipalities errors: " . implode("; ", $errors));
        }
        
        return $totalInserted;
    }

    public function syncBulkPopulation(): int
    {
        $url = "https://servicodados.ibge.gov.br/api/v3/agregados/9514/periodos/2022/variaveis/93?localidades=N6[all]";
        $data = $this->apiService->fetchJson($url);
        
        if (!is_array($data) || empty($data)) {
            return 0;
        }

        $updates = [];
        foreach ($data as $variable) {
            foreach ($variable['resultados'] as $result) {
                foreach ($result['series'] as $series) {
                    $ibgeCode = (int) $series['localidade']['id'];
                    $value = (int) ($series['serie']['2022'] ?? 0);
                    if ($ibgeCode > 0 && $value > 0) {
                        $updates[$ibgeCode] = $value;
                    }
                }
            }
        }

        return $this->municipalityRepository->updateStatsBatch($updates, 'population');
    }

    public function syncBulkGdp(): array
    {
        $url = "https://servicodados.ibge.gov.br/api/v3/agregados/5938/periodos/2021/variaveis/37?localidades=N6[all]";
        $data = $this->apiService->fetchJson($url);
        
        if (!is_array($data) || empty($data)) {
            return ['total' => 0, 'per_capita' => 0];
        }

        $updatesGdp = [];
        $updatesPerCapita = [];
        $populations = $this->getAllPopulations();

        foreach ($data as $variable) {
            foreach ($variable['resultados'] as $result) {
                foreach ($result['series'] as $series) {
                    $ibgeCode = (int)$series['localidade']['id'];
                    $value = null;
                    foreach (['2021', '2022', '2020'] as $year) {
                        if (isset($series['serie'][$year]) && $series['serie'][$year] !== '...') {
                            $value = $series['serie'][$year];
                            break;
                        }
                    }
                    
                    if ($ibgeCode > 0 && $value !== null) {
                        $gdp = (float) $value;
                        $updatesGdp[$ibgeCode] = $gdp;
                        
                        $population = $populations[$ibgeCode] ?? 0;
                        if ($population > 0) {
                            $updatesPerCapita[$ibgeCode] = ($gdp * 1000) / $population;
                        }
                    }
                }
            }
        }

        $countGdp = $this->municipalityRepository->updateStatsBatch($updatesGdp, 'gdp');
        $countPerCapita = $this->municipalityRepository->updateStatsBatch($updatesPerCapita, 'gdp_per_capita');

        return ['total' => $countGdp, 'per_capita' => $countPerCapita];
    }

    private function getAllPopulations(): array
    {
        $url = "https://servicodados.ibge.gov.br/api/v3/agregados/9514/periodos/2022/variaveis/93?localidades=N6[all]";
        $data = $this->apiService->fetchJson($url);
        
        if (!is_array($data) || empty($data)) {
            return [];
        }
        
        $populations = [];
        foreach ($data as $variable) {
            foreach ($variable['resultados'] as $result) {
                foreach ($result['series'] as $series) {
                    $ibgeCode = (int)$series['localidade']['id'];
                    foreach (['2022', '2021', '2020'] as $year) {
                        if (isset($series['serie'][$year]) && is_numeric($series['serie'][$year])) {
                            $populations[$ibgeCode] = (int) $series['serie'][$year];
                            break;
                        }
                    }
                }
            }
        }
        
        return $populations;
    }

    public function syncBulkFrota(): int
    {
        $url = "https://servicodados.ibge.gov.br/api/v3/agregados/6875/periodos/2017/variaveis/9573?localidades=N6[all]";
        $data = $this->apiService->fetchJson($url);
        
        if (!is_array($data) || empty($data)) return 0;

        $updates = [];
        foreach ($data as $variable) {
            foreach ($variable['resultados'] as $result) {
                foreach ($result['series'] as $series) {
                    $ibgeCode = (int) $series['localidade']['id'];
                    $value = $series['serie']['2017'] ?? 0;
                    if (is_numeric($value)) {
                        $value = (int)$value;
                        if ($ibgeCode > 0 && $value > 0) {
                            $updates[$ibgeCode] = $value;
                        }
                    }
                }
            }
        }
        return $this->municipalityRepository->updateStatsBatch($updates, 'vehicle_fleet');
    }

    public function syncBulkCompanies(): int
    {
        $url = "https://servicodados.ibge.gov.br/api/v3/agregados/1685/periodos/2021/variaveis/706?localidades=N6[all]";
        $data = $this->apiService->fetchJson($url);
        
        if (!is_array($data) || empty($data)) return 0;

        $updates = [];
        foreach ($data as $variable) {
            foreach ($variable['resultados'] as $result) {
                foreach ($result['series'] as $series) {
                    $ibgeCode = (int) $series['localidade']['id'];
                    $value = null;
                    foreach (['2021', '2020', '2019'] as $year) {
                        if (isset($series['serie'][$year]) && is_numeric($series['serie'][$year])) {
                            $value = (int) $series['serie'][$year];
                            break;
                        }
                    }
                    if ($ibgeCode > 0 && $value > 0) {
                        $updates[$ibgeCode] = $value;
                    }
                }
            }
        }
        return $this->municipalityRepository->updateStatsBatch($updates, 'business_units');
    }

    public function syncBulkDdd(): int
    {
        $map = [
            'AC' => '68', 'AL' => '82', 'AM' => '92', 'AP' => '96', 'BA' => '71',
            'CE' => '85', 'DF' => '61', 'ES' => '27', 'GO' => '62', 'MA' => '98',
            'MG' => '31', 'MS' => '67', 'MT' => '65', 'PA' => '91', 'PB' => '83',
            'PE' => '81', 'PI' => '86', 'PR' => '41', 'RJ' => '21', 'RN' => '84',
            'RO' => '69', 'RR' => '95', 'RS' => '51', 'SC' => '48', 'SE' => '79',
            'SP' => '11', 'TO' => '63'
        ];

        foreach ($map as $uf => $ddd) {
            $sql = "UPDATE municipalities SET ddd = :ddd WHERE state_uf = :uf AND (ddd IS NULL OR ddd = '')";
            $stmt = \App\Core\Database::connection()->prepare($sql);
            $stmt->execute(['ddd' => $ddd, 'uf' => $uf]);
        }

        return 5570;
    }
}