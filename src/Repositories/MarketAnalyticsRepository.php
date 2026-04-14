<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Cache;
use PDO;

final class MarketAnalyticsRepository
{
    private const CACHE_TTL = 3600;

    public function getCnaeStatistics(int $limit = 20): array
    {
        $cacheKey = "analytics_cnae_stats_{$limit}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $db = Database::connection();
        
        $sql = "
            SELECT 
                c.cnae_main_code,
                a.description AS cnae_description,
                a.section,
                COUNT(*) AS total_companies,
                SUM(COALESCE(c.capital_social, 0)) AS total_revenue,
                AVG(COALESCE(c.capital_social, 0)) AS avg_revenue,
                MAX(COALESCE(c.capital_social, 0)) AS max_revenue,
                MIN(COALESCE(c.capital_social, 0)) AS min_revenue
            FROM companies c
            LEFT JOIN cnae_activities a ON c.cnae_main_code = a.code
            WHERE c.cnae_main_code IS NOT NULL 
                AND c.cnae_main_code != ''
            GROUP BY c.cnae_main_code, a.description, a.section
            ORDER BY avg_revenue DESC
            LIMIT :limit
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        Cache::set($cacheKey, $results, self::CACHE_TTL);
        return $results;
    }

    public function getStateStatistics(): array
    {
        $cacheKey = "analytics_state_stats";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $db = Database::connection();
        
        $sql = "
            SELECT 
                c.state AS uf,
                s.name AS state_name,
                COUNT(*) AS total_companies,
                SUM(COALESCE(c.capital_social, 0)) AS total_revenue,
                AVG(COALESCE(c.capital_social, 0)) AS avg_revenue,
                COUNT(DISTINCT c.city) AS total_cities
            FROM companies c
            LEFT JOIN states s ON c.state = s.uf
            WHERE c.state IS NOT NULL 
                AND c.state != ''
            GROUP BY c.state, s.name
            ORDER BY total_companies DESC
        ";
        
        $stmt = $db->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        Cache::set($cacheKey, $results, self::CACHE_TTL);
        return $results;
    }

    public function getMunicipalityStatistics(int $limit = 20): array
    {
        $cacheKey = "analytics_muni_stats_{$limit}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $db = Database::connection();
        
        $sql = "
            SELECT 
                c.city,
                c.state AS uf,
                COUNT(*) AS total_companies,
                SUM(COALESCE(c.capital_social, 0)) AS total_revenue,
                AVG(COALESCE(c.capital_social, 0)) AS avg_revenue
            FROM companies c
            WHERE c.city IS NOT NULL 
                AND c.city != ''
            GROUP BY c.city, c.state
            HAVING total_companies >= 100
            ORDER BY avg_revenue DESC
            LIMIT :limit
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        Cache::set($cacheKey, $results, self::CACHE_TTL);
        return $results;
    }

    public function getTopCnaeByState(string $uf, int $limit = 5): array
    {
        $cacheKey = "analytics_cnae_state_{$uf}_{$limit}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $db = Database::connection();
        
        $sql = "
            SELECT 
                c.cnae_main_code,
                a.description AS cnae_description,
                COUNT(*) AS total_companies,
                AVG(COALESCE(c.capital_social, 0)) AS avg_revenue
            FROM companies c
            LEFT JOIN cnae_activities a ON c.cnae_main_code = a.code
            WHERE c.state = :uf
                AND c.cnae_main_code IS NOT NULL
                AND c.cnae_main_code != ''
            GROUP BY c.cnae_main_code, a.description
            ORDER BY total_companies DESC
            LIMIT :limit
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':uf', strtoupper($uf));
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        Cache::set($cacheKey, $results, self::CACHE_TTL);
        return $results;
    }

    public function getCompanySizeDistribution(): array
    {
        $cacheKey = "analytics_company_sizes";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $db = Database::connection();
        
        $sql = "
            SELECT 
                company_size,
                COUNT(*) AS total,
                AVG(COALESCE(capital_social, 0)) AS avg_revenue
            FROM companies
            WHERE company_size IS NOT NULL
                AND company_size != ''
            GROUP BY company_size
            ORDER BY 
                CASE company_size
                    WHEN 'MEI' THEN 1
                    WHEN 'ME' THEN 2
                    WHEN 'EPP' THEN 3
                    WHEN 'GP' THEN 4
                    WHEN 'LTDA' THEN 5
                    WHEN 'SA' THEN 6
                    ELSE 7
                END
        ";
        
        $stmt = $db->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        Cache::set($cacheKey, $results, self::CACHE_TTL);
        return $results;
    }

    public function getStatusDistribution(): array
    {
        $cacheKey = "analytics_status_dist";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $db = Database::connection();
        
        $sql = "
            SELECT 
                status,
                COUNT(*) AS total
            FROM companies
            WHERE status IS NOT NULL
                AND status != ''
            GROUP BY status
            ORDER BY total DESC
        ";
        
        $stmt = $db->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        Cache::set($cacheKey, $results, self::CACHE_TTL);
        return $results;
    }

    public function getTotalCompanies(): int
    {
        $db = Database::connection();
        $stmt = $db->query("SELECT COUNT(*) AS total FROM companies");
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function getTotalRevenue(): float
    {
        $db = Database::connection();
        $stmt = $db->query("SELECT SUM(COALESCE(capital_social, 0)) AS total FROM companies");
        return (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function getRecentGrowth(int $days = 30): array
    {
        $db = Database::connection();
        
        $sql = "
            SELECT 
                DATE(created_at) AS date,
                COUNT(*) AS new_companies
            FROM companies
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function clearCache(): void
    {
        Cache::forget('analytics_cnae_stats_20');
        Cache::forget('analytics_state_stats');
        Cache::forget('analytics_muni_stats_20');
        Cache::forget('analytics_company_sizes');
        Cache::forget('analytics_status_dist');
    }
}
