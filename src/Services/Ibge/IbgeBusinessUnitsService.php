<?php

declare(strict_types=1);

namespace App\Services\Ibge;

use App\Repositories\MunicipalityRepository;

final class IbgeBusinessUnitsService
{
    private MunicipalityRepository $municipalityRepository;
    private IbgeApiService $apiService;

    private const TTL_BUSINESS_UNITS = 180;

    public function __construct()
    {
        $this->municipalityRepository = new MunicipalityRepository();
        $this->apiService = new IbgeApiService();
    }

    public function getBusinessUnitsSmart(int $ibgeCode): ?int
    {
        $muni = $this->municipalityRepository->findByIbgeCode($ibgeCode);
        $updatedAt = $muni['updated_at'] ?? null;
        
        $needsRefresh = $updatedAt === null || (new \DateTime($updatedAt))->diff(new \DateTime())->days >= self::TTL_BUSINESS_UNITS;
        
        if (!$needsRefresh) {
            return $muni['business_units'] ?? null;
        }
        
        $units = $this->apiService->getMunicipalityBusinessUnitsFromApi($ibgeCode);
        if ($units) {
            $this->municipalityRepository->updateField($ibgeCode, 'business_units', $units);
        }
        
        return $units;
    }
}