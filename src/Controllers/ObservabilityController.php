<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;
use App\Services\ObservabilityService;
use App\Services\SetupService;
use App\Services\IbgeService;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Auth;
use ReflectionClass;
use Throwable;

final class ObservabilityController
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function health(): void
    {
        $snapshot = (new ObservabilityService())->healthSnapshot();
        $statusCode = (($snapshot['status'] ?? 'degraded') === 'ok') ? 200 : 503;
        Response::json($snapshot, $statusCode);
    }

    public function adminIndex(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $this->renderObservability();
    }
    
    public function renderObservability(): void
    {
        $service = new ObservabilityService();
        
        View::render('admin/observability', [
            'title' => 'Observabilidade',
            'health' => $service->healthSnapshot(),
            'metrics' => $service->adminMetrics(),
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function runMigrations(): void
    {

        $results = ['success' => [], 'errors' => []];

        try {
            $pdo = \App\Core\Database::connection();
            $setupService = new SetupService();
            $reflection = new ReflectionClass($setupService);

            $lockFiles = [
                base_path('storage/.schema_location_completed'),
                base_path('storage/.schema_qsa_v1_completed'),
            ];

            foreach ($lockFiles as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            $methods = [
                'ensureBlacklistSchema',
                'ensureNotificationsSchema',
                'ensurePasswordResetSchema',
                'ensureUserExtensionsSchema',
                'ensureLocationAndTaxSchema',
                'ensureFavoritesSchema',
                'ensureQsaAndCnaeSchema',
                'ensureSearchOptimizationSchema',
                'ensureEnrichmentSchema',
            ];

            foreach ($methods as $methodName) {
                if ($reflection->hasMethod($methodName)) {
                    $method = $reflection->getMethod($methodName);
                    $method->setAccessible(true);
                    $method->invoke($setupService, $pdo);
                    $results['success'][] = $methodName;
                }
            }

            $tables = ['states', 'municipalities', 'cnpj_blacklist', 'notification_logs', 'password_reset_tokens', 'user_favorites'];
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    $results['success'][] = "Tabela $table: OK";
                } else {
                    $results['errors'][] = "Tabela $table: NAO ENCONTRADA";
                }
            }

            \App\Core\Session::flash('success', 'Migrations executadas: ' . count($results['success']) . ' com sucesso.');
        } catch (\Throwable $e) {
            $results['errors'][] = $e->getMessage();
            \App\Core\Session::flash('error', 'Erro ao executar migrations: ' . $e->getMessage());
        }

        redirect('/admin#observabilidade');
    }

    public function syncMunicipalities(): void
    {
        if (!\App\Core\Auth::can(['admin'])) {
            http_response_code(403);
            echo 'Acesso negado.';
            return;
        }

        if (!\App\Core\Csrf::validate($_POST['_token'] ?? null)) {
            Session::flash('error', 'Sessão expirada.');
            redirect('/admin#observabilidade');
        }

        try {
            $action = $_POST['action'] ?? 'ibge';

            if ($action === 'munic' && !empty($_FILES['munic_file'])) {
                $file = $_FILES['munic_file'];
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    Session::flash('error', 'Erro ao fazer upload do arquivo.');
                    redirect('/admin/observabilidade');
                }

                $allowedExtensions = ['csv', 'txt', 'zip'];
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions)) {
                    Session::flash('error', 'Extensão inválida. Use .csv, .txt ou .zip');
                    redirect('/admin/observabilidade');
                }

                $maxSize = 50 * 1024 * 1024;
                if ($file['size'] > $maxSize) {
                    Session::flash('error', 'Arquivo muito grande. Máximo 50MB.');
                    redirect('/admin/observabilidade');
                }

                $importService = new \App\Services\MunicipalityImportService();
                
                if ($extension === 'zip') {
                    $tempDir = sys_get_temp_dir() . '/munic_' . time();
                    $zipPath = $file['tmp_name'];
                    mkdir($tempDir);
                    
                    $zip = new \ZipArchive();
                    if ($zip->open($zipPath) === true) {
                        $zip->extractTo($tempDir);
                        $zip->close();
                    }
                    
                    $csvFiles = glob($tempDir . '/*.{csv,txt,CSV,TXT}', GLOB_BRACE);
                    $csvFile = $csvFiles[0] ?? null;
                    
                    if ($csvFile) {
                        $result = $importService->importMunicCsv($csvFile);
                    } else {
                        $result = ['success' => 0, 'errors' => 0, 'message' => 'Arquivo CSV não encontrado no ZIP'];
                    }
                    
                    array_map('unlink', glob($tempDir . '/*'));
                    rmdir($tempDir);
                } else {
                    $result = $importService->importMunicCsv($file['tmp_name']);
                }

                Session::flash('success', $result['message']);
                redirect('/admin/observabilidade');
            }

            if ($action === 'munic_url' && !empty($_POST['munic_url'])) {
                $url = $_POST['munic_url'];
                
                $importService = new \App\Services\MunicipalityImportService();
                Session::flash('info', 'Baixando arquivo da Receita Federal...');
                
                $result = $importService->importFromUrl($url);
                Session::flash('success', $result['message']);
                redirect('/admin/observabilidade');
            }

            $service = new IbgeService();
            $count = $service->syncAllMunicipalities();
            
            $db = \App\Core\Database::connection();
            $stmt = $db->query("SELECT COUNT(*) as total FROM municipalities");
            $total = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

            if ($count > 0) {
                if ($total >= 5500) {
                    Session::flash('success', "Sincronização completa! Total de {$total} municípios brasileiros.");
                } else {
                    Session::flash('success', "Sucesso! Foram sincronizados {$count} municipios. Total no banco: {$total}.");
                }
            } else {
                Session::flash('error', 'Nenhum município foi sincronizado. Verifique a conexão com o IBGE.');
            }
        } catch (Throwable $e) {
            Session::flash('error', 'Erro na sincronização: ' . $e->getMessage());
        }

        redirect('/admin#observabilidade');
    }

    public function syncMunicipalityEnrichment(): void
    {

        $type = $_POST['type'] ?? '';
        $service = new IbgeService();
        $count = 0;
        $label = "";

        try {
            switch ($type) {
                case 'population':
                    $count = $service->syncBulkPopulation();
                    $label = "População (Censo 2022)";
                    break;
                case 'gdp':
                    $results = $service->syncBulkGdp();
                    $count = $results['total'];
                    $label = "PIB Total e Per Capita (2021)";
                    break;
                case 'frota':
                    $count = $service->syncBulkFrota();
                    $label = "Frota de Veículos (2022)";
                    break;
                case 'companies':
                    $count = $service->syncBulkCompanies();
                    $label = "Unidades Locais/Empresas (2021)";
                    break;
                case 'ddd':
                    $count = $service->syncBulkDdd();
                    $label = "Mapeamento de DDD Principal";
                    break;
                default:
                    Session::flash('error', 'Tipo de enriquecimento inválido.');
                    redirect('/admin#observabilidade');
            }

            if ($count > 0) {
                Session::flash('success', "Sucesso! Dados de {$label} atualizados para {$count} municípios.");
            } else {
                Session::flash('error', "Nenhum dado de {$label} foi sincronizado. Verifique a API do IBGE.");
            }
        } catch (\Throwable $e) {
            Session::flash('error', "Erro no enriquecimento ({$type}): " . $e->getMessage());
        }

        redirect('/admin#observabilidade');
    }

    public function syncCnaeActivities(): void
    {
        try {
            $activityController = new \App\Controllers\ActivityController();
            $count = $activityController->syncCnae();
            
            $db = \App\Core\Database::connection();
            $stmt = $db->query("SELECT COUNT(*) as total FROM cnae_activities");
            $total = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
            
            Session::flash('success', "CNAEs sincronizados: {$count}. Total no banco: {$total}");
        } catch (\Throwable $e) {
            Session::flash('error', 'Erro ao sincronizar CNAEs: ' . $e->getMessage());
        }

        redirect('/admin#observabilidade');
    }

    public function apiTester(): void
    {
        redirect('/admin#api-tester');
    }

    public function runTest(): void
    {

        $api = (string) ($_POST['api'] ?? '');
        $param = (string) ($_POST['param'] ?? '');
        $start = microtime(true);

        try {
            $result = match($api) {
                'cnpj' => (new \App\Services\CnpjService())->findOrFetch($param ?: '00000000000191'),
                'ibge' => (function() use ($param) {
                    $ibge = new \App\Services\IbgeService();
                    $param = strtoupper(trim($param ?: 'SP'));

                    if (strlen($param) === 2 && !is_numeric($param)) {
                        return $ibge->fetchStateStats($param);
                    }

                    $code = (int) preg_replace('/\D+/', '', $param);
                    if ($code === 0) {
                        $code = 3550308;
                    }
                    return $ibge->fetchMunicipalityStats($code, true);
                })(),
                'bcb' => (function() use ($param) {
                    $repo = new \App\Repositories\ExchangeRateRepository();
                    $currency = strtoupper($param ?: 'USD');
                    $dbRates = $repo->getLatestRates();
                    foreach ($dbRates as $rate) {
                        if ($rate['currency_code'] === $currency) {
                            return [
                                'source' => 'database',
                                'cotacaoCompra' => $rate['cotacao_compra'],
                                'cotacaoVenda' => $rate['cotacao_venda'],
                                'dataCotacao' => $rate['data_cotacao'],
                            ];
                        }
                    }
                    return (new \App\Services\BcbService())->getLatestExchangeRate($currency);
                })(),
                'news' => (new \App\Services\MarketIntelligenceService())->getCompanyNews($param ?: 'tecnologia'),
                'compliance' => (new \App\Services\ComplianceService())->checkSanctions($param ?: '00000000000191'),
                'cptec' => (function() use ($param) {
                    $cptec = new \App\Services\CptecService();
                    $ibgeCode = (int) preg_replace('/\D+/', '', $param ?: '3550308');
                    if ($ibgeCode === 0) {
                        $ibgeCode = 3550308;
                    }
                    return $cptec->fetchWeatherByIbge($ibgeCode) ?? ['error' => 'Clima nao disponivel para este IBGE'];
                })(),
                'ddd' => (function() use ($param) {
                    $ddd = preg_replace('/\D+/', '', $param ?: '11');
                    $service = new \App\Services\DddService();
                    return $service->fetchDddInfo($ddd);
                })(),
                'nominatim' => (function() use ($param) {
                    $nominatim = new \App\Services\NominatimService();
                    $query = trim($param ?: 'Sao Paulo, SP, Brasil');
                    return $nominatim->search($query);
                })(),
                'receitaws' => (function() use ($param) {
                    $cnpj = preg_replace('/[^A-Za-z0-9]/', '', $param ?: '00000000000191');
                    $url = 'https://receitaws.com.br/v1/cnpj/' . $cnpj;
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 15,
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_HTTPHEADER => ['Accept: application/json'],
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($httpCode === 200 && $response) {
                        $data = json_decode($response, true);
                        return [
                            'status' => $data['status'] ?? 'unknown',
                            'nome' => $data['nome'] ?? '',
                            'fantasia' => $data['fantasia'] ?? '',
                            'uf' => $data['uf'] ?? '',
                            'municipio' => $data['municipio'] ?? '',
                            'atividade_principal' => $data['atividade_principal'] ?? [],
                        ];
                    }
                    return ['error' => "HTTP $httpCode"];
                })(),
                default => throw new \Exception('API nao reconhecida: ' . $api)
            };

            $result = $this->normalizeApiTesterResult($api, $result);

            $duration = round((microtime(true) - $start) * 1000, 2);
            Response::json([
                'ok' => true,
                'duration_ms' => $duration,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'ok' => false,
                'error' => $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
            ], 400);
        }
    }

    private function normalizeApiTesterResult(string $api, mixed $result): mixed
    {
        if (!is_array($result)) {
            return $result;
        }

        return match ($api) {
            'ibge' => $this->normalizeIbgeResult($result),
            'cnpj' => $this->normalizeCnpjResult($result),
            'compliance' => $this->normalizeComplianceResult($result),
            default => $result,
        };
    }

    private function normalizeIbgeResult(array $result): array
    {
        if (array_key_exists('capital_city', $result)) {
            return [
                'population' => (int) ($result['population'] ?? 0),
                'gdp' => (float) ($result['gdp'] ?? 0),
                'gdp_per_capita' => (float) ($result['gdp_per_capita'] ?? 0),
                'gdp_agri' => (float) ($result['gdp_agri'] ?? 0),
                'gdp_industry' => (float) ($result['gdp_industry'] ?? 0),
                'gdp_services' => (float) ($result['gdp_services'] ?? 0),
                'gdp_admin' => (float) ($result['gdp_admin'] ?? 0),
                'area_km2' => (float) ($result['area_km2'] ?? 0),
                'capital_city' => (string) ($result['capital_city'] ?? 'Nao informado'),
            ];
        }

        return [
            'ibge_code' => (int) ($result['ibge_code'] ?? 0),
            'population' => (int) ($result['population'] ?? 0),
            'gdp' => (float) ($result['gdp'] ?? 0),
            'gdp_per_capita' => (float) ($result['gdp_per_capita'] ?? 0),
            'gdp_agri' => (float) ($result['gdp_agri'] ?? 0),
            'gdp_industry' => (float) ($result['gdp_industry'] ?? 0),
            'gdp_services' => (float) ($result['gdp_services'] ?? 0),
            'gdp_admin' => (float) ($result['gdp_admin'] ?? 0),
            'vehicle_fleet' => (int) ($result['vehicle_fleet'] ?? 0),
            'business_units' => (int) ($result['business_units'] ?? 0),
            'area_km2' => (float) ($result['area_km2'] ?? 0),
        ];
    }

    private function normalizeCnpjResult(array $result): array
    {
        $result['address_complement'] = (string) ($result['address_complement'] ?? '');
        $result['municipal_ibge_code'] = (int) ($result['municipal_ibge_code'] ?? 0);
        $result['simples_opt_in'] = $result['simples_opt_in'] ?? 'nao_informado';
        $result['mei_opt_in'] = $result['mei_opt_in'] ?? 'nao_informado';

        if (isset($result['raw_data']) && is_string($result['raw_data'])) {
            $raw = json_decode($result['raw_data'], true);
            if (is_array($raw)) {
                $raw['_compliance'] = $this->normalizeComplianceResult(is_array($raw['_compliance'] ?? null) ? $raw['_compliance'] : []);
                $raw['_cep_details'] = is_array($raw['_cep_details'] ?? null) ? $raw['_cep_details'] : [];
                $raw['_cep_details']['ibge_code'] = (int) ($raw['_cep_details']['ibge_code'] ?? ($raw['_cep_details']['ibge'] ?? 0));
                $raw['_cep_details']['ibge'] = (int) ($raw['_cep_details']['ibge'] ?? 0);
                $result['raw_data'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $result;
    }

    private function normalizeComplianceResult(array $result): array
    {
        $details = is_array($result['details'] ?? null) ? $result['details'] : [];

        return [
            'status' => (string) ($result['status'] ?? 'partial'),
            'total_sanctions' => (int) ($result['total_sanctions'] ?? 0),
            'details' => [
                'ceis' => is_array($details['ceis'] ?? null) ? $details['ceis'] : [],
                'cnep' => is_array($details['cnep'] ?? null) ? $details['cnep'] : [],
                'cepim' => is_array($details['cepim'] ?? null) ? $details['cepim'] : [],
            ],
            'message' => (string) ($result['message'] ?? ''),
        'last_check' => (string) ($result['last_check'] ?? date(self::DATE_FORMAT)),
        ];
    }

    public function clearLogs(): void
    {
        $logFile = base_path('storage/logs/app.log');
        file_put_contents($logFile, date(self::DATE_FORMAT) . " - clearLogs called\n", FILE_APPEND);
        

        $type = $_POST['type'] ?? 'all';
        $cleared = [];
        $errors = [];

        $logDir = base_path('storage/logs');
        
        $patterns = [
            'app' => 'app*.log',
            'setup' => 'setup*.log',
            'php_errors' => 'php_errors*.log',
            'cms' => 'cms*.log',
        ];

        $toClear = match ($type) {
            'app' => ['app'],
            'setup' => ['setup'],
            'php_errors' => ['php_errors'],
            default => ['app', 'setup', 'php_errors'],
        };

        foreach ($toClear as $key) {
            $pattern = $patterns[$key];
            $files = glob($logDir . '/' . $pattern);
            
            if (empty($files)) {
                $cleared[] = $key . ' (nenhum arquivo encontrado)';
                continue;
            }

            $clearedCount = 0;
            error_log('clearLogs: processing ' . count($files) . ' files');
            foreach ($files as $file) {
                error_log('clearLogs: file=' . $file . ' writable=' . (is_writable($file) ? 'yes' : 'no'));
                try {
                    $result = file_put_contents($file, '');
                    error_log('clearLogs: result=' . $result);
                    if ($result !== false) {
                        $clearedCount++;
                    } else {
                        $errors[] = basename($file) . ' (falha)';
                    }
                } catch (\Throwable $e) {
                    error_log('clearLogs: error=' . $e->getMessage());
                    $errors[] = basename($file) . ' (' . $e->getMessage() . ')';
                }
            }
            if ($clearedCount > 0) {
                $cleared[] = $key . ' (' . $clearedCount . ' arquivo(s))';
            }
        }

        if (!empty($errors)) {
            Session::flash('error', 'Falha ao limpar logs: ' . implode(', ', $errors));
        } elseif (!empty($cleared)) {
            Session::flash('success', 'Logs processados: ' . implode(', ', $cleared));
        } else {
            Session::flash('info', 'Nenhum log para limpar.');
        }

        redirect('/admin#observabilidade');
    }

    public function testClearLogs(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Test endpoint called at " . date(self::DATE_FORMAT) . "\n";
        
        $logDir = base_path('storage/logs');
        echo "Log dir: $logDir\n";
        
        $files = glob($logDir . '/app*.log');
        echo "Files found: " . count($files) . "\n";
        
        foreach ($files as $file) {
            $result = file_put_contents($file, '');
            echo basename($file) . " -> result: $result\n";
        }
        
        echo "Done\n";
    }

    public function runPhpstan(): void
    {

        $result = [
            'success' => false,
            'output' => '',
            'errors' => 0,
            'warnings' => 0,
        ];

        $phpstanPath = base_path('vendor/bin/phpstan');
        
        if (!file_exists($phpstanPath)) {
            $result['output'] = 'PHPStan não está instalado no servidor de produção.';
            $result['output'] .= "\n\nPara usar, execute localmente:\ncd /caminho/do/projeto\ncomposer install --dev\ncomposer analyse";
            Session::flash('warning', 'PHPStan não disponível no servidor (execute localmente)');
            $_SESSION['phpstan_result'] = $result;
            redirect('/admin#seguranca');
        }

        if (!function_exists('shell_exec')) {
            $result['output'] = 'Funções de execução de comandos não estão disponíveis neste servidor (shell_exec desabilitado por segurança).';
            $result['output'] .= "\n\nPara usar, execute localmente:\ncomposer install --dev\ncomposer analyse";
            Session::flash('warning', 'Execução de comandos não disponível no servidor');
            $_SESSION['phpstan_result'] = $result;
            redirect('/admin#seguranca');
        }

        try {
            $cmd = $phpstanPath . ' analyse --no-progress --error-format=raw 2>&1';
            
            $outputText = @shell_exec($cmd);
            
            if (!is_string($outputText)) {
                $outputText = 'Erro ao executar PHPStan.';
            }
            
            preg_match_all('/^\s*Line\s+(\d+)/m', $outputText, $matches);
            $errors = count($matches[1]);
            
            $result['success'] = $errors === 0;
            $result['output'] = $outputText;
            $result['errors'] = $errors;
            $result['warnings'] = substr_count($outputText, 'warning');

            if ($errors === 0) {
                Session::flash('success', 'PHPStan: Nenhum erro encontrado!');
            } else {
                Session::flash('warning', "PHPStan: {$errors} problemas encontrados");
            }
        } catch (Throwable $e) {
            $result['output'] = 'Erro ao executar PHPStan: ' . $e->getMessage();
            Session::flash('error', $result['output']);
        }

        $_SESSION['phpstan_result'] = $result;
        
        redirect('/admin#seguranca');
    }

    public function getPhpstanFromGithub(): void
    {
        $token = $_ENV['GITHUB_TOKEN'] ?? $_ENV['GH_TOKEN'] ?? '';
        
        if (empty($token)) {
            Response::json([
                'success' => false,
                'error' => 'GITHUB_TOKEN não configurado. Adicione no .env: GITHUB_TOKEN=seu_token_aqui',
                'setup' => true
            ]);
        }

        $owner = $_ENV['GITHUB_REPO_OWNER'] ?? 'anomalyco';
        $repo = $_ENV['GITHUB_REPO_NAME'] ?? 'platadata-cms';
        
        $ch = curl_init("https://api.github.com/repos/{$owner}/{$repo}/actions/runs?per_page=10");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $token,
                'X-GitHub-Api-Version: 2022-11-28'
            ],
            CURLOPT_USERAGENT => 'Platadata-CMS/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            Response::json([
                'success' => false,
                'error' => 'Erro ao acessar GitHub API (HTTP ' . $httpCode . '). Verifique o token e as variáveis GITHUB_REPO_OWNER/GITHUB_REPO_NAME.'
            ]);
        }
        
        $data = json_decode($response, true);
        
        if (empty($data['workflow_runs'])) {
            Response::json([
                'success' => false,
                'error' => 'Nenhum workflow run encontrado.'
            ]);
        }
        
        $runs = $data['workflow_runs'];
        
        $result = [
            'success' => true,
            'runs' => []
        ];
        
        $phpRuns = array_filter($runs, fn($r) => str_contains($r['path'] ?? '', 'php.yml'));
        $phpRuns = array_slice($phpRuns, 0, 5);
        
        foreach ($phpRuns as $run) {
            $status = $run['status'] ?? 'unknown';
            $conclusion = $run['conclusion'] ?? null;
            $runId = $run['id'] ?? 0;
            
            $jobStatus = 'pending';
            if ($status === 'completed') {
                $jobStatus = ($conclusion === 'success') ? 'success' : 'failure';
            } elseif ($status === 'in_progress') {
                $jobStatus = 'running';
            }
            
            $runData = [
                'id' => $runId,
                'number' => $run['run_number'] ?? 0,
                'branch' => $run['head_branch'] ?? 'unknown',
                'status' => $jobStatus,
                'conclusion' => $conclusion,
                'created_at' => $run['created_at'] ?? '',
                'updated_at' => $run['updated_at'] ?? '',
                'html_url' => $run['html_url'] ?? '',
                'message' => $run['head_commit']['message'] ?? ''
            ];
            
            if ($status === 'completed' && $conclusion !== 'success') {
                $runData['logs'] = $this->fetchJobLogs($owner, $repo, $runId, $token);
            }
            
            $result['runs'][] = $runData;
        }
        
        Response::json($result);
    }
    
    private function fetchJobLogs(string $owner, string $repo, int $runId, string $token): ?string
    {
        $ch = curl_init("https://api.github.com/repos/{$owner}/{$repo}/actions/runs/{$runId}/jobs");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $token,
                'X-GitHub-Api-Version: 2022-11-28'
            ],
            CURLOPT_USERAGENT => 'Platadata-CMS/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        $jobs = json_decode($response, true);
        
        if (empty($jobs['jobs'])) {
            return null;
        }
        
        foreach ($jobs['jobs'] as $job) {
            $jobId = $job['id'] ?? null;
            if (!$jobId) continue;
            
            $ch = curl_init("https://api.github.com/repos/{$owner}/{$repo}/actions/jobs/{$jobId}/logs");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/vnd.github.v3+json',
                    'Authorization: Bearer ' . $token,
                    'X-GitHub-Api-Version: 2022-11-28'
                ],
                CURLOPT_USERAGENT => 'Platadata-CMS/1.0',
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $logsResponse = curl_exec($ch);
            $logsCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($logsCode === 200 && !empty($logsResponse)) {
                $lines = explode("\n", $logsResponse);
                $filteredLines = [];
                $inPhpstan = false;
                
                foreach ($lines as $line) {
                    if (stripos($line, 'phpstan') !== false || 
                        stripos($line, 'Error') !== false ||
                        stripos($line, 'Line') !== false ||
                        stripos($line, '------') !== false ||
                        stripos($line, 'composer') !== false ||
                        stripos($line, 'php') === 0 ||
                        $inPhpstan) {
                        $inPhpstan = true;
                        $filteredLines[] = $line;
                        if (count($filteredLines) > 100) break;
                    }
                }
                
                if (!empty($filteredLines)) {
                    return implode("\n", $filteredLines);
                }
            }
        }
        
        return null;
    }
    

    
    public function getRecentLogs(): void
    {
        $level = $_GET['level'] ?? null;
        $lines = min(100, max(10, (int) ($_GET['lines'] ?? 50)));
        
        $logs = \App\Core\Logger::getRecentLogs($lines, $level ?: null);
        Response::json([
            'success' => true,
            'count' => count($logs),
            'logs' => $logs
        ]);
    }
    {
        $hook = $_GET['hook'] ?? null;
        
        if ($hook) {
            $result = \App\Core\Cron::run($hook);
            Response::json($result);
            return;
        }
        
        $results = \App\Core\Cron::runDueTasks();
        Response::json([
            'success' => true,
            'tasks_run' => count($results),
            'results' => $results
        ]);
    }
    
    public function cronStatus(): void
    {
        $schedules = \App\Core\Cron::schedules();
        $status = [];
        $timestamp = time();
        
        foreach ($schedules as $hook => $interval) {
            $lastRun = (int) \App\Core\Cache::get("cron_last_run_{$hook}", 0);
            $due = $lastRun === 0 || $timestamp >= ($lastRun + $interval);
            
            $status[$hook] = [
                'interval_seconds' => $interval,
                'interval_human' => self::formatInterval($interval),
                'last_run' => $lastRun > 0 ? date(self::DATE_FORMAT, $lastRun) : 'nunca',
                'next_run' => $due ? 'agora' : date(self::DATE_FORMAT, $lastRun + $interval),
                'due' => $due,
            ];
        }
        
        View::render('admin/cron', [
            'title' => 'Tarefas Agendadas',
            'schedules' => $schedules,
            'status' => $status,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }
    
    private function formatInterval(int $seconds): string
    {
        if ($seconds < 60) return "{$seconds}s";
        if ($seconds < 3600) return floor($seconds / 60) . " min";
        if ($seconds < 86400) return floor($seconds / 3600) . "h";
        return floor($seconds / 86400) . " dias";
    }
}
