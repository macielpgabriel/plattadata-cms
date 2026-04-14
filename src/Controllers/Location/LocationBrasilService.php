<?php

declare(strict_types=1);

namespace App\Controllers\Location;

use App\Core\Database;
use App\Services\IbgeService;

final class LocationBrasilService
{
    private IbgeService $ibgeService;

    public function __construct()
    {
        $this->ibgeService = new IbgeService();
    }

    public function getBrasilOverview(): array
    {
        return [
            'total_states' => 27,
            'total_municipalities' => 5570,
            'population' => $this->ibgeService->getPopulacaoBrasil(),
            'gdp' => $this->ibgeService->getPibBrasil(),
        ];
    }

    public function getStatesRanking(): array
    {
        $db = Database::connection();
        
        $stmt = $db->query("
            SELECT state, COUNT(*) as total
            FROM companies 
            WHERE is_hidden = 0 AND state IS NOT NULL AND state != ''
            GROUP BY state 
            ORDER BY total DESC
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getArrecadacaoBrasil(): array
    {
        return $this->ibgeService->getArrecadacaoPorEstado((int) date('Y'));
    }

    public function formatGdp(float $gdp): string
    {
        if ($gdp >= 1_000_000_000_000) {
            return number_format($gdp / 1_000_000_000_000, 2, ',', '.') . ' tri';
        }
        if ($gdp >= 1_000_000_000) {
            return number_format($gdp / 1_000_000_000, 2, ',', '.') . ' bi';
        }
        if ($gdp >= 1_000_000) {
            return number_format($gdp / 1_000_000, 2, ',', '.') . ' mi';
        }
        return number_format($gdp, 2, ',', '.');
    }
}