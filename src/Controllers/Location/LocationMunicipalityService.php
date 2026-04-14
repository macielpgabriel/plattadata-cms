<?php

declare(strict_types=1);

namespace App\Controllers\Location;

use App\Repositories\MunicipalityRepository;
use App\Services\IbgeService;
use App\Core\Logger;

final class LocationMunicipalityService
{
    private MunicipalityRepository $municipalityRepository;
    private IbgeService $ibgeService;

    public function __construct()
    {
        $this->municipalityRepository = new MunicipalityRepository();
        $this->ibgeService = new IbgeService();
    }

    public function getMunicipalityBySlug(string $slug, string $uf): ?array
    {
        $muni = $this->municipalityRepository->findBySlug($slug, $uf);
        
        if ($muni) {
            return $muni;
        }
        
        $nameFallback = str_replace('-', ' ', $slug);
        return $this->municipalityRepository->findByNameAndState($nameFallback, $uf);
    }

    public function getMunicipalityByIbge(int $ibgeCode): ?array
    {
        return $this->municipalityRepository->findByIbgeCode($ibgeCode);
    }

    public function getMunicipalityWithStats(int $ibgeCode, bool $skipRefresh = false): array
    {
        $muni = $this->getMunicipalityByIbge($ibgeCode);
        
        if (!$muni) {
            return [];
        }
        
        $stats = $this->ibgeService->fetchMunicipalityStats($ibgeCode, $skipRefresh);
        
        if ($stats) {
            $muni = array_merge($muni, $stats);
        }
        
        return $muni;
    }

    public function getMunicipalitiesByState(string $uf, int $page = 1, int $perPage = 50, ?string $search = null): array
    {
        return $this->municipalityRepository->findByState($uf, $page, $perPage, $search);
    }

    public function syncMunicipalityFromApi(int $ibgeCode): ?array
    {
        try {
            $muni = $this->ibgeService->fetchMunicipalityStats($ibgeCode, true);
            return $muni;
        } catch (\Throwable $e) {
            Logger::error("Failed to sync municipality {$ibgeCode}: " . $e->getMessage());
            return null;
        }
    }
}