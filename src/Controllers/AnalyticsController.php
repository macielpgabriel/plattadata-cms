<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\SearchAnalyticsService;

final class AnalyticsController
{
    private SearchAnalyticsService $analytics;

    public function __construct()
    {
        $this->analytics = new SearchAnalyticsService();
    }

    public function index(): void
    {
        if (!Auth::can(['admin', 'editor'])) {
            http_response_code(403);
            echo 'Sem permissão para acessar esta página.';
            return;
        }

        $days = (int) ($_GET['days'] ?? 30);
        $days = max(7, min(365, $days));

        $stats = [
            'total_consults' => $this->analytics->getTotalConsults($days),
            'daily_stats' => $this->analytics->getDailyConsultStats($days),
            'weekly_stats' => $this->analytics->getWeeklyConsultStats(),
            'monthly_stats' => $this->analytics->getMonthlyConsultStats(12),
            'by_state' => $this->analytics->getConsultStatsByState(),
            'by_user' => $this->analytics->getConsultStatsByUser(),
            'top_companies' => $this->analytics->getTopConsultedCompanies(10, $days),
            'search_terms' => $this->analytics->getSearchTerms(20),
            'new_companies' => $this->analytics->getNewCompaniesStats(),
            'peak_hour' => $this->analytics->getPeakConsultHour(),
        ];

        View::render('admin/analytics', [
            'title' => 'Analytics - Consultas e Tendências',
            'stats' => $stats,
            'selectedDays' => $days,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function companyTrends(array $params): void
    {
        if (!Auth::can(['admin', 'editor'])) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Sem permissão'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $cnpj = (string) ($params['cnpj'] ?? '');
        $days = (int) ($_GET['days'] ?? 30);

        if (empty($cnpj)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'CNPJ requerido'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $trend = $this->analytics->getCompanyTrend($cnpj, $days);
        $growth = $this->analytics->getGrowthRate($cnpj);
        $stats = $this->analytics->getCompanyConsultStats($cnpj);
        $company = $this->getCompanyDetails($cnpj);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'cnpj' => $cnpj,
            'trend' => $trend,
            'growth' => $growth,
            'stats' => $stats,
            'company' => $company,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public function compareDetailed(): void
    {
        $cnpj1 = '';
        $cnpj2 = '';

        try {
            // Check auth
            if (!Auth::can(['admin', 'editor'])) {
                Response::json(['error' => 'Sem permissão'], 403);
            }

            $cnpj1 = preg_replace('/\D/', '', (string) ($_GET['cnpj1'] ?? ''));
            $cnpj2 = preg_replace('/\D/', '', (string) ($_GET['cnpj2'] ?? ''));

            if (empty($cnpj1) || empty($cnpj2) || strlen($cnpj1) !== 14 || strlen($cnpj2) !== 14) {
                Response::json(['error' => 'CNPJs inválidos'], 400);
            }

            $comparison = $this->analytics->getComparisonData([$cnpj1, $cnpj2]);
            
            \App\Core\Logger::info('Analytics comparison query executed', [
                'searched_cnpjs' => [$cnpj1, $cnpj2],
                'results_found' => count($comparison)
            ]);

            // Indexar resultados por CNPJ (limpo) para garantir ordem e suportar comparação da mesma empresa
            $indexed = [];
            foreach ($comparison as $row) {
                $cleanRowCnpj = preg_replace('/\D/', '', (string) $row['cnpj']);
                $indexed[$cleanRowCnpj] = $row;
            }

            $data1 = $indexed[$cnpj1] ?? null;
            $data2 = $indexed[$cnpj2] ?? null;

            if (!$data1 || !$data2) {
                $missing = [];
                if (!$data1) $missing[] = $cnpj1;
                if (!$data2 && $cnpj1 !== $cnpj2) $missing[] = $cnpj2;

                Response::json([
                    'error' => 'Empresas não encontradas ou não registradas.',
                    'message' => 'Não foi possível encontrar dados completos para: ' . implode(', ', $missing),
                    'found_count' => count($comparison),
                    'requested' => [$cnpj1, $cnpj2]
                ], 404);
            }

            $result = [
                'success' => true,
                'companies' => [
                    '1' => $this->formatCompanyComparison($data1),
                    '2' => $this->formatCompanyComparison($data2),
                ],
                'differences' => $this->calculateDifferences($data1, $data2),
            ];

            Response::json($result);

            
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha na comparação detalhada: ' . $e->getMessage(), [
                'cnpj1' => $cnpj1,
                'cnpj2' => $cnpj2,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'error' => 'Erro interno',
                'message' => (bool) config('app.debug', false) ? $e->getMessage() : 'Ocorreu um erro ao processar a comparação.'
            ], 500);
        }
    }

    
    private function formatCompanyComparison(array $data): array
    {
        return [
            'cnpj' => $data['cnpj'],
            'name' => $data['trade_name'] ?: $data['legal_name'],
            'legal_name' => $data['legal_name'],
            'status' => $data['status'] ?? 'desconhecido',
            'city' => $data['city'] ?? '-',
            'state' => $data['state'] ?? '-',
            'opened_at' => $data['opened_at'] ?? null,
            'company_size' => $data['company_size'] ?? '-',
            'cnae' => $data['cnae_main_code'] ?? '-',
            'capital_social' => (float) ($data['capital_social'] ?? 0),
            'capital_social_formatted' => $this->formatCurrency((float) ($data['capital_social'] ?? 0)),
            'total_consults' => (int) ($data['total_consults'] ?? 0),
            'total_views' => (int) ($data['total_views'] ?? 0),
            'days_consulted' => (int) ($data['days_consulted'] ?? 0),
            'unique_users' => (int) ($data['unique_users'] ?? 0),
            'first_consult' => $data['first_consult'] ?? null,
            'last_consult' => $data['last_consult'] ?? null,
            'credit_score' => (int) ($data['credit_score'] ?? 0) ?: null,
            'risk_level' => $data['risk_level'] ?? null,
            'simples' => (bool) ($data['simples_opt_in'] ?? false),
            'mei' => (bool) ($data['mei_opt_in'] ?? false),
        ];
    }
    
    private function calculateDifferences(array $data1, array $data2): array
    {
        $diffs = [];
        
        $consults1 = (int) ($data1['total_consults'] ?? 0);
        $consults2 = (int) ($data2['total_consults'] ?? 0);
        $diffs['total_consults'] = $this->diff($consults1, $consults2);
        
        $views1 = (int) ($data1['total_views'] ?? 0);
        $views2 = (int) ($data2['total_views'] ?? 0);
        $diffs['total_views'] = $this->diff($views1, $views2);
        
        $users1 = (int) ($data1['unique_users'] ?? 0);
        $users2 = (int) ($data2['unique_users'] ?? 0);
        $diffs['unique_users'] = $this->diff($users1, $users2);
        
        $days1 = (int) ($data1['days_consulted'] ?? 0);
        $days2 = (int) ($data2['days_consulted'] ?? 0);
        $diffs['days_consulted'] = $this->diff($days1, $days2);
        
        $capital1 = (float) ($data1['capital_social'] ?? 0);
        $capital2 = (float) ($data2['capital_social'] ?? 0);
        $diffs['capital_social'] = $this->diff($capital1, $capital2, true);
        
        $score1 = (int) ($data1['credit_score'] ?? 0) ?: 50;
        $score2 = (int) ($data2['credit_score'] ?? 0) ?: 50;
        $diffs['credit_score'] = $this->diff($score1, $score2);
        
        return $diffs;
    }
    
    private function diff($val1, $val2, $isCurrency = false): array
    {
        $diff = $val1 - $val2;
        $percent = $val2 > 0 ? round((($val1 - $val2) / $val2) * 100, 1) : ($val1 > 0 ? 100 : 0);
        
        return [
            'value1' => $val1,
            'value2' => $val2,
            'difference' => $diff,
            'difference_formatted' => $isCurrency ? $this->formatCurrency(abs($diff)) : abs((int) $diff),
            'percent' => $percent,
            'percent_formatted' => ($percent >= 0 ? '+' : '') . $percent . '%',
            'winner' => $diff > 0 ? '1' : ($diff < 0 ? '2' : 'tie'),
        ];
    }
    
    private function formatCurrency(float $value): string
    {
        if ($value >= 1000000000) {
            return 'R$ ' . number_format($value / 1000000000, 2, ',', '.') . ' bi';
        }
        if ($value >= 1000000) {
            return 'R$ ' . number_format($value / 1000000, 2, ',', '.') . ' mi';
        }
        if ($value >= 1000) {
            return 'R$ ' . number_format($value / 1000, 1, ',', '.') . ' mil';
        }
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
    
    private function getCompanyDetails(string $cnpj): array
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                SELECT cnpj, legal_name, trade_name, status, city, state, opened_at,
                       company_size, cnae_main_code, capital_social, credit_score, risk_level,
                       simples_opt_in, mei_opt_in, views, employees_estimate
                FROM companies WHERE cnpj = ?
            ");
            $stmt->execute([$cnpj]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function compare(): void
    {
        if (!Auth::can(['admin', 'editor'])) {
            http_response_code(403);
            echo 'Sem permissão para acessar esta página.';
            return;
        }

        $cnpjsParam = (string) ($_GET['cnpjs'] ?? '');
        $cnpjs = array_filter(array_map('trim', explode(',', $cnpjsParam)));

        $comparison = [];
        if (!empty($cnpjs)) {
            $comparison = $this->analytics->getComparisonData($cnpjs);
        }

        View::render('admin/analytics_compare', [
            'title' => 'Comparar Empresas - Analytics',
            'comparison' => $comparison,
            'cnpjs' => $cnpjs,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function exportCsv(): void
    {
        if (!Auth::can(['admin', 'editor'])) {
            http_response_code(403);
            exit;
        }

        $stats = $this->analytics->getDailyConsultStats(90);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=consultas_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['Data', 'Total Consultas', 'Empresas Únicas', 'Usuários Únicos'], ';');
        
        foreach ($stats as $row) {
            fputcsv($output, [
                $row['date'],
                $row['total'],
                $row['unique_companies'],
                $row['unique_users'],
            ], ';');
        }
        
        fclose($output);
    }

    public function searchCompanies(): void
    {
        if (!Auth::can(['admin', 'editor'])) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Sem permissão'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $term = trim((string) ($_GET['q'] ?? ''));
        
        if (strlen($term) < 2) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $db = Database::connection();
            
            $stmt = $db->prepare("
                SELECT cnpj, legal_name, trade_name, city, state, status
                FROM companies 
                WHERE is_hidden = 0 
                AND (
                    cnpj LIKE :term1 
                    OR legal_name LIKE :term2 
                    OR trade_name LIKE :term3
                )
                ORDER BY 
                    CASE 
                        WHEN legal_name LIKE :exact THEN 1
                        WHEN trade_name LIKE :exact2 THEN 2
                        ELSE 3
                    END,
                    legal_name ASC
                LIMIT 10
            ");
            
            $likeTerm = '%' . $term . '%';
            $exactTerm = $term . '%';
            
            $stmt->execute([
                'term1' => $likeTerm,
                'term2' => $likeTerm,
                'term3' => $likeTerm,
                'exact' => $exactTerm,
                'exact2' => $exactTerm,
            ]);
            
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $formatted = array_map(function($company) {
                $name = $company['trade_name'] ?: $company['legal_name'];
                return [
                    'cnpj' => $company['cnpj'],
                    'name' => $name,
                    'full_name' => $company['legal_name'],
                    'trade_name' => $company['trade_name'],
                    'location' => ($company['city'] ?? '') . '/' . ($company['state'] ?? ''),
                    'status' => $company['status'],
                    'label' => $name . ' (' . $company['cnpj'] . ') - ' . ($company['city'] ?? '') . '/' . ($company['state'] ?? ''),
                ];
            }, $results);
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($formatted, JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Erro na busca'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
