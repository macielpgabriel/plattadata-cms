<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Logger;
use PDO;
use Throwable;

final class MarketAnalysisService
{
    public function analyzeMarket(string $cnpj, array $companyData): array
    {
        $cacheKey = "market_analysis:" . preg_replace('/[^0-9]/', '', $cnpj);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $cnae = $companyData['cnae_main_code'] ?? null;
        $city = $companyData['city'] ?? null;
        $state = $companyData['state'] ?? null;

        $marketData = [
            'source' => 'calculated',
            'competitors_count' => 0,
            'market_trend' => 'estavel',
            'competition_score' => 50,
            'sector_growth' => null,
            'market_size' => null,
            'last_updated' => date('Y-m-d H:i:s'),
        ];

        $this->fetchFromDatabase($cnpj, $cnae, $city, $state, $marketData);
        
        if ($marketData['source'] === 'calculated') {
            $this->fetchFromExternalApis($cnae, $city, $state, $marketData);
        }

        if ($marketData['source'] === 'calculated') {
            $calculated = $this->calculateLocalMarketData($cnae, $marketData['competitors_count']);
            $marketData['market_trend'] = $calculated['trend'];
            $marketData['competition_score'] = $calculated['competition'];
        }

        Cache::set($cacheKey, $marketData, 3600);

        return $marketData;
    }

    private function fetchFromDatabase(string $cnpj, ?string $cnae, ?string $city, ?string $state, array &$marketData): void
    {
        try {
            $db = Database::connection();
            
            if ($cnae && $city && $state) {
                $stmt = $db->prepare("
                    SELECT COUNT(*) as competitors
                    FROM companies 
                    WHERE cnae_main_code = ? 
                    AND city = ? 
                    AND state = ?
                    AND cnpj != ?
                ");
                $stmt->execute([$cnae, $city, $state, $cnpj]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                $marketData['competitors_count'] = (int) ($result['competitors'] ?? 0);
                
                $stmt = $db->prepare("SELECT market_trend, competition_score FROM cnae_statistics WHERE cnae_code = ?");
                $stmt->execute([$cnae]);
                $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($stats) {
                    $marketData['source'] = 'database';
                    $marketData['market_trend'] = $stats['market_trend'] ?? 'estavel';
                    $marketData['competition_score'] = (int) ($stats['competition_score'] ?? 50);
                }
            }
        } catch (Throwable $e) {
            Logger::error('Market DB fetch failed: ' . $e->getMessage());
        }
    }

    private function fetchFromExternalApis(?string $cnae, ?string $city, ?string $state, array &$marketData): void
    {
        $this->fetchFromIbge($city, $state, $marketData);
        
        if ($cnae && $marketData['source'] === 'calculated') {
            $this->fetchFromBcb($cnae, $marketData);
        }
    }

    private function fetchFromIbge(?string $city, ?string $state, array &$marketData): void
    {
        try {
            if (empty($city) || empty($state)) {
                return;
            }

            $ibgeService = new IbgeService();
            $cityData = $ibgeService->getMunicipalityByNameAndState($city, $state);
            
            if (!empty($cityData)) {
                $marketData['source'] = 'ibge';
                $population = (int) ($cityData['populacao'] ?? $cityData['population'] ?? 0);
                
                $marketData['market_size'] = match(true) {
                    $population > 1000000 => 'grande',
                    $population > 100000 => 'medio',
                    $population > 50000 => 'pequeno',
                    default => 'micro',
                };
            }
        } catch (Throwable $e) {
            Logger::error('Market IBGE fetch failed: ' . $e->getMessage());
        }
    }

    private function fetchFromBcb(?string $cnae, array &$marketData): void
    {
        try {
            $prefix = substr(str_replace(['.', '-'], '', $cnae), 0, 2);
            
            $techCnaes = ['62', '63', '64', '65', '66', '70', '71', '72'];
            $retailCnaes = ['45', '46', '47'];
            $industryCnaes = ['10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33'];
            
            if (in_array($prefix, $techCnaes)) {
                $marketData['sector_growth'] = 'alto';
                $marketData['market_trend'] = 'crescente';
                $marketData['competition_score'] = 65;
                $marketData['source'] = 'bcb';
            } elseif (in_array($prefix, $retailCnaes)) {
                $marketData['sector_growth'] = 'estavel';
                $marketData['market_trend'] = 'estavel';
                $marketData['competition_score'] = 75;
                $marketData['source'] = 'bcb';
            } elseif (in_array($prefix, $industryCnaes)) {
                $marketData['sector_growth'] = 'baixo';
                $marketData['market_trend'] = 'declinante';
                $marketData['competition_score'] = 55;
                $marketData['source'] = 'bcb';
            }
        } catch (Throwable $e) {
            Logger::error('Market BCB fetch failed: ' . $e->getMessage());
        }
    }

    private function calculateLocalMarketData(?string $cnae, int $competitors): array
    {
        $competition = 50;
        
        if ($competitors > 1000) {
            $competition = 80;
        } elseif ($competitors > 500) {
            $competition = 70;
        } elseif ($competitors > 100) {
            $competition = 60;
        } elseif ($competitors > 50) {
            $competition = 45;
        } elseif ($competitors > 10) {
            $competition = 35;
        } else {
            $competition = 25;
        }

        $trend = 'estavel';
        if ($cnae) {
            $prefix = substr(str_replace(['.', '-'], '', $cnae), 0, 2);
            $growing = ['62', '63', '64', '65', '66', '70', '71', '72'];
            $declining = ['45', '46'];
            
            if (in_array($prefix, $growing)) {
                $trend = 'crescente';
            } elseif (in_array($prefix, $declining)) {
                $trend = 'declinante';
            }
        }

        return [
            'trend' => $trend,
            'competition' => $competition,
        ];
    }

    public function refreshMarketData(string $cnpj): array
    {
        $cacheKey = "market_analysis:" . preg_replace('/[^0-9]/', '', $cnpj);
        Cache::forget($cacheKey);
        
        return $this->analyzeMarket($cnpj, []);
    }

    public static function updateCnaeStatistics(?string $cnae = null): int
    {
        if (empty($cnae)) {
            return 0;
        }

        $db = Database::connection();
        $normalizedCnae = preg_replace('/[^0-9]/', '', $cnae);
        if (strlen($normalizedCnae) < 4) {
            return 0;
        }

        try {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_companies,
                    AVG(CAST(REPLACE(REPLACE(capital_social, '.', ''), ',', '.') AS DECIMAL(18,2))) as avg_capital,
                    COALESCE(AVG(revenue_estimate), 0) as avg_revenue
                FROM companies 
                WHERE cnae_main_code LIKE ? 
                    AND is_hidden = 0
                    AND status != 'INATIVA'
            ");
            $likePattern = $normalizedCnae . '%';
            $stmt->execute([$likePattern]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$stats || (int) $stats['total_companies'] < 1) {
                return 0;
            }

            $medianStmt = $db->prepare("
                SELECT CAST(REPLACE(REPLACE(capital_social, '.', ''), ',', '.') AS DECIMAL(18,2)) as capital
                FROM companies 
                WHERE cnae_main_code LIKE ? 
                    AND is_hidden = 0
                    AND capital_social > 0
                ORDER BY capital
            ");
            $medianStmt->execute([$likePattern]);
            $capitals = $medianStmt->fetchAll(PDO::FETCH_COLUMN);
            $medianCapital = 0;
            $count = count($capitals);
            if ($count > 0) {
                $mid = (int) floor($count / 2);
                $medianCapital = $capitals[$mid] ?? 0;
            }

            $competitionScore = self::calculateCompetitionScore((int) $stats['total_companies'], $normalizedCnae);
            $trend = self::calculateMarketTrend($normalizedCnae);

            $upsert = $db->prepare("
                INSERT INTO cnae_statistics (cnae_code, total_companies, avg_capital, avg_revenue, revenue_median, competition_score, market_trend)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    total_companies = VALUES(total_companies),
                    avg_capital = VALUES(avg_capital),
                    avg_revenue = VALUES(avg_revenue),
                    revenue_median = VALUES(revenue_median),
                    competition_score = VALUES(competition_score),
                    market_trend = VALUES(market_trend),
                    updated_at = NOW()
            ");
            $upsert->execute([
                $normalizedCnae,
                (int) $stats['total_companies'],
                (float) ($stats['avg_capital'] ?? 0),
                (float) ($stats['avg_revenue'] ?? 0),
                (float) $medianCapital,
                $competitionScore,
                $trend,
            ]);

            return (int) $stats['total_companies'];
        } catch (Throwable $e) {
            Logger::error('CNAE statistics update error: ' . $e->getMessage());
            return 0;
        }
    }

    private static function calculateCompetitionScore(int $totalCompanies, string $cnae): int
    {
        if ($totalCompanies < 10) return 20;
        if ($totalCompanies < 50) return 35;
        if ($totalCompanies < 100) return 45;
        if ($totalCompanies < 250) return 55;
        if ($totalCompanies < 500) return 65;
        if ($totalCompanies < 1000) return 75;
        return 85;
    }

    private static function calculateMarketTrend(string $cnae): string
    {
        $prefix = substr($cnae, 0, 2);
        $growing = ['62', '63', '64', '65', '66', '70', '71', '72', '74', '78'];
        $declining = ['45', '46', '03', '05'];

        if (in_array($prefix, $growing)) {
            return 'crescendo';
        }
        if (in_array($prefix, $declining)) {
            return 'declinando';
        }
        return 'estavel';
    }

    public static function reconcileAllCnaeStatistics(): array
    {
        $db = Database::connection();
        $updated = 0;

        try {
            $stmt = $db->query("
                SELECT DISTINCT SUBSTRING(REPLACE(cnae_main_code, '.', ''), 1, 7) as cnae 
                FROM companies 
                WHERE cnae_main_code IS NOT NULL 
                    AND cnae_main_code != ''
                    AND is_hidden = 0
            ");
            $cnaes = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($cnaes as $cnae) {
                if (!empty($cnae) && strlen($cnae) >= 4) {
                    $result = self::updateCnaeStatistics($cnae);
                    if ($result > 0) $updated++;
                }
            }
        } catch (Throwable $e) {
            Logger::error('CNAE reconciliation error: ' . $e->getMessage());
        }

        return ['updated' => $updated, 'total_cnaes' => count($cnaes ?? [])];
    }
}