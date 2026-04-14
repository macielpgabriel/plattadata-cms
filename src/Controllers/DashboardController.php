<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Repositories\CompanyRepository;
use App\Services\BcbService;

final class DashboardController
{
    public function index(): void
    {
        $companyRepo = new CompanyRepository();
        $companies = $companyRepo->recent();
        
        $repo = new \App\Repositories\ExchangeRateRepository();
        $dbRates = $repo->getLatestRates();
        
        $exchangeRates = [];
        if (!empty($dbRates)) {
            foreach ($dbRates as $rate) {
                if (in_array($rate['currency_code'], ['USD', 'EUR'])) {
                    $exchangeRates[$rate['currency_code']] = [
                        'cotacaoCompra' => $rate['cotacao_compra'],
                        'cotacaoVenda' => $rate['cotacao_venda'],
                        'dataCotacao' => $rate['data_cotacao'],
                    ];
                }
            }
        }
        
        if (empty($exchangeRates)) {
            $bcb = new BcbService();
            $exchangeRates = [
                'USD' => $bcb->getLatestExchangeRate('USD'),
                'EUR' => $bcb->getLatestExchangeRate('EUR'),
            ];
        }

        // Dados para Gráficos e Logs (apenas Admin/Editor)
        $searchStats = [];
        $recentSearches = [];
        $dashboardMetrics = [];
        $topCompanies = [];
        if (Auth::can(['admin', 'editor'])) {
            $searchStats = $companyRepo->getSearchVolumeStats(7);
            $recentSearches = $companyRepo->getRecentGlobalSearches(8);
            $dashboardMetrics = $this->getDashboardMetrics();
            $topCompanies = $this->getTopCompaniesByCapital();
        }

        View::render('dashboard/index', [
            'title' => 'Dashboard',
            'user' => Auth::user(),
            'companies' => $companies,
            'exchangeRates' => $exchangeRates,
            'searchStats' => $searchStats,
            'recentSearches' => $recentSearches,
            'dashboardMetrics' => $dashboardMetrics,
            'topCompanies' => $topCompanies,
            'flash' => \App\Core\Session::flash('success'),
            'error' => \App\Core\Session::flash('error'),
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    private function getDashboardMetrics(): array
    {
        try {
            $db = Database::connection();
            
            $totalCompanies = $db->query("SELECT COUNT(*) as total FROM companies WHERE is_hidden = 0")->fetch(\PDO::FETCH_ASSOC);
            $totalActive = $db->query("SELECT COUNT(*) as total FROM companies WHERE is_hidden = 0 AND status = 'ativa'")->fetch(\PDO::FETCH_ASSOC);
            $totalMei = $db->query("SELECT COUNT(*) as total FROM companies WHERE is_hidden = 0 AND mei_opt_in = 1")->fetch(\PDO::FETCH_ASSOC);
            $totalSimples = $db->query("SELECT COUNT(*) as total FROM companies WHERE is_hidden = 0 AND simples_opt_in = 1")->fetch(\PDO::FETCH_ASSOC);
            $totalCapital = $db->query("SELECT SUM(capital_social) as total FROM companies WHERE is_hidden = 0 AND capital_social > 0")->fetch(\PDO::FETCH_ASSOC);
            $avgCapital = $db->query("SELECT AVG(capital_social) as total FROM companies WHERE is_hidden = 0 AND capital_social > 0")->fetch(\PDO::FETCH_ASSOC);
            
            $queriesToday = $db->query("SELECT COUNT(*) as total FROM company_query_logs WHERE DATE(created_at) = CURDATE()")->fetch(\PDO::FETCH_ASSOC);
            $queriesMonth = $db->query("SELECT COUNT(*) as total FROM company_query_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch(\PDO::FETCH_ASSOC);
            
            $byState = $db->query("SELECT state, COUNT(*) as total FROM companies WHERE is_hidden = 0 AND state IS NOT NULL GROUP BY state ORDER BY total DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            $byCnae = $db->query("SELECT cnae_main_code, COUNT(*) as total FROM companies WHERE is_hidden = 0 AND cnae_main_code IS NOT NULL GROUP BY cnae_main_code ORDER BY total DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            
            return [
                'total_companies' => (int) ($totalCompanies['total'] ?? 0),
                'total_active' => (int) ($totalActive['total'] ?? 0),
                'total_mei' => (int) ($totalMei['total'] ?? 0),
                'total_simples' => (int) ($totalSimples['total'] ?? 0),
                'total_capital' => (float) ($totalCapital['total'] ?? 0),
                'avg_capital' => (float) ($avgCapital['total'] ?? 0),
                'queries_today' => (int) ($queriesToday['total'] ?? 0),
                'queries_month' => (int) ($queriesMonth['total'] ?? 0),
                'top_state' => $byState['state'] ?? null,
                'top_cnae' => $byCnae['cnae_main_code'] ?? null,
                'taxa_ativa' => (int) ($totalCompanies['total'] ?? 0) > 0 
                    ? round(((int) ($totalActive['total'] ?? 0) / (int) ($totalCompanies['total'] ?? 0)) * 100, 1) 
                    : 0,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getTopCompaniesByCapital(int $limit = 10): array
    {
        try {
            $db = Database::connection();
            $stmt = $db->query("
                SELECT cnpj, legal_name, trade_name, city, state, capital_social
                FROM companies 
                WHERE is_hidden = 0 AND capital_social > 0
                ORDER BY capital_social DESC 
                LIMIT {$limit}
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function updateProfile(): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        $data = [
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? 'viewer'),
            'is_active' => (int) ($user['is_active'] ?? 1),
            'two_factor_enabled' => isset($_POST['two_factor_enabled']) ? 1 : 0,
            'password' => (string) ($_POST['password'] ?? ''),
        ];

        if (!empty($data['password'])) {
            $passwordError = (new \App\Services\PasswordPolicyService())->validate($data['password']);
            if ($passwordError !== null) {
                \App\Core\Session::flash('error', $passwordError);
                redirect('/dashboard');
            }
            $data['password_hash'] = password_hash($data['password'], PASSWORD_ARGON2ID);
        }

        (new \App\Repositories\UserRepository())->update((int) $user['id'], $data);
        \App\Core\Session::flash('success', 'Configuracoes de seguranca atualizadas.');
        redirect('/dashboard');
    }
}
