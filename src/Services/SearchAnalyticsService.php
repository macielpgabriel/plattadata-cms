<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class SearchAnalyticsService
{
    public function getTopConsultedCompanies(int $limit = 10, int $days = 30): array
    {
        $db = Database::connection();
        
        $stmt = $db->prepare("
            SELECT 
                c.cnpj,
                c.legal_name,
                c.trade_name,
                c.city,
                c.state,
                COUNT(l.id) as consult_count,
                MAX(l.created_at) as last_consult
            FROM company_query_logs l
            INNER JOIN companies c ON c.id = l.company_id
            WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY c.id, c.cnpj, c.legal_name, c.trade_name, c.city, c.state
            ORDER BY consult_count DESC
            LIMIT :limit
        ");
        $stmt->bindValue('days', $days, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCompanyConsultStats(string $cnpj): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_consults,
                    COUNT(DISTINCT DATE(created_at)) as days_consulted,
                    COUNT(DISTINCT user_id) as unique_users,
                    MIN(created_at) as first_consult,
                    MAX(created_at) as last_consult
                FROM company_query_logs l
                INNER JOIN companies c ON c.id = l.company_id
                WHERE c.cnpj = ?
            ");
            $stmt->execute([$cnpj]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [
                'total_consults' => 0,
                'days_consulted' => 0,
                'unique_users' => 0,
                'first_consult' => null,
                'last_consult' => null,
            ];
        }
    }

    public function getDailyConsultStats(int $days = 30): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total,
                    COUNT(DISTINCT company_id) as unique_companies,
                    COUNT(DISTINCT user_id) as unique_users
                FROM company_query_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->bindValue('days', $days, \PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getWeeklyConsultStats(): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->query("
                SELECT 
                    YEARWEEK(created_at, 1) as year_week,
                    DATE(MIN(created_at)) as week_start,
                    COUNT(*) as total,
                    COUNT(DISTINCT company_id) as unique_companies
                FROM company_query_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                GROUP BY YEARWEEK(created_at, 1)
                ORDER BY year_week ASC
            ");
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getMonthlyConsultStats(int $months = 12): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as total,
                    COUNT(DISTINCT company_id) as unique_companies,
                    COUNT(DISTINCT user_id) as unique_users
                FROM company_query_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC
            ");
            $stmt->bindValue('months', $months, \PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getHourlyConsultPatterns(): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->query("
                SELECT 
                    HOUR(created_at) as hour,
                    DAYOFWEEK(created_at) as day_of_week,
                    COUNT(*) as total
                FROM company_query_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY HOUR(created_at), DAYOFWEEK(created_at)
                ORDER BY day_of_week, hour
            ");
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getConsultStatsByState(): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->query("
                SELECT 
                    c.state,
                    COUNT(*) as total_consults,
                    COUNT(DISTINCT c.id) as unique_companies
                FROM company_query_logs l
                INNER JOIN companies c ON c.id = l.company_id
                WHERE c.state IS NOT NULL
                AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY c.state
                ORDER BY total_consults DESC
            ");
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getConsultStatsByUser(): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->query("
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    COUNT(l.id) as total_consults,
                    COUNT(DISTINCT l.company_id) as unique_companies,
                    MAX(l.created_at) as last_consult
                FROM company_query_logs l
                INNER JOIN users u ON u.id = l.user_id
                WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY u.id, u.name, u.email
                ORDER BY total_consults DESC
                LIMIT 20
            ");
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getSearchTerms(int $limit = 50): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->query("
                SELECT 
                    resource as search_term,
                    COUNT(*) as total
                FROM api_access_logs
                WHERE action = 'search'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY resource
                ORDER BY total DESC
                LIMIT {$limit}
            ");
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getCompanyTrend(string $cnpj, int $days = 30): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total
                FROM company_query_logs l
                INNER JOIN companies c ON c.id = l.company_id
                WHERE c.cnpj = ?
                AND l.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->bindValue('days', $days, \PDO::PARAM_INT);
            $stmt->execute([$cnpj]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getGrowthRate(string $cnpj): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM company_query_logs l
                     INNER JOIN companies c ON c.id = l.company_id
                     WHERE c.cnpj = ?
                     AND l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as this_week,
                    (SELECT COUNT(*) FROM company_query_logs l
                     INNER JOIN companies c ON c.id = l.company_id
                     WHERE c.cnpj = ?
                     AND l.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                     AND l.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)) as last_week
            ");
            $stmt->execute([$cnpj, $cnpj]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $thisWeek = (int) ($result['this_week'] ?? 0);
            $lastWeek = (int) ($result['last_week'] ?? 0);
            
            if ($lastWeek === 0) {
                $growth = $thisWeek > 0 ? 100 : 0;
            } else {
                $growth = round((($thisWeek - $lastWeek) / $lastWeek) * 100, 1);
            }
            
            return [
                'this_week' => $thisWeek,
                'last_week' => $lastWeek,
                'growth_percent' => $growth,
                'trend' => $growth > 0 ? 'up' : ($growth < 0 ? 'down' : 'stable'),
            ];
        } catch (\Exception $e) {
            return [
                'this_week' => 0,
                'last_week' => 0,
                'growth_percent' => 0,
                'trend' => 'stable',
            ];
        }
    }

    public function getTotalConsults(int $days = 30): int
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM company_query_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->bindValue('days', $days, \PDO::PARAM_INT);
            $stmt->execute();
            
            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getPeakConsultHour(): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->query("
                SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as total
                FROM company_query_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY HOUR(created_at)
                ORDER BY total DESC
                LIMIT 1
            ");
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return [
                'hour' => (int) ($result['hour'] ?? 0),
                'total' => (int) ($result['total'] ?? 0),
            ];
        } catch (\Exception $e) {
            return ['hour' => 0, 'total' => 0];
        }
    }

    public function getNewCompaniesStats(): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->query("
                SELECT 
                    COUNT(*) as total_new,
                    COUNT(CASE WHEN status = 'ativa' THEN 1 END) as active,
                    COUNT(CASE WHEN simples_opt_in = 1 THEN 1 END) as in_simples,
                    COUNT(CASE WHEN mei_opt_in = 1 THEN 1 END) as mei
                FROM companies
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getComparisonData(array $cnpjs): array
    {
        if (empty($cnpjs)) {
            return [];
        }

        $db = Database::connection();

        $hasTradeName = $this->companyColumnExists($db, 'trade_name');
        $hasStatus = $this->companyColumnExists($db, 'status');
        $hasCity = $this->companyColumnExists($db, 'city');
        $hasState = $this->companyColumnExists($db, 'state');
        $hasCapitalSocial = $this->companyColumnExists($db, 'capital_social');
        $hasOpenedAt = $this->companyColumnExists($db, 'opened_at');
        $hasCompanySize = $this->companyColumnExists($db, 'company_size');
        $hasCnaeMainCode = $this->companyColumnExists($db, 'cnae_main_code');
        $hasViews = $this->companyColumnExists($db, 'views');
        $hasCreditScore = $this->companyColumnExists($db, 'credit_score');
        $hasRiskLevel = $this->companyColumnExists($db, 'risk_level');
        $hasSimplesOptIn = $this->companyColumnExists($db, 'simples_opt_in');
        $hasMeiOptIn = $this->companyColumnExists($db, 'mei_opt_in');

        $selectTradeName = $hasTradeName ? 'c.trade_name' : 'NULL';
        $selectStatus = $hasStatus ? 'c.status' : "'desconhecido'";
        $selectCity = $hasCity ? 'c.city' : 'NULL';
        $selectState = $hasState ? 'c.state' : 'NULL';
        $selectCapitalSocial = $hasCapitalSocial ? 'c.capital_social' : '0';
        $selectOpenedAt = $hasOpenedAt ? 'c.opened_at' : 'NULL';
        $selectCompanySize = $hasCompanySize ? 'c.company_size' : 'NULL';
        $selectCnaeMainCode = $hasCnaeMainCode ? 'c.cnae_main_code' : 'NULL';
        $selectViews = $hasViews ? 'c.views' : '0';
        $selectCreditScore = $hasCreditScore ? 'c.credit_score' : 'NULL';
        $selectRiskLevel = $hasRiskLevel ? 'c.risk_level' : 'NULL';
        $selectSimplesOptIn = $hasSimplesOptIn ? 'c.simples_opt_in' : 'NULL';
        $selectMeiOptIn = $hasMeiOptIn ? 'c.mei_opt_in' : 'NULL';

        $groupBy = [
            'c.id',
            'c.cnpj',
            'c.legal_name',
        ];

        if ($hasTradeName) {
            $groupBy[] = 'c.trade_name';
        }
        if ($hasStatus) {
            $groupBy[] = 'c.status';
        }
        if ($hasCity) {
            $groupBy[] = 'c.city';
        }
        if ($hasState) {
            $groupBy[] = 'c.state';
        }
        if ($hasCapitalSocial) {
            $groupBy[] = 'c.capital_social';
        }
        if ($hasOpenedAt) {
            $groupBy[] = 'c.opened_at';
        }
        if ($hasCompanySize) {
            $groupBy[] = 'c.company_size';
        }
        if ($hasCnaeMainCode) {
            $groupBy[] = 'c.cnae_main_code';
        }
        if ($hasViews) {
            $groupBy[] = 'c.views';
        }
        if ($hasCreditScore) {
            $groupBy[] = 'c.credit_score';
        }
        if ($hasRiskLevel) {
            $groupBy[] = 'c.risk_level';
        }
        if ($hasSimplesOptIn) {
            $groupBy[] = 'c.simples_opt_in';
        }
        if ($hasMeiOptIn) {
            $groupBy[] = 'c.mei_opt_in';
        }

        $placeholders = implode(',', array_fill(0, count($cnpjs), '?'));
        $stmt = $db->prepare("
            SELECT 
                c.id as company_id,
                c.cnpj,
                c.legal_name,
                {$selectTradeName} as trade_name,
                {$selectStatus} as status,
                {$selectCity} as city,
                {$selectState} as state,
                {$selectCapitalSocial} as capital_social,
                {$selectOpenedAt} as opened_at,
                {$selectCompanySize} as company_size,
                {$selectCnaeMainCode} as cnae_main_code,
                {$selectViews} as total_views,
                {$selectCreditScore} as credit_score,
                {$selectRiskLevel} as risk_level,
                {$selectSimplesOptIn} as simples_opt_in,
                {$selectMeiOptIn} as mei_opt_in,
                COUNT(l.id) as total_consults,
                COUNT(DISTINCT DATE(l.created_at)) as days_consulted,
                COUNT(DISTINCT l.user_id) as unique_users,
                MIN(l.created_at) as first_consult,
                MAX(l.created_at) as last_consult
            FROM companies c
            LEFT JOIN company_query_logs l ON l.company_id = c.id
            WHERE REPLACE(REPLACE(REPLACE(c.cnpj, '.', ''), '/', ''), '-', '') IN ({$placeholders})
            GROUP BY " . implode(', ', $groupBy) . "
        ");
        $stmt->execute($cnpjs);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function companyColumnExists(\PDO $db, string $column): bool
    {
        static $cache = [];

        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }

        $stmt = $db->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'companies'
              AND COLUMN_NAME = :column
            LIMIT 1
        ");
        $stmt->bindValue('column', $column);
        $stmt->execute();

        $cache[$column] = (bool) $stmt->fetchColumn();

        return $cache[$column];
    }
    
    public function getComparisonDetailedStats(string $cnpj): array
    {
        $db = Database::connection();
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_consults,
                    COUNT(DISTINCT DATE(created_at)) as days_with_consults,
                    COUNT(DISTINCT user_id) as unique_users,
                    MIN(created_at) as first_consult,
                    MAX(created_at) as last_consult
                FROM company_query_logs
                WHERE company_id = (SELECT id FROM companies WHERE cnpj = ?)
            ");
            $stmt->execute([$cnpj]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
