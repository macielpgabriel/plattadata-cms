<?php

declare(strict_types=1);

namespace App\Services\Ibge;

use App\Repositories\MunicipalityRepository;

final class IbgeGdpService
{
    private MunicipalityRepository $municipalityRepository;
    private IbgeApiService $apiService;

    private const TTL_GDP = 365;

    public function __construct()
    {
        $this->municipalityRepository = new MunicipalityRepository();
        $this->apiService = new IbgeApiService();
    }

    public function getGdpSmart(int $ibgeCode): ?array
    {
        $muni = $this->municipalityRepository->findByIbgeCode($ibgeCode);
        $updatedAt = $muni['updated_at'] ?? null;
        
        $needsRefresh = $updatedAt === null || (new \DateTime($updatedAt))->diff(new \DateTime())->days >= self::TTL_GDP;
        
        if (!$needsRefresh) {
            return [
                'gdp' => $muni['gdp'] ?? null,
                'gdp_per_capita' => $muni['gdp_per_capita'] ?? null,
            ];
        }
        
        $gdpData = $this->apiService->getMunicipalityGdpFromApi($ibgeCode);
        if ($gdpData) {
            if (!empty($gdpData['gdp'])) {
                $this->municipalityRepository->updateField($ibgeCode, 'gdp', $gdpData['gdp']);
            }
            if (!empty($gdpData['gdp_per_capita'])) {
                $this->municipalityRepository->updateField($ibgeCode, 'gdp_per_capita', $gdpData['gdp_per_capita']);
            }
        }
        
        return $gdpData;
    }

    public function getPibBrasil(): float
    {
        return 2100000000000.00;
    }
}