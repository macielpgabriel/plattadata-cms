<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\CompanyRepository;
use App\Repositories\FavoriteRepository;
use App\Repositories\CompanyTaxRepository;
use App\Repositories\ExchangeRateRepository;
use App\Services\CnpjService;
use App\Services\SimplesNacionalService;
use App\Services\LgpdComplianceService;
use App\Services\MailService;
use App\Services\RateLimiterService;
use App\Services\SetupService;
use App\Services\BcbService;
use App\Services\CptecService;
use App\Services\OpenMeteoService;
use App\Services\CompanyEnrichmentService;
use App\Services\SearchAnalyticsService;
use App\Services\CompanyChangeMonitorService;
use App\Services\ComplianceService;
use App\Services\AuditLogService;
use App\Repositories\MunicipalityRepository;
use App\Services\MarketIntelligenceService;
use App\Services\IbgeService;
use App\Controllers\Company\CompanySearchService;
use App\Controllers\Company\CompanyShowService;
use RuntimeException;

final class CompanyController
{
    private CnpjService $cnpjService;
    private CompanyRepository $companies;
    private FavoriteRepository $favorites;
    private MunicipalityRepository $municipalities;
    private MarketIntelligenceService $marketIntel;
    private CompanySearchService $searchService;
    private CompanyShowService $showService;
    private IbgeService $ibgeService;
    private SimplesNacionalService $simplesService;
    private CompanyEnrichmentService $enrichmentService;
    private RateLimiterService $rateLimiter;
    private MailService $mailService;
    private LgpdComplianceService $lgpdService;
    private CompanyChangeMonitorService $monitorService;

    public function __construct()
    {
        $this->cnpjService = new CnpjService();
        $this->companies = new CompanyRepository();
        $this->favorites = new FavoriteRepository();
        $this->municipalities = new MunicipalityRepository();
        $this->marketIntel = new MarketIntelligenceService();
        $this->searchService = new CompanySearchService();
        $this->showService = new CompanyShowService();
        $this->ibgeService = new IbgeService();
        $this->simplesService = new SimplesNacionalService();
        $this->enrichmentService = new CompanyEnrichmentService();
        $this->rateLimiter = new RateLimiterService();
        $this->mailService = new MailService();
        $this->lgpdService = new LgpdComplianceService();
        $this->monitorService = new CompanyChangeMonitorService();
    }

    public function indexByLocation(array $params): void
    {
        $uf = strtoupper(trim((string) ($params['uf'] ?? '')));
        $slug = trim((string) ($params['slug'] ?? ''));
        
        if ($uf === '' || $slug === '') {
            Response::notFound();
        }

        try {
            $muni = $this->searchService->getMunicipalityBySlug($slug, $uf);
            
            if (!$muni) {
                Response::notFound();
            }
            
            if (!empty($muni['slug']) && $muni['slug'] !== $slug) {
                redirect("/empresas/em/" . strtolower($uf) . "/" . $muni['slug']);
            }

            $term = (string) ($muni['name'] ?? '');
            $state = (string) ($muni['state_uf'] ?? $uf);
            $ibgeCode = (int) ($muni['ibge_code'] ?? 0);
            
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $configuredPerPage = (int) site_setting('companies_per_page', '15');
            $perPage = max(5, min($configuredPerPage, 100));

            $result = $this->searchService->searchByMunicipality($ibgeCode, $term, $state, $page, $perPage);

            View::render('companies/index', [
                'title' => "Empresas em {$term} - {$state}",
                'items' => $result['data'] ?? [],
                'total' => $result['total'] ?? 0,
                'page' => $result['page'] ?? $page,
                'lastPage' => $result['last_page'] ?? 1,
                'term' => $term,
                'state' => $state,
                'location' => $muni,
                'metaTitle' => "Empresas em {$term} ({$state}) | Consulta CNPJ",
                'metaDescription' => "Lista de empresas e dados do CNPJ em {$term}, {$state}.",
            ]);
        } catch (\Throwable $e) {
            Logger::error("Erro em indexByLocation: " . $e->getMessage());
            redirect("/empresas?q=" . urlencode($slug) . "&state=" . urlencode($uf));
        }
    }

    public function index(): void
    {
        if (!(new SetupService())->isDatabaseReady()) {
            redirect('/install');
        }

        $format = $_GET['format'] ?? '';
        $state = strtoupper(trim((string) ($_GET['state'] ?? '')));
        $state = preg_match('/^[A-Z]{2}$/', $state) ? $state : '';

        if ($format === 'json' && $state) {
            header('Content-Type: application/json; charset=utf-8');
            try {
                $cities = $this->searchService->getCitiesByState($state);
                echo json_encode(['cities' => $cities], JSON_UNESCAPED_UNICODE);
            } catch (\Exception $e) {
                echo json_encode(['error' => 'Erro'], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        $term = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $configuredPerPage = (int) site_setting('companies_per_page', '15');
        $perPage = max(5, min($configuredPerPage, 100));

        $result = $this->searchService->searchByTerm($term, $state, $page, $perPage);

        View::render('companies/index', [
            'title' => 'Empresas',
            'items' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'lastPage' => $result['last_page'],
            'term' => $term,
            'state' => $state,
            'location' => null,
            'metaTitle' => 'Consulta de Empresas e CNPJ',
            'metaDescription' => 'Busque empresas por CNPJ, razão social, cidade e UF.',
        ]);
    }

    public function exportCsv(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));
        $state = strtoupper(trim((string) ($_GET['state'] ?? '')));
        
        $this->searchService->exportToCsv($term, $state);
    }

    public function searchForm(): void
    {
        if (!Auth::can(['admin', 'editor'])) {
            http_response_code(403);
            echo 'Seu perfil nao possui permissao para consultar novos CNPJs.';
            return;
        }

        View::render('companies/search', [
            'title' => 'Consultar CNPJ',
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function search(): void
    {
        if (!Auth::can(['admin', 'editor'])) {
            http_response_code(403);
            return;
        }

        $limitError = $this->enforceAuthenticatedSearchRateLimit();
        if ($limitError !== null) {
            Session::flash('error', $limitError);
            redirect('/empresas/busca');
        }

        $cnpj = $this->cnpjService->sanitize((string) ($_POST['cnpj'] ?? ''));

        if (!$this->cnpjService->validate($cnpj)) {
            Session::flash('error', 'CNPJ invalido.');
            redirect('/empresas/busca');
        }

        try {
            $existedBefore = $this->companies->findByCnpj($cnpj, true) !== null;
            $company = $this->cnpjService->findOrFetch($cnpj);
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
            redirect('/empresas/busca');
        }

        $user = Auth::user();
        if (!empty($company['id']) && !empty($user['id'])) {
            $this->companies->logSearch((int) $company['id'], (int) $user['id'], (string) ($company['source'] ?? 'unknown'), $this->clientIp());
            AuditLogService::log((int) $user['id'], 'access', 'company', (int) $company['id'], null, ['cnpj' => $cnpj]);
        }

        if (!empty($company['id']) && !$existedBefore) {
            AuditLogService::logCreate((int) ($user['id'] ?? 0), 'company', (int) $company['id'], $company);
        }

        $source = ($company['source'] ?? '') === 'api' ? 'API externa' : 'cache local';
        Session::flash('success', 'Consulta concluida via ' . $source . '.');
        redirect('/empresas/' . $cnpj);
    }

    public function refresh(array $params): void
    {
        $cnpj = $this->cnpjService->sanitize((string) ($params['cnpj'] ?? ''));
        $company = $this->companies->findByCnpj($cnpj);

        if (!$company) {
            redirect('/empresas/busca');
        }

        $oldCompanyData = $company;
        $lastSync = strtotime($company['last_synced_at'] ?? '2000-01-01');
        $daysSinceSync = (time() - $lastSync) / 86400;
        $isStaff = Auth::can(['admin', 'editor']);
        $userCooldownDays = max(1, (int) config('app.cnpj.refresh.user_cooldown_days', 15));
        $refreshRateLimitPerHour = max(1, (int) config('app.cnpj.refresh.rate_limit_per_hour', 10));

        if (!$isStaff && $daysSinceSync <= $userCooldownDays) {
            Session::flash('error', 'Esta empresa foi atualizada recentemente.');
            redirect('/empresas/busca');
            return;
        }

        $limitError = $isStaff ? $this->enforceAuthenticatedSearchRateLimit() : null;
        if ($limitError !== null) {
            Session::flash('error', $limitError);
            redirect('/empresas/' . $cnpj);
            return;
        }

        $user = Auth::user();
        $refreshScope = $user ? 'user_' . $user['id'] : 'ip_' . $this->clientIp();
        $refreshLimit = $this->rateLimiter->hit('company_refresh', $refreshScope, $refreshRateLimitPerHour, 3600);

        if (empty($refreshLimit['success'])) {
            $minutes = (int) ceil($refreshLimit['retry_after'] / 60);
            Session::flash('error', "Limite de atualizacoes atingido. Tente novamente em {$minutes} minutos.");
            redirect('/empresas/' . $cnpj);
            return;
        }

        if (!$this->cnpjService->validate($cnpj)) {
            Session::flash('error', 'CNPJ invalido.');
            redirect('/empresas/' . $cnpj);
            return;
        }

        try {
            $company = $this->cnpjService->refreshFromApi($cnpj);
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
            redirect('/empresas/' . $cnpj);
            return;
        }

        if (!empty($company['id']) && !empty($user['id'])) {
            $this->companies->logSearch((int) $company['id'], (int) $user['id'], 'refresh', $this->clientIp());

            $oldData = [
                'legal_name' => $oldCompanyData['legal_name'] ?? null,
                'trade_name' => $oldCompanyData['trade_name'] ?? null,
                'status' => $oldCompanyData['status'] ?? null,
                'last_synced_at' => $oldCompanyData['last_synced_at'] ?? null,
            ];
            $newData = [
                'legal_name' => $company['legal_name'] ?? null,
                'trade_name' => $company['trade_name'] ?? null,
                'status' => $company['status'] ?? null,
                'last_synced_at' => $company['last_synced_at'] ?? null,
            ];
            AuditLogService::logUpdate((int) $user['id'], 'company', (int) $company['id'], $oldData, $newData);
        }

        $adminEmail = (string) config('mail.admin_email', '');
        if ($adminEmail !== '') {
            $operator = (string) ($user['name'] ?? 'Sistema');
            $this->mailService->send(
                $adminEmail,
                'CNPJ atualizado no CMS',
                "Empresa {$cnpj} foi atualizada por {$operator}."
            );
        }

        Session::flash('success', 'Dados atualizados com sucesso via API externa.');
        redirect('/empresas/' . $cnpj);
    }

    public function show(array $params): void
    {
        if (!(new SetupService())->isDatabaseReady()) {
            redirect('/install');
            return;
        }

        $cnpj = $this->cnpjService->sanitize((string) ($params['cnpj'] ?? ''));
        $marketNews = [];
        $company = $this->companies->findByCnpj($cnpj, true);

        if (!$company) {
            Session::flash('error', 'Empresa ainda nao cadastrada no cache local.');
            redirect('/empresas/busca');
            return;
        }

        $company = $this->applyCompanyDefaults($company);

        if (!empty($company['id'])) {
            $this->companies->incrementViews((int)$company['id']);

            $user = Auth::user();
            if (!empty($user['id'])) {
                AuditLogService::log((int) $user['id'], 'access', 'company', (int) $company['id'], null, ['cnpj' => $cnpj]);
            }
        }

        if (!empty($company['is_hidden'])) {
            $infoMessage = Auth::can(['admin', 'editor']) 
                ? 'AVISO: Esta empresa está OCULTA para o público.' 
                : 'Esta empresa foi removida a pedido do proprietário.';
            Session::flash('info', $infoMessage);
        }

        $rawData = json_decode((string) $company['raw_data'], true) ?: [];
        $rawData = $this->harmonizeRawData($rawData, $company);

        if ($this->shouldAutoRefreshOnShow($company, $rawData, $cnpj)) {
            try {
                $this->cnpjService->refreshFromApi($cnpj);
                $reloaded = $this->companies->findByCnpj($cnpj, true);
                if (is_array($reloaded)) {
                    $company = $this->applyCompanyDefaults($reloaded);
                    $rawData = json_decode((string) $company['raw_data'], true) ?: [];
                    $rawData = $this->harmonizeRawData($rawData, $company);
                }
            } catch (\Throwable $e) {
                Logger::warning('Auto refresh da pagina da empresa falhou.', [
                    'cnpj' => $cnpj,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $company['trade_name'] = $this->firstNonEmptyString([
            $company['trade_name'] ?? null,
            $rawData['nome_fantasia'] ?? null,
            $rawData['fantasia'] ?? null,
            $rawData['trade_name'] ?? null,
        ]);
        
        $company['status'] = $this->firstNonEmptyString([
            $company['status'] ?? '',
            $rawData['status'] ?? '',
            $rawData['situacao'] ?? '',
            $rawData['situacao_cadastral'] ?? '',
        ]);
        
        $company['city'] = $this->firstNonEmptyString([
            $company['city'] ?? null,
            $rawData['municipio'] ?? null,
            $rawData['cidade'] ?? null,
            $rawData['city'] ?? null,
        ]);
        $company['state'] = strtoupper($this->firstNonEmptyString([
            $company['state'] ?? null,
            $rawData['uf'] ?? null,
            $rawData['estado'] ?? null,
            $rawData['state'] ?? null,
        ]));

        if (isset($rawData['email']) && is_string($rawData['email']) && trim($rawData['email']) !== '') {
            $company['email'] = $rawData['email'];
        }

        $company['phone'] = $this->firstNonEmptyString([
            $company['phone'] ?? '',
            $rawData['ddd_telefone_1'] ?? '',
            $rawData['telefone'] ?? '',
            $rawData['phone'] ?? '',
            $rawData['telefone1'] ?? '',
        ]);

        $qsa = is_array($rawData['qsa'] ?? null) ? $rawData['qsa'] : [];
        
        $secondaryCnaes = $this->enrichSecondaryCnaes($this->normalizeSecondaryCnaes($rawData['cnaes_secundarios'] ?? []));
        $mainCnae = $this->resolveMainCnae($rawData);
        
        $municipalityDetails = is_array($rawData['_municipality_details'] ?? null) ? $rawData['_municipality_details'] : [];
        
        if (empty($municipalityDetails['regiao']) && !empty($company['state'])) {
            $municipalityDetails['regiao'] = $this->ibgeService->getRegionByUf((string)$company['state']);
        }
        
        $enrichment = is_array($rawData['_enrichment'] ?? null) ? $rawData['_enrichment'] : [];
        $mapLinks = is_array($rawData['_map_links'] ?? null) ? $rawData['_map_links'] : [];

        $address = $this->showService->formatAddress($rawData);
        $mapEmbedUrl = 'https://www.google.com/maps?q=' . rawurlencode($address ?: ($company['city'] ?? '') . ' ' . ($company['state'] ?? '')) . '&output=embed';

        $weather = null;
        $ibgeCode = (int) ($company['municipal_ibge_code'] ?? 0);
        
        $snapshots = !empty($company['id']) ? $this->companies->getSnapshots((int) $company['id']) : [];
        $queryHistory = !empty($company['id']) ? $this->companies->getQueryHistory((int) $company['id']) : [];
        $dbEnrichment = !empty($company['id']) ? $this->companies->findEnrichmentByCompanyId((int) $company['id']) : null;
        
        $taxData = null;
        try {
            if (!empty($company['id'])) {
                $taxData = $this->simplesService->getTaxData((int) $company['id'], $cnpj);
            }
        } catch (\Throwable $e) {
        }

        $user = Auth::user();
        $isFavorite = false;
        try {
            if (!empty($user['id']) && !empty($company['id'])) {
                $isFavorite = $this->favorites->isFavorite((int) $user['id'], (int) $company['id']);
            }
        } catch (\PDOException $e) {
            $isFavorite = false;
        }

        $stats = [
            'qsa_count' => count($qsa),
            'total_cnae_count' => count($secondaryCnaes) + (!empty($mainCnae['codigo']) ? 1 : 0),
            'secondary_cnae_count' => count($secondaryCnaes),
            'snapshot_count' => count($snapshots),
            'query_count' => count($queryHistory),
        ];

        try {
            $tradeName = trim((string) ($company['trade_name'] ?? ''));
            $legalName = trim((string) ($company['legal_name'] ?? ''));
            $query = $tradeName !== '' ? $tradeName : $legalName;

            if ($query !== '') {
                $marketNews = $this->marketIntel->getCompanyNews($query, 5) ?: [];
            }
        } catch (\Throwable $e) {
            $marketNews = [];
        }

        $enrichedData = [];
        try {
            $rawDataForEnrichment = is_array($company['raw_data'] ?? null) 
                ? $company['raw_data'] 
                : (json_decode((string) $company['raw_data'], true) ?: []);
            
            $cnaesSecundarios = $rawDataForEnrichment['cnaes_secundarios'] ?? $rawDataForEnrichment['atividades_secundarias'] ?? $rawDataForEnrichment['cnaes'] ?? [];
            $natureza = $rawDataForEnrichment['natureza_juridica'] ?? ($company['legal_nature'] ?? null);
            
            $enrichedData = @$this->enrichmentService->enrichCompany([
                'id' => $company['id'] ?? null,
                'cnpj' => $cnpj,
                'cnae_main_code' => $company['cnae_main_code'] ?? null,
                'city' => $company['city'] ?? null,
                'state' => $company['state'] ?? null,
                'cnaes_secundarios' => $cnaesSecundarios,
                'natureza_juridica' => $natureza,
                'raw_data' => $company['raw_data'] ?? null,
            ]);
        } catch (\Throwable $e) {
            error_log("Enrichment error: " . $e->getMessage());
        }
        
        $statusLabel = strtolower((string) ($company["status"] ?? "ativa"));
        $metaDescription = "Consulte o CNPJ $cnpj - " . ($company["legal_name"] ?? "") . " - empresa $statusLabel em " . ($company["city"] ?? "") . "/" . ($company["state"] ?? "");

        $usdRate = null;
        $capitalUsd = null;
        $capitalBrl = (float) ($company['capital_social'] ?? 0);
        if ($capitalBrl > 0) {
            try {
                $repo = new \App\Repositories\ExchangeRateRepository();
                $dbRates = $repo->getLatestRates();
                $usdData = null;
                if (!empty($dbRates)) {
                    foreach ($dbRates as $rate) {
                        if ($rate['currency_code'] === 'USD') {
                            $usdData = [
                                'cotacaoCompra' => $rate['cotacao_compra'],
                                'cotacaoVenda' => $rate['cotacao_venda'],
                            ];
                            break;
                        }
                    }
                }
                if ($usdData && isset($usdData['cotacaoCompra'])) {
                    $usdRate = (float) $usdData['cotacaoCompra'];
                    $capitalUsd = $capitalBrl / $usdRate;
                }
            } catch (\Throwable $e) {
            }
        }
        
        // Debug mode - return JSON instead of rendering view
        if (!empty($_GET['debug']) && $_GET['debug'] === '1') {
            Response::json([
                'cnpj' => $cnpj,
                'company_found' => $company ? true : false,
                'company_id' => $company['id'] ?? null,
                'company_name' => $company['legal_name'] ?? null,
                'variables_passed_to_view' => [
                    'title' => 'Empresa ' . $cnpj,
                    'company' => is_array($company) ? 'array (' . count($company) . ' items)' : gettype($company),
                    'rawData' => is_array($rawData) ? 'array (' . count($rawData) . ' items)' : gettype($rawData),
                    'cnpj' => $cnpj,
                    'qsa' => is_array($qsa) ? 'array (' . count($qsa) . ' items)' : gettype($qsa),
                    'mainCnae' => is_array($mainCnae) ? 'array (' . count($mainCnae) . ' items)' : gettype($mainCnae),
                    'secondaryCnaes' => is_array($secondaryCnaes) ? 'array (' . count($secondaryCnaes) . ' items)' : gettype($secondaryCnaes),
                    'enrichment' => is_array($enrichment) ? 'array (' . count($enrichment) . ' items)' : gettype($enrichment),
                    'marketNews' => is_array($marketNews ?? null) ? 'array (' . count($marketNews ?? []) . ' items)' : gettype($marketNews ?? []),
                    'mapLinks' => is_array($mapLinks) ? 'array (' . count($mapLinks) . ' items)' : gettype($mapLinks),
                    'address' => gettype($address),
                    'mapEmbedUrl' => gettype($mapEmbedUrl),
                    'snapshots' => is_array($snapshots) ? 'array (' . count($snapshots) . ' items)' : gettype($snapshots),
                    'queryHistory' => is_array($queryHistory) ? 'array (' . count($queryHistory) . ' items)' : gettype($queryHistory),
                    'dbEnrichment' => gettype($dbEnrichment),
                    'taxData' => gettype($taxData),
                    'stats' => is_array($stats) ? 'array (' . count($stats) . ' items)' : gettype($stats),
                    'isFavorite' => gettype($isFavorite),
                    'weather' => gettype($weather),
                    'weatherIbgeCode' => gettype($ibgeCode),
                    'enrichedData' => is_array($enrichedData) ? 'array (' . count($enrichedData) . ' items)' : gettype($enrichedData),
                    'usdRate' => gettype($usdRate),
                    'capitalUsd' => gettype($capitalUsd),
                ],
                'message' => 'Debug data retrieved successfully'
            ]);
            return;
        }
        
        View::render('companies/show', [
            'title' => 'Empresa ' . $cnpj,
            'company' => $company,
            'rawData' => $rawData,
            'cnpj' => $cnpj,
            'qsa' => $qsa,
            'mainCnae' => $mainCnae,
            'secondaryCnaes' => $secondaryCnaes,
            'enrichment' => $enrichment,
            'marketNews' => $marketNews ?? [],
            'mapLinks' => $mapLinks,
            'address' => $address,
            'mapEmbedUrl' => $mapEmbedUrl,
            'snapshots' => $snapshots,
            'queryHistory' => $queryHistory,
            'dbEnrichment' => $dbEnrichment,
            'taxData' => $taxData,
            'stats' => $stats,
            'isFavorite' => $isFavorite,
            'coordinates' => null,
            'weather' => $weather,
            'weatherIbgeCode' => $ibgeCode,
            'enrichedData' => $enrichedData,
            'usdRate' => $usdRate,
            'capitalUsd' => $capitalUsd,
            'flash' => Session::flash('success'),
            'info' => Session::flash('info'),
            'error' => Session::flash('error'),
            'metaTitle' => ($company['legal_name'] ?? 'Empresa') . ' - CNPJ ' . $cnpj . ' | Plattadata',
            'metaDescription' => $metaDescription,
        ]);
    }

    public function delete(array $params): void
    {
        if (!Auth::can(['admin'])) {
            http_response_code(403);
            echo 'Seu perfil nao possui permissao para excluir empresas.';
            return;
        }

        $cnpj = $this->cnpjService->sanitize((string) ($params['cnpj'] ?? ''));
        $company = $this->companies->findByCnpj($cnpj);
        $user = Auth::user();

        if ($this->companies->deleteByCnpj($cnpj)) {
            if (!empty($company['id']) && !empty($user['id'])) {
                AuditLogService::logDelete((int) $user['id'], 'company', (int) $company['id'], $company);
            }
            Session::flash('success', 'Empresa excluida com sucesso.');
        } else {
            Session::flash('error', 'Nao foi possivel excluir a empresa.');
        }

        redirect('/empresas');
    }

    public function rawJson(array $params): void
    {
        if (!(new SetupService())->isDatabaseReady()) {
            redirect('/install');
        }

        if (!Auth::can(['admin', 'editor'])) {
            http_response_code(403);
            echo 'Seu perfil nao possui permissao para acessar o JSON tecnico.';
            return;
        }

        $cnpj = $this->cnpjService->sanitize((string) ($params['cnpj'] ?? ''));
        $company = $this->companies->findByCnpj($cnpj, true);

        if (!$company) {
            Response::json(['error' => 'Empresa nao encontrada.'], 404);
        }

        $rawData = json_decode((string) $company['raw_data'], true) ?: [];
        
        $user = Auth::user();
        $lgpdProfile = $this->lgpdService->resolveProfile($user);
        $masked = $this->lgpdService->maskCompanyPayload($rawData, $lgpdProfile);

        Response::json(['company' => $company, 'masked_data' => $masked]);
    }

    public function history(array $params): void
    {
        $cnpj = $this->cnpjService->sanitize((string) ($params['cnpj'] ?? ''));
        $company = $this->companies->findByCnpj($cnpj);

        if (!$company) {
            Response::json(['error' => 'Empresa nao encontrada.'], 404);
        }

        $snapshots = $this->companies->getSnapshots((int) $company['id']);
        Response::json(['snapshots' => $snapshots]);
    }

    public function map(): void
    {
        View::render('companies/map', [
            'title' => 'Mapa de Empresas',
        ]);
    }

    public function mapApi(): void
    {
        $bounds = $_GET['bounds'] ?? '';
        $state = $_GET['state'] ?? '';
        
        $db = Database::connection();
        
        $stmt = $db->prepare("SHOW COLUMNS FROM companies LIKE 'latitude'");
        $stmt->execute();
        $hasGeo = $stmt->fetch() !== false;
        
        if ($hasGeo) {
            $sql = "SELECT cnpj, legal_name, trade_name, city, state, latitude, longitude 
                     FROM companies WHERE is_hidden = 0 AND latitude IS NOT NULL AND longitude IS NOT NULL";
        } else {
            $sql = "SELECT cnpj, legal_name, trade_name, city, state 
                     FROM companies WHERE is_hidden = 0";
        }
        $params = [];
        
        if ($state) {
            $sql .= " AND state = :state";
            $params['state'] = strtoupper($state);
        }
        
        $sql .= " LIMIT 1000";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $companies = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode(['companies' => $companies]);
    }

    public function subscribeMonitor(array $params): void
    {
        if (!Auth::check()) {
            Response::json(['error' => 'Usuario precisa estar logado.'], 401);
        }

        $cnpj = $this->cnpjService->sanitize((string) ($params['cnpj'] ?? ''));
        $user = Auth::user();
        
        $success = $this->monitorService->subscribe($user['id'], $cnpj);
        
        Response::json(['success' => $success]);
    }

    public function unsubscribeMonitor(array $params): void
    {
        if (!Auth::check()) {
            Response::json(['error' => 'Usuario precisa estar logado.'], 401);
        }

        $cnpj = $this->cnpjService->sanitize((string) ($params['cnpj'] ?? ''));
        $user = Auth::user();
        
        $success = $this->monitorService->unsubscribe($user['id'], $cnpj);
        
        Response::json(['success' => $success]);
    }

    private function firstNonEmptyString(array $values): string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }
        return '';
    }

    private function applyCompanyDefaults(array $company): array
    {
        $defaults = [
            'id' => null,
            'is_hidden' => 0,
            'raw_data' => '{}',
            'trade_name' => '',
            'legal_name' => '',
            'status' => '',
            'city' => '',
            'state' => '',
            'phone' => '',
            'municipal_ibge_code' => 0,
            'cnae_main_code' => null,
            'legal_nature' => null,
            'capital_social' => 0,
            'last_synced_at' => null,
        ];

        return array_replace($defaults, $company);
    }

    private function harmonizeRawData(array $rawData, array $company): array
    {
        if (!isset($rawData['nome_fantasia']) && !empty($rawData['trade_name'])) {
            $rawData['nome_fantasia'] = $rawData['trade_name'];
        }
        if (!isset($rawData['municipio']) && !empty($rawData['city'])) {
            $rawData['municipio'] = $rawData['city'];
        }
        if (!isset($rawData['uf']) && !empty($rawData['state'])) {
            $rawData['uf'] = $rawData['state'];
        }
        if (!isset($rawData['ddd_telefone_1']) && !empty($rawData['phone'])) {
            $rawData['ddd_telefone_1'] = $rawData['phone'];
        }
        if (!isset($rawData['logradouro']) && !empty($rawData['street'])) {
            $rawData['logradouro'] = $rawData['street'];
        }
        if (!isset($rawData['numero']) && !empty($rawData['address_number'])) {
            $rawData['numero'] = $rawData['address_number'];
        }
        if (!isset($rawData['bairro']) && !empty($rawData['district'])) {
            $rawData['bairro'] = $rawData['district'];
        }
        if (!isset($rawData['complemento']) && !empty($rawData['address_complement'])) {
            $rawData['complemento'] = $rawData['address_complement'];
        }

        // Fallback final com dados já persistidos em colunas.
        $rawData['nome_fantasia'] = $rawData['nome_fantasia'] ?? ($company['trade_name'] ?? null);
        $rawData['municipio'] = $rawData['municipio'] ?? ($company['city'] ?? null);
        $rawData['uf'] = $rawData['uf'] ?? ($company['state'] ?? null);
        $rawData['ddd_telefone_1'] = $rawData['ddd_telefone_1'] ?? ($company['phone'] ?? null);
        $rawData['email'] = $rawData['email'] ?? ($company['email'] ?? null);
        $rawData['natureza_juridica'] = $rawData['natureza_juridica'] ?? ($company['legal_nature'] ?? null);
        $rawData['porte'] = $rawData['porte'] ?? ($company['company_size'] ?? null);
        $rawData['data_inicio_atividade'] = $rawData['data_inicio_atividade'] ?? ($company['opened_at'] ?? null);

        return $rawData;
    }

    private function shouldAutoRefreshOnShow(array $company, array $rawData, string $cnpj): bool
    {
        $autoRefreshMinDays = max(1, (int) config('app.cnpj.refresh.auto_refresh_min_days', 7));
        $autoRefreshLockSeconds = max(300, (int) config('app.cnpj.refresh.auto_refresh_lock_seconds', 21600));

        $hasMissingCoreData = (
            $this->firstNonEmptyString([$company['trade_name'] ?? '', $rawData['nome_fantasia'] ?? '', $rawData['trade_name'] ?? '']) === ''
            || $this->firstNonEmptyString([$company['email'] ?? '', $rawData['email'] ?? '']) === ''
            || $this->firstNonEmptyString([$company['phone'] ?? '', $rawData['ddd_telefone_1'] ?? '', $rawData['phone'] ?? '']) === ''
            || $this->firstNonEmptyString([$company['legal_nature'] ?? '', $rawData['natureza_juridica'] ?? '']) === ''
            || $this->firstNonEmptyString([$company['company_size'] ?? '', $rawData['porte'] ?? '']) === ''
            || $this->firstNonEmptyString([(string) ($company['opened_at'] ?? ''), (string) ($rawData['data_inicio_atividade'] ?? ''), (string) ($rawData['opened_at'] ?? '')]) === ''
        );

        if (!$hasMissingCoreData) {
            return false;
        }

        $lastSyncAt = strtotime((string) ($company['last_synced_at'] ?? '2000-01-01'));
        $daysSinceSync = (time() - $lastSyncAt) / 86400;
        if ($daysSinceSync < $autoRefreshMinDays) {
            return false;
        }

        $cacheKey = 'company:auto_refresh:on_show:' . $cnpj;
        if (Cache::has($cacheKey)) {
            return false;
        }

        Cache::set($cacheKey, true, $autoRefreshLockSeconds);
        return true;
    }

    private function normalizeSecondaryCnaes(mixed $secondary): array
    {
        if (!is_array($secondary)) {
            return [];
        }
        
        return array_filter(array_map(function($item) {
            if (is_string($item)) {
                return ['codigo' => $this->formatCnaeCode($item), 'descricao' => ''];
            }
            if (is_array($item)) {
                $rawCode = $item['codigo'] ?? $item['code'] ?? $item['id'] ?? '';
                return [
                    'codigo' => $this->formatCnaeCode((string) $rawCode),
                    'descricao' => $item['descricao'] ?? $item['description'] ?? '',
                ];
            }
            return null;
        }, $secondary));
    }

    private function formatCnaeCode(string $code): string
    {
        $digits = preg_replace('/\D/', '', $code);
        
        if (strlen($digits) < 7) {
            return $code;
        }
        
        $digits = substr($digits, 0, 7);
        
        return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '-' . substr($digits, 5, 1) . '/' . substr($digits, 6, 1);
    }

    private function resolveMainCnae(array $rawData): array
    {
        $code = $rawData['cnae_fiscal']
            ?? $rawData['cnae']
            ?? $rawData['cnae_fiscal_codigo']
            ?? $rawData['cnae_main_code']
            ?? ($rawData['atividade_principal'][0]['codigo'] ?? ($rawData['atividade_principal'][0]['code'] ?? ($rawData['estabelecimento']['atividade_principal']['id'] ?? '')));
        $desc = $rawData['cnae_fiscal_descricao']
            ?? $rawData['cnae_descricao']
            ?? ($rawData['atividade_principal'][0]['descricao'] ?? ($rawData['atividade_principal'][0]['text'] ?? ($rawData['estabelecimento']['atividade_principal']['descricao'] ?? '')));
        
        return [
            'codigo' => $this->formatCnaeCode((string) $code),
            'descricao' => (string) $desc,
        ];
    }

    public function getCnaeDescription(string $code): string
    {
        if ($code === '') {
            return '';
        }

        $digits = preg_replace('/[^0-9]/', '', $code);
        
        if (strlen($digits) > 7) {
            $digits = substr($digits, 0, 7);
        }
        
        if (strlen($digits) < 7) {
            return $code;
        }

        $normalized = $digits;
        $likePattern = $normalized . '%';

        try {
            $stmt = \App\Core\Database::connection()->prepare(
                'SELECT description FROM cnae_activities WHERE code = ? OR code LIKE ? LIMIT 1'
            );
            $stmt->execute([$normalized, $likePattern]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['description'])) {
                return $row['description'];
            }
        } catch (\Throwable $e) {
        }

        $this->fetchAndSaveCnaeFromApi($normalized);

        try {
            $stmt2 = \App\Core\Database::connection()->prepare(
                'SELECT description FROM cnae_activities WHERE code = ? OR code LIKE ? LIMIT 1'
            );
            $stmt2->execute([$normalized, $likePattern]);
            $row2 = $stmt2->fetch(\PDO::FETCH_ASSOC);
            if ($row2 && !empty($row2['description'])) {
                return $row2['description'];
            }
        } catch (\Throwable $e) {
        }

        return '';
    }

    private function fetchAndSaveCnaeFromApi(string $code): void
    {
        try {
            $url = 'https://api.cnpja.com.br/offices/' . $code;
            $response = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 5, 'ignore_errors' => true]
            ]));
            if ($response === false) {
                return;
            }
            $data = json_decode($response, true);
            if (empty($data) || !isset($data['code']) || !isset($data['title'])) {
                return;
            }
            $desc = trim($data['title']);
            if ($desc === '') {
                return;
            }
            $section = !empty($data['section']) ? substr(trim($data['section']), 0, 10) : null;
            $slug = strtolower(preg_replace('/[^a-z0-9]/', '-', $desc));
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');

            \App\Core\Database::connection()->exec(
                "INSERT INTO cnae_activities (code, slug, description, section) 
                 VALUES ('" . addslashes($data['code']) . "', '" . addslashes($slug) . "', '" . addslashes($desc) . "', " . 
                 ($section ? "'" . addslashes($section) . "'" : "NULL") . ")
                 ON DUPLICATE KEY UPDATE description = VALUES(description), slug = VALUES(slug)"
            );
        } catch (\Throwable $e) {
        }
    }

    private function normalizeCnaeCode(string $code): string
    {
        $digits = preg_replace('/[^0-9]/', '', $code);
        
        if (strlen($digits) > 7) {
            $digits = substr($digits, 0, 7);
        }
        
        return $digits;
    }

    private function enrichSecondaryCnaes(array $secondaryCnaes): array
    {
        if (empty($secondaryCnaes)) {
            return [];
        }

        foreach ($secondaryCnaes as &$cnae) {
            $code = trim((string) ($cnae['codigo'] ?? ''));
            if ($code !== '') {
                $cnae['descricao'] = $this->getCnaeDescription($code);
            }
        }

        return $secondaryCnaes;
    }

    private function enforceAuthenticatedSearchRateLimit(): ?string
    {
        $user = Auth::user();
        if (!$user) {
            return 'Voce precisa estar logado para consultar.';
        }

        $scope = 'user_' . $user['id'];
        $result = $this->rateLimiter->hit('company_search', $scope, 30, 60);

        if (!$result['success']) {
            $seconds = (int) ($result['retry_after'] ?? 60);
            $minutes = (int) ceil($seconds / 60);
            return "Limite de consultas atingido. Aguarde {$minutes} minutos.";
        }

        return null;
    }

    private function clientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
