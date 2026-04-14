<?php

declare(strict_types=1);

namespace App\Services\Ibge;

use App\Repositories\MunicipalityRepository;

final class IbgePopulationService
{
    private MunicipalityRepository $municipalityRepository;
    private IbgeApiService $apiService;
    private int $cacheTtlDays;

    private const TTL_POPULATION = 180;

    public function __construct()
    {
        $this->municipalityRepository = new MunicipalityRepository();
        $this->apiService = new IbgeApiService();
        $this->cacheTtlDays = (int) config('ibge.cache_ttl_days', 30);
    }

    public function needsRefresh(?string $updatedAt, int $ttlDays): bool
    {
        if ($updatedAt === null) return true;
        
        $updated = new \DateTime($updatedAt);
        $now = new \DateTime();
        $diff = $now->diff($updated);
        $days = $diff->days;
        
        return $days >= $ttlDays;
    }

    public function getPopulationSmart(int $ibgeCode): ?int
    {
        $muni = $this->municipalityRepository->findByIbgeCode($ibgeCode);
        $updatedAt = $muni['updated_at'] ?? null;
        
        if (!$this->needsRefresh($updatedAt, self::TTL_POPULATION)) {
            return $muni['population'] ?? null;
        }
        
        $population = $this->apiService->getMunicipalityPopulationFromApi($ibgeCode);
        if ($population) {
            $this->municipalityRepository->updateField($ibgeCode, 'population', $population);
        }
        
        return $population;
    }

    public function getMunicipalityPopulation(int $ibgeCode): ?int
    {
        $muniRepo = new \App\Repositories\MunicipalityRepository();
        $muni = $muniRepo->findByIbgeCode($ibgeCode);
        if ($muni && !empty($muni['population'])) {
            return (int) $muni['population'];
        }
        return null;
    }

    public function getStatePopulation(string $uf): ?int
    {
        $populacoes = [
            'SP' => 46289333, 'RJ' => 17463349, 'MG' => 21411923, 'BA' => 14985284,
            'CE' => 9240580, 'PR' => 11597484, 'PE' => 9674793, 'RS' => 11473674,
            'PA' => 8777124, 'MA' => 7153262, 'SC' => 7338473, 'GO' => 7206589,
            'PB' => 4059905, 'AM' => 4269995, 'ES' => 4108508, 'MT' => 3567234,
            'MS' => 2839188, 'RN' => 3534165, 'AL' => 3365351, 'PI' => 3289290,
            'DF' => 3094325, 'SE' => 2338474, 'RO' => 1815278, 'TO' => 1607363,
            'AC' => 906876, 'AP' => 877613, 'RR' => 652713,
        ];
        return $populacoes[strtoupper($uf)] ?? null;
    }

    public function getPopulacaoBrasil(): int
    {
        return 215000000;
    }
}