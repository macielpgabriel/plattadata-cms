<?php

declare(strict_types=1);

namespace App\Controllers\Location;

use App\Repositories\StateRepository;
use App\Repositories\MunicipalityRepository;
use App\Services\IbgeService;

final class LocationStatesService
{
    private StateRepository $stateRepository;
    private IbgeService $ibgeService;

    public function __construct()
    {
        $this->stateRepository = new StateRepository();
        $this->ibgeService = new IbgeService();
    }

    public function getAllStates(): array
    {
        $states = $this->stateRepository->findAll();
        
        if (empty($states)) {
            return $this->getStatesFromApi();
        }
        
        return $states;
    }

    public function getStatesFromApi(): array
    {
        $statesData = $this->ibgeService->fetchAndCacheStates();
        
        $states = [];
        foreach ($statesData as $item) {
            $states[] = [
                'uf' => $item['uf'] ?? null,
                'name' => $item['name'] ?? null,
                'region' => $item['region'] ?? null,
                'ibge_code' => $item['ibge_code'] ?? null,
            ];
        }
        
        usort($states, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
        
        return $states;
    }

    public function getStateByUf(string $uf): ?array
    {
        return $this->stateRepository->findByUf($uf);
    }

    public function getStateWithStats(string $uf): array
    {
        $state = $this->getStateByUf($uf);
        
        if (!$state) {
            return [];
        }
        
        $state['company_count'] = $this->stateRepository->count();
        
        return $state;
    }
}