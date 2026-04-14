<?php

declare(strict_types=1);

namespace App\Services\Ibge;

use App\Repositories\MunicipalityRepository;

final class IbgeVehicleFleetService
{
    private MunicipalityRepository $municipalityRepository;
    private IbgeApiService $apiService;

    private const TTL_VEHICLE_FLEET = 365;

    public function __construct()
    {
        $this->municipalityRepository = new MunicipalityRepository();
        $this->apiService = new IbgeApiService();
    }

    public function getVehicleFleetSmart(int $ibgeCode): ?int
    {
        $muni = $this->municipalityRepository->findByIbgeCode($ibgeCode);
        $updatedAt = $muni['updated_at'] ?? null;
        
        $needsRefresh = $updatedAt === null || (new \DateTime($updatedAt))->diff(new \DateTime())->days >= self::TTL_VEHICLE_FLEET;
        
        if (!$needsRefresh) {
            return $muni['vehicle_fleet'] ?? null;
        }
        
        $fleet = $this->apiService->getMunicipalityVehicleFleetFromApi($ibgeCode);
        if ($fleet) {
            $this->municipalityRepository->updateField($ibgeCode, 'vehicle_fleet', $fleet);
        }
        
        return $fleet;
    }
}