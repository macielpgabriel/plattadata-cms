<?php

declare(strict_types=1);

namespace App\Controllers\Observability;

use App\Core\Database;
use App\Core\Logger;
use App\Services\IbgeService;
use App\Repositories\MunicipalityRepository;

final class SyncService
{
    private IbgeService $ibgeService;
    private MunicipalityRepository $municipalityRepository;

    public function __construct()
    {
        $this->ibgeService = new IbgeService();
        $this->municipalityRepository = new MunicipalityRepository();
    }

    public function syncAllMunicipalities(): int
    {
        return $this->ibgeService->syncAllMunicipalities();
    }

    public function syncMunicipalityEnrichment(): array
    {
        $results = [
            'population' => 0,
            'gdp' => 0,
            'frota' => 0,
            'companies' => 0,
            'ddd' => 0,
        ];

        try {
            $results['population'] = $this->ibgeService->syncBulkPopulation();
        } catch (\Throwable $e) {
            Logger::warning('Sync population failed: ' . $e->getMessage());
        }

        try {
            $gdpResult = $this->ibgeService->syncBulkGdp();
            $results['gdp'] = $gdpResult['total'] ?? 0;
        } catch (\Throwable $e) {
            Logger::warning('Sync GDP failed: ' . $e->getMessage());
        }

        try {
            $results['frota'] = $this->ibgeService->syncBulkFrota();
        } catch (\Throwable $e) {
            Logger::warning('Sync fleet failed: ' . $e->getMessage());
        }

        try {
            $results['companies'] = $this->ibgeService->syncBulkCompanies();
        } catch (\Throwable $e) {
            Logger::warning('Sync companies failed: ' . $e->getMessage());
        }

        try {
            $results['ddd'] = $this->ibgeService->syncBulkDdd();
        } catch (\Throwable $e) {
            Logger::warning('Sync DDD failed: ' . $e->getMessage());
        }

        return $results;
    }

    public function syncCnaeActivities(): int
    {
        return 0;
    }
}