<?php

declare(strict_types=1);

namespace App\Services\Ibge;

use App\Repositories\MunicipalityRepository;

final class IbgeDemographicsService
{
    private MunicipalityRepository $municipalityRepository;
    private IbgeApiService $apiService;

    private const TTL_DEMOGRAPHICS = 365;

    public function __construct()
    {
        $this->municipalityRepository = new MunicipalityRepository();
        $this->apiService = new IbgeApiService();
    }

    public function getGenderDataSmart(int $ibgeCode): ?array
    {
        $muni = $this->municipalityRepository->findByIbgeCode($ibgeCode);
        $updatedAt = $muni['updated_at'] ?? null;
        
        $needsRefresh = $updatedAt === null || (new \DateTime($updatedAt))->diff(new \DateTime())->days >= self::TTL_DEMOGRAPHICS;
        
        if (!$needsRefresh) {
            return [
                'male' => $muni['population_male'] ?? null,
                'female' => $muni['population_female'] ?? null,
                'male_percent' => $muni['population_male_percent'] ?? null,
                'female_percent' => $muni['population_female_percent'] ?? null,
            ];
        }
        
        $genderData = $this->apiService->getPopulationByGender($ibgeCode);
        if ($genderData) {
            $this->municipalityRepository->updateField($ibgeCode, 'population_male', $genderData['male']);
            $this->municipalityRepository->updateField($ibgeCode, 'population_female', $genderData['female']);
            $this->municipalityRepository->updateField($ibgeCode, 'population_male_percent', $genderData['male_percent']);
            $this->municipalityRepository->updateField($ibgeCode, 'population_female_percent', $genderData['female_percent']);
        }
        
        return $genderData;
    }
}