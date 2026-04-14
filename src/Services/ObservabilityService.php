<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

final class ObservabilityService
{
    public function healthSnapshot(): array
    {
        [$pdo, $dbError] = $this->connectApplicationDatabaseSafe();
        $dbOk = $pdo !== null;

        return [
            'status' => $dbOk ? 'ok' : 'degraded',
            'checked_at' => date('c'),
            'app_env' => (string) config('app.env', 'production'),
            'timezone' => (string) config('app.timezone', 'America/Sao_Paulo'),
            'database' => [
                'ok' => $dbOk,
                'error' => $dbError,
            ],
        ];
    }

    public function adminMetrics(): array
    {
        $metrics = [
            'counts' => [
                'companies' => 0,
                'users' => 0,
                'query_logs_24h' => 0,
                'api_attempts_24h' => 0,
                'api_failures_24h' => 0,
            ],
            'latency' => [
                'avg_attempts_last_24h' => 0.0,
            ],
            'logs' => [
                'app_tail' => $this->lastLogFile('app*.log', 20),
                'setup_tail' => $this->lastLogFile('setup*.log', 20),
                'php_errors_tail' => $this->lastLogFile('php_errors*.log', 20),
            ],
            'api_logs' => [],
            'weather_updates' => [],
            'exchange_updates' => [],
        ];

        try {
            [$pdo, $dbError] = $this->connectApplicationDatabaseSafe();
            if ($pdo === null) {
                $metrics['error'] = $dbError ?? 'Banco de dados indisponivel.';
                return $metrics;
            }

            $metrics['counts']['companies'] = $this->fetchScalar($pdo, 'SELECT COUNT(*) FROM companies');
            $metrics['counts']['users'] = $this->fetchScalar($pdo, 'SELECT COUNT(*) FROM users');
            $metrics['counts']['query_logs_24h'] = $this->fetchScalar($pdo, 'SELECT COUNT(*) FROM company_query_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)');
            $metrics['counts']['api_attempts_24h'] = $this->fetchScalar($pdo, 'SELECT COUNT(*) FROM company_source_payloads WHERE fetched_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)');
            $metrics['counts']['api_failures_24h'] = $this->fetchScalar($pdo, 'SELECT COUNT(*) FROM company_source_payloads WHERE fetched_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) AND succeeded = 0');

            // Municipality counts
            $metrics['counts']['municipalities'] = $this->fetchScalar($pdo, 'SELECT COUNT(*) FROM municipalities');
            $metrics['counts']['municipalities_with_weather'] = $this->fetchScalar($pdo, 'SELECT COUNT(*) FROM municipalities WHERE weather_updated_at IS NOT NULL');
            $metrics['counts']['active_companies'] = $this->fetchScalar($pdo, "SELECT COUNT(*) FROM companies WHERE status = 'ATIVA'");
            $metrics['counts']['inactive_companies'] = $this->fetchScalar($pdo, "SELECT COUNT(*) FROM companies WHERE status != 'ATIVA'");

            // Latest company
            $latestCompanyStmt = $pdo->query(
                "SELECT cnpj, trade_name, opened_at FROM companies ORDER BY opened_at DESC LIMIT 1"
            );
            $metrics['latest_company'] = $latestCompanyStmt->fetch(PDO::FETCH_ASSOC);

            // Weather updates
            $weatherStmt = $pdo->query(
                "SELECT m.ibge_code, m.name, m.state_uf, m.weather_updated_at as updated_at 
                 FROM municipalities m 
                 WHERE m.weather_updated_at IS NOT NULL 
                 ORDER BY m.weather_updated_at DESC 
                 LIMIT 20"
            );
            $metrics['weather_updates'] = $weatherStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Exchange rate updates
            $exchangeStmt = $pdo->query(
                "SELECT currency_code as currency, MAX(data_cotacao) as updated_at 
                 FROM exchange_rates 
                 GROUP BY currency_code 
                 ORDER BY updated_at DESC 
                 LIMIT 20"
            );
            $metrics['exchange_updates'] = $exchangeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Exchange rate counts
            $metrics['counts']['exchange_currencies'] = $this->fetchScalar($pdo, 'SELECT COUNT(DISTINCT currency_code) FROM exchange_rates');
            $metrics['counts']['exchange_records'] = $this->fetchScalar($pdo, 'SELECT COUNT(*) FROM exchange_rates');

            // Top search queries
            $topQueriesStmt = $pdo->query(
                "SELECT search_term, COUNT(*) as total 
                 FROM company_query_logs 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY search_term 
                 ORDER BY total DESC 
                 LIMIT 10"
            );
            $metrics['top_queries'] = $topQueriesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Top searched companies
            $topCompaniesStmt = $pdo->query(
                "SELECT cnpj, COUNT(*) as total, MAX(c.trade_name) as trade_name
                 FROM company_query_logs q
                 LEFT JOIN companies c ON q.search_term = c.cnpj OR q.search_term LIKE CONCAT('%', c.trade_name, '%')
                 WHERE q.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY cnpj
                 ORDER BY total DESC 
                 LIMIT 10"
            );
            $metrics['top_companies'] = $topCompaniesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Companies by status
            $statusStmt = $pdo->query(
                "SELECT status, COUNT(*) as total 
                 FROM companies 
                 GROUP BY status 
                 ORDER BY total DESC"
            );
            $metrics['companies_by_status'] = $statusStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Top municipalities by company count
            $topMunicipalitiesStmt = $pdo->query(
                "SELECT m.name, m.state_uf, COUNT(c.id) as total
                 FROM companies c
                 JOIN municipalities m ON c.municipal_ibge_code = m.ibge_code
                 GROUP BY m.ibge_code
                 ORDER BY total DESC
                 LIMIT 10"
            );
            $metrics['top_municipalities'] = $topMunicipalitiesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $attemptsPerCompany = $this->fetchScalarFloat(
                $pdo,
                'SELECT AVG(company_attempts.total) AS avg_attempts
                 FROM (
                     SELECT cnpj, COUNT(*) AS total
                     FROM company_source_payloads
                     WHERE fetched_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                     GROUP BY cnpj
                 ) AS company_attempts'
            );
            $metrics['latency']['avg_attempts_last_24h'] = round($attemptsPerCompany, 2);

            $stmt = $pdo->query(
                "SELECT cnpj, provider, status_code, succeeded, error_message, fetched_at
                 FROM company_source_payloads
                 ORDER BY fetched_at DESC
                 LIMIT 20"
            );
            $metrics['api_logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Hourly metrics for charts
            $metrics['hourly'] = $this->getHourlyMetrics($pdo);
        } catch (PDOException $exception) {
            $metrics['error'] = $exception->getMessage();
        }

        return $metrics;
    }

    private function getHourlyMetrics(PDO $pdo): array
    {
        $hourly = [
            'queries' => [],
            'api_attempts' => [],
            'api_failures' => [],
            'labels' => [],
        ];

        // Get last 24 hours
        for ($i = 23; $i >= 0; $i--) {
            $startHour = date('H', strtotime("-$i hours"));
            $hourly['labels'][] = $startHour . ':00';
            
            // Queries per hour
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM company_query_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
            ");
            // For hour from $i hours ago to $i+1 hours ago
            $stmt->execute([$i + 1, $i]);
            $hourly['queries'][] = (int) $stmt->fetchColumn();

            // API attempts per hour
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM company_source_payloads 
                WHERE fetched_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                AND fetched_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
            ");
            $stmt->execute([$i + 1, $i]);
            $hourly['api_attempts'][] = (int) $stmt->fetchColumn();

            // API failures per hour
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM company_source_payloads 
                WHERE fetched_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                AND fetched_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
                AND succeeded = 0
            ");
            $stmt->execute([$i + 1, $i]);
            $hourly['api_failures'][] = (int) $stmt->fetchColumn();
        }

        return $hourly;
    }

    private function connectApplicationDatabaseSafe(): array
    {
        $host = (string) config('database.host', 'localhost');
        $port = (string) config('database.port', '3306');
        $name = (string) config('database.name', '');
        $user = (string) config('database.user', '');
        $pass = (string) config('database.pass', '');

        if ($name === '' || $user === '') {
            return [null, 'Banco de dados nao configurado no CMS.'];
        }

        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->query('SELECT 1');
            return [$pdo, null];
        } catch (PDOException $exception) {
            return [null, $exception->getMessage()];
        }
    }

    private function fetchScalar(PDO $pdo, string $sql): int
    {
        $stmt = $pdo->query($sql);
        $value = $stmt ? $stmt->fetchColumn() : 0;
        return is_numeric($value) ? (int) $value : 0;
    }

    private function fetchScalarFloat(PDO $pdo, string $sql): float
    {
        $stmt = $pdo->query($sql);
        $value = $stmt ? $stmt->fetchColumn() : 0.0;
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function lastLogFile(string $pattern, int $lines): array
    {
        $logDir = base_path('storage/logs');
        $files = glob($logDir . '/' . $pattern);
        if (empty($files)) {
            return [];
        }
        $latestFile = end($files) ?: reset($files);
        return $this->tailLog($latestFile, $lines);
    }

    private function tailLog(string $file, int $lines): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $content = @file($file, FILE_IGNORE_NEW_LINES);
        if (!is_array($content)) {
            return [];
        }

        $slice = array_slice($content, -1 * max(1, $lines));
        return array_values(array_filter(array_map('trim', $slice), static fn (string $line): bool => $line !== ''));
    }
}
