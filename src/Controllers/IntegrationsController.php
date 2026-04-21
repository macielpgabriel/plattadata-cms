<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\CompanyRepository;
use App\Services\CnpjService;

final class IntegrationsController
{
    private CnpjService $cnpjService;
    private CompanyRepository $companies;

    public function __construct()
    {
        $this->cnpjService = new CnpjService();
        $this->companies = new CompanyRepository();
    }

    public function apiInfo(): void
    {
        Response::json([
            'name' => 'PlattaData CMS API',
            'version' => '1.0',
            'description' => 'API REST para consulta de dados de empresas brasileiras via CNPJ',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/v1/company/{cnpj}',
                    'description' => 'Busca dados de uma empresa pelo CNPJ',
                    'auth' => 'API Key (header X-API-Key)',
                    'params' => [
                        'cnpj' => 'CNPJ da empresa (somente números ou com máscara)',
                    ],
                    'example' => '/api/v1/company/12345678901234',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/search',
                    'description' => 'Busca empresas por termo ou filtros',
                    'auth' => 'API Key (header X-API-Key)',
                    'params' => [
                        'q' => 'Termo de busca (nome, CNPJ parcial)',
                        'state' => 'Sigla do estado (opcional)',
                        'page' => 'Página (padrão: 1)',
                        'per_page' => 'Resultados por página (máx: 100)',
                    ],
                    'example' => '/api/v1/search?q=google&state=SP',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/rankings/states',
                    'description' => 'Ranking de empresas por estado',
                    'auth' => 'API Key (header X-API-Key)',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/rankings/cities',
                    'description' => 'Ranking de empresas por cidade',
                    'auth' => 'API Key (header X-API-Key)',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/v1/rankings/cnae',
                    'description' => 'Ranking de CNAEs mais frequentes',
                    'auth' => 'API Key (header X-API-Key)',
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/v1/webhook/favorite',
                    'description' => 'Recebe notificações quando uma empresa é favoritada',
                    'auth' => 'Webhook Secret (header X-Webhook-Secret)',
                    'body' => [
                        'cnpj' => 'CNPJ da empresa',
                        'action' => 'added | removed',
                        'user_id' => 'ID do usuário',
                    ],
                ],
            ],
            'rate_limits' => [
                'authenticated' => '60 requests/minute',
                'api_key' => '120 requests/minute',
            ],
            'documentation' => config('app.url') . '/integracoes',
        ]);
    }

    public function getCompany(array $params): void
    {
        $apiKey = $this->getApiKey();
        if (!$this->validateApiKey($apiKey)) {
            Response::json(['error' => 'API Key inválida ou não autorizada'], 401);
        }

        $cnpj = $this->cnpjService->sanitize((string) ($params['cnpj'] ?? ''));
        
        if (!$this->cnpjService->validate($cnpj)) {
            Response::json(['error' => 'CNPJ inválido'], 400);
        }

        $company = $this->companies->findByCnpj($cnpj, true);
        
        if (!$company) {
            Response::json(['error' => 'Empresa não encontrada'], 404);
        }

        $response = $this->formatCompanyResponse($company);
        
        $this->logApiAccess($apiKey, 'get_company', $cnpj);
        
        Response::json([
            'success' => true,
            'data' => $response,
            'meta' => [
                'cnpj' => $cnpj,
                'cached' => true,
                'last_updated' => $company['updated_at'] ?? null,
            ],
        ]);
    }

    public function search(): void
    {
        $apiKey = $this->getApiKey();
        if (!$this->validateApiKey($apiKey)) {
            Response::json(['error' => 'API Key inválida ou não autorizada'], 401);
        }

        $term = trim((string) ($_GET['q'] ?? ''));
        $state = strtoupper(trim((string) ($_GET['state'] ?? '')));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));

        $result = $this->companies->searchPaginated($term, $state ?: null, $page, $perPage);
        
        $this->logApiAccess($apiKey, 'search', $term ?: 'all');

        Response::json([
            'success' => true,
            'data' => array_map(fn($c) => $this->formatCompanyResponse($c), $result['data']),
            'meta' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'last_page' => $result['last_page'],
                'query' => $term,
                'state' => $state ?: null,
            ],
        ]);
    }

    public function rankingsStates(): void
    {
        $apiKey = $this->getApiKey();
        if (!$this->validateApiKey($apiKey)) {
            Response::json(['error' => 'API Key inválida ou não autorizada'], 401);
        }

        try {
            $db = Database::connection();
            $stmt = $db->query("
                SELECT 
                    state as uf,
                    COUNT(*) as total
                FROM companies
                WHERE state IS NOT NULL AND state != '' AND is_hidden = 0
                GROUP BY state
                ORDER BY total DESC
            ");
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $total = array_sum(array_column($results, 'total'));
            
            $stateNames = [
                'SP' => 'São Paulo', 'RJ' => 'Rio de Janeiro', 'MG' => 'Minas Gerais',
                'RS' => 'Rio Grande do Sul', 'PR' => 'Paraná', 'BA' => 'Bahia',
                'GO' => 'Goiás', 'PE' => 'Pernambuco', 'SC' => 'Santa Catarina',
                'CE' => 'Ceará', 'PA' => 'Pará', 'MA' => 'Maranhão', 'AM' => 'Amazonas',
                'ES' => 'Espírito Santo', 'PB' => 'Paraíba', 'RN' => 'Rio Grande do Norte',
                'AL' => 'Alagoas', 'PI' => 'Piauí', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
                'SE' => 'Sergipe', 'RO' => 'Rondônia', 'TO' => 'Tocantins', 'AC' => 'Acre',
                'AP' => 'Amapá', 'RR' => 'Roraima', 'DF' => 'Distrito Federal'
            ];
            
            $data = [];
            foreach ($results as $index => $row) {
                $data[] = [
                    'ranking' => $index + 1,
                    'uf' => $row['uf'],
                    'state_name' => $stateNames[$row['uf']] ?? $row['uf'],
                    'total_companies' => (int) $row['total'],
                    'percentage' => $total > 0 ? round(($row['total'] / $total) * 100, 2) : 0,
                ];
            }

            $this->logApiAccess($apiKey, 'rankings', 'states');

            Response::json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'total_companies' => $total,
                    'total_states' => count($results),
                ],
            ]);
        } catch (\Exception $e) {
            Response::json(['error' => 'Erro ao buscar rankings'], 500);
        }
    }

    public function rankingsCities(): void
    {
        $apiKey = $this->getApiKey();
        if (!$this->validateApiKey($apiKey)) {
            Response::json(['error' => 'API Key inválida ou não autorizada'], 401);
        }

        try {
            $db = Database::connection();
            $stmt = $db->query("
                SELECT 
                    city,
                    state,
                    COUNT(*) as total
                FROM companies
                WHERE city IS NOT NULL AND city != '' AND state IS NOT NULL AND state != '' AND is_hidden = 0
                GROUP BY city, state
                ORDER BY total DESC
                LIMIT 50
            ");
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $data = [];
            foreach ($results as $index => $row) {
                $data[] = [
                    'ranking' => $index + 1,
                    'city' => $row['city'],
                    'state' => $row['state'],
                    'total_companies' => (int) $row['total'],
                ];
            }

            $this->logApiAccess($apiKey, 'rankings', 'cities');

            Response::json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Response::json(['error' => 'Erro ao buscar rankings'], 500);
        }
    }

    public function rankingsCnae(): void
    {
        $apiKey = $this->getApiKey();
        if (!$this->validateApiKey($apiKey)) {
            Response::json(['error' => 'API Key inválida ou não autorizada'], 401);
        }

        try {
            $db = Database::connection();
            $stmt = $db->query("
                SELECT 
                    cnae_main_code,
                    COUNT(*) as total
                FROM companies
                WHERE cnae_main_code IS NOT NULL AND cnae_main_code != '' AND is_hidden = 0
                GROUP BY cnae_main_code
                ORDER BY total DESC
                LIMIT 50
            ");
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $data = [];
            foreach ($results as $index => $row) {
                $data[] = [
                    'ranking' => $index + 1,
                    'cnae_code' => $row['cnae_main_code'],
                    'total_companies' => (int) $row['total'],
                ];
            }

            $this->logApiAccess($apiKey, 'rankings', 'cnae');

            Response::json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Response::json(['error' => 'Erro ao buscar rankings'], 500);
        }
    }

    public function webhookFavorite(): void
    {
        $secret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
        if (!$this->validateWebhookSecret($secret)) {
            Response::json(['error' => 'Webhook secret inválido'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['cnpj']) || empty($input['action'])) {
            Response::json(['error' => 'Parâmetros inválidos. Required: cnpj, action'], 400);
        }

        $cnpj = $this->cnpjService->sanitize($input['cnpj']);
        $action = $input['action'];
        
        if (!in_array($action, ['added', 'removed'], true)) {
            Response::json(['error' => 'Action deve ser "added" ou "removed"'], 400);
        }

        $this->logWebhookAccess($secret, $action, $cnpj);

        Response::json([
            'success' => true,
            'message' => 'Webhook processado',
            'received' => [
                'cnpj' => $cnpj,
                'action' => $action,
                'timestamp' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function integrationsPage(): void
    {
        if (!Auth::can(['admin'])) {
            http_response_code(403);
            echo 'Sem permissão para acessar esta página.';
            return;
        }

        $webhookSecrets = $this->getWebhookSecrets();

        View::render('admin/integrations', [
            'title' => 'Integrações - Webhooks',
            'webhookSecrets' => $webhookSecrets,
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function generateApiKey(): void
    {
        Session::flash('error', 'API Keys não estão mais disponíveis.');
        redirect('/admin/integracoes');
    }

    public function deleteApiKey(): void
    {
        Session::flash('error', 'API Keys não estão mais disponíveis.');
        redirect('/admin/integracoes');
    }

    public function generateWebhookSecret(): void
    {
        if (!Auth::can(['admin'])) {
            http_response_code(403);
            echo 'Sem permissão para acessar esta página.';
            return;
        }

        $name = trim((string) ($_POST['name'] ?? 'Webhook'));
        
        try {
            $secret = $this->createWebhookSecret($name);
            Session::flash('success', 'Webhook Secret gerado com sucesso. Copie agora pois não será mostrado novamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'Erro ao criar Webhook: ' . $e->getMessage());
        }

        redirect('/admin/integracoes');
    }

    public function deleteWebhookSecret(): void
    {
        if (!Auth::can(['admin'])) {
            http_response_code(403);
            echo 'Sem permissão para acessar esta página.';
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $this->removeWebhookSecret($id);

        Session::flash('success', 'Webhook Secret removido com sucesso.');
        redirect('/admin/integracoes');
    }

    private function getApiKey(): ?string
    {
        $header = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($header !== '') {
            return $header;
        }
        return null;
    }

    private function validateApiKey(?string $key): bool
    {
        if ($key === null || $key === '') {
            return false;
        }

        try {
            $db = Database::connection();
            $stmt = $db->prepare("SELECT id FROM api_keys WHERE api_key = :key AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
            $stmt->execute(['key' => $key]);
            return $stmt->fetch() !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function validateWebhookSecret(?string $secret): bool
    {
        if ($secret === null || $secret === '') {
            return false;
        }

        try {
            $db = Database::connection();
            $stmt = $db->prepare("SELECT id FROM api_keys WHERE webhook_secret = :secret AND is_active = 1");
            $stmt->execute(['secret' => $secret]);
            return $stmt->fetch() !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function formatCompanyResponse(array $company): array
    {
        return [
            'cnpj' => $company['cnpj'] ?? '',
            'legal_name' => $company['legal_name'] ?? '',
            'trade_name' => $company['trade_name'] ?? null,
            'status' => $company['status'] ?? '',
            'city' => $company['city'] ?? null,
            'state' => $company['state'] ?? null,
            'postal_code' => $company['postal_code'] ?? null,
            'email' => $company['email'] ?? null,
            'phone' => $company['phone'] ?? null,
            'website' => $company['website'] ?? null,
            'company_size' => $company['company_size'] ?? null,
            'capital_social' => $company['capital_social'] ?? null,
            'legal_nature' => $company['legal_nature'] ?? null,
            'cnae_main_code' => $company['cnae_main_code'] ?? null,
            'opened_at' => $company['opened_at'] ?? null,
            'simples_opt_in' => $company['simples_opt_in'] ? true : ($company['simples_opt_in'] === null ? null : false),
            'mei_opt_in' => $company['mei_opt_in'] ? true : ($company['mei_opt_in'] === null ? null : false),
        ];
    }

    private function logApiAccess(?string $apiKey, string $action, string $resource): void
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("INSERT INTO api_access_logs (api_key, action, resource, ip_address, created_at) VALUES (:key, :action, :resource, :ip, NOW())");
            $stmt->execute([
                'key' => $apiKey ? substr($apiKey, 0, 8) . '...' : 'unknown',
                'action' => $action,
                'resource' => $resource,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);
        } catch (\Exception $e) {
            // Silent fail for logging
        }
    }

    private function logWebhookAccess(string $secret, string $action, string $cnpj): void
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("INSERT INTO api_access_logs (api_key, action, resource, ip_address, created_at) VALUES ('webhook', :action, :resource, :ip, NOW())");
            $stmt->execute([
                'action' => $action,
                'resource' => $cnpj,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);
        } catch (\Exception $e) {
            // Silent fail for logging
        }
    }

    private function ensureTablesExist(): void
    {
        $db = Database::connection();
        
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS api_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                api_key VARCHAR(255) NULL,
                webhook_secret VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                expires_at DATETIME NULL,
                UNIQUE KEY uk_api_key (api_key),
                UNIQUE KEY uk_webhook_secret (webhook_secret)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Table might already exist
        }
        
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS api_access_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_key VARCHAR(50) NULL,
                action VARCHAR(100) NOT NULL,
                resource VARCHAR(255) NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created_at (created_at),
                INDEX idx_api_key (api_key),
                INDEX idx_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Table might already exist
        }
    }

    private function createApiKey(string $name): string
    {
        $key = bin2hex(random_bytes(32));
        
        $this->ensureTablesExist();
        
        try {
            $db = Database::connection();
            $stmt = $db->prepare("INSERT INTO api_keys (name, api_key, is_active, created_at) VALUES (:name, :key, 1, NOW())");
            $stmt->execute(['name' => $name, 'key' => $key]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Erro ao criar API Key: ' . $e->getMessage());
        }
        
        return $key;
    }

    private function createWebhookSecret(string $name): string
    {
        $secret = bin2hex(random_bytes(24));
        
        $this->ensureTablesExist();
        
        try {
            $db = Database::connection();
            $stmt = $db->prepare("INSERT INTO api_keys (name, webhook_secret, is_active, created_at) VALUES (:name, :secret, 1, NOW())");
            $stmt->execute(['name' => $name, 'secret' => $secret]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Erro ao criar Webhook Secret: ' . $e->getMessage());
        }
        
        return $secret;
    }

    private function getApiKeys(): array
    {
        $this->ensureTablesExist();
        
        try {
            $db = Database::connection();
            return $db->query("SELECT id, name, api_key, is_active, created_at, expires_at FROM api_keys WHERE api_key IS NOT NULL ORDER BY created_at DESC")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getWebhookSecrets(): array
    {
        $this->ensureTablesExist();
        
        try {
            $db = Database::connection();
            return $db->query("SELECT id, name, webhook_secret, is_active, created_at FROM api_keys WHERE webhook_secret IS NOT NULL ORDER BY created_at DESC")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getRecentApiLogs(int $limit = 20): array
    {
        try {
            $db = Database::connection();
            $stmt = $db->query("SELECT * FROM api_access_logs ORDER BY id DESC LIMIT {$limit}");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function removeApiKey(int $id): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("DELETE FROM api_keys WHERE id = :id AND api_key IS NOT NULL");
            return $stmt->execute(['id' => $id]);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function removeWebhookSecret(int $id): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("DELETE FROM api_keys WHERE id = :id AND webhook_secret IS NOT NULL");
            return $stmt->execute(['id' => $id]);
        } catch (\Exception $e) {
            return false;
        }
    }
}
