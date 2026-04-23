<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Core\SafeDatabase;
use App\Services\IbgeService;
use App\Services\OpenMeteoService;
use App\Services\DddService;
use App\Repositories\MunicipalityRepository;
use App\Repositories\ExchangeRateRepository;
use App\Repositories\VehicleTypeRepository;
use App\Controllers\Location\LocationStatesService;
use App\Controllers\Location\LocationMunicipalityService;
use App\Controllers\Location\LocationBrasilService;

final class LocationController
{
    private IbgeService $ibgeService;
    private OpenMeteoService $weatherService;
    private DddService $dddService;
    private MunicipalityRepository $municipalityRepository;
    private LocationStatesService $statesService;
    private LocationMunicipalityService $municipalityService;
    private LocationBrasilService $brasilService;
    private $marketIntelligenceService;
    private $bcbService;

    public function __construct()
    {
        $this->ibgeService = new IbgeService();
        $this->weatherService = new OpenMeteoService();
        $this->dddService = new DddService();
        $this->municipalityRepository = new MunicipalityRepository();
        $this->statesService = new LocationStatesService();
        $this->municipalityService = new LocationMunicipalityService();
        $this->brasilService = new LocationBrasilService();
        $this->marketIntelligenceService = class_exists('\App\Services\MarketIntelligenceService') ? new \App\Services\MarketIntelligenceService() : null;
        $this->bcbService = class_exists('\App\Services\BcbService') ? new \App\Services\BcbService() : null;
    }

    public function states(): void
    {
        $states = $this->ibgeService->fetchAndCacheStates();
        
        if (empty($states)) {
            $states = $this->statesService->getStatesFromApi();
        }
        
        $stats = [];
        try {
            $results = SafeDatabase::query("
                SELECT state as uf, COUNT(*) as total_empresas
                FROM companies c
                WHERE state IS NOT NULL AND state != ''
                GROUP BY state
            ", [], []);
            
            $companyStats = [];
            foreach ($results as $row) {
                $companyStats[$row['uf']] = (int) $row['total_empresas'];
            }
            
            $muniResults = SafeDatabase::query("
                SELECT state_uf as uf, COUNT(*) as total_municipios
                FROM municipalities
                WHERE state_uf IS NOT NULL AND state_uf != ''
                GROUP BY state_uf
            ", [], []);
            
            $muniStats = [];
            foreach ($muniResults as $row) {
                $muniStats[$row['uf']] = (int) $row['total_municipios'];
            }
            
            $allUfs = array_unique(array_merge(array_keys($companyStats), array_keys($muniStats)));
            foreach ($allUfs as $uf) {
                $stats[$uf] = [
                    'empresas' => $companyStats[$uf] ?? 0,
                    'municipios' => $muniStats[$uf] ?? 0,
                ];
            }
        } catch (\Exception $e) {
            Logger::error('LocationController stats error: ' . $e->getMessage());
        }

        View::render('public/locations/states', [
            'states' => $states,
            'stats' => $stats,
            'title' => 'Cidades por Estado',
            'metaDescription' => 'Explore cidades e empresas por estado no Brasil.'
        ]);
    }

    public function brasil(): void
    {
        $rawDados = $this->ibgeService->getAllBrasilDados();
        
        $dadosBrasil = [];
        foreach ($rawDados as $key => $data) {
            if ($key === '_meta') {
                continue;
            }
            if ($data['valor'] !== null && $data['valor'] !== 0) {
                $dadosBrasil[$key] = $data['valor'];
            } elseif (!empty($data['texto'])) {
                $dadosBrasil[$key] = $data['texto'];
            } else {
                $dadosBrasil[$key] = null;
            }
        }
        
        $dadosBrasil['capital'] = $dadosBrasil['capital'] ?? 'Brasília';
        $dadosBrasil['area_km2'] = $dadosBrasil['area_km2'] ?? 8515767;
        
        if (isset($rawDados['_meta']['fontes'])) {
            $dadosBrasil['_fontes'] = $rawDados['_meta']['fontes'];
        }
        
        $populacao = $dadosBrasil['populacao'] ?? 203080400;
        $pib = $dadosBrasil['pib'] ?? 9983000000000;
        
        $totalEmpresas = 0;
        $numMunicipios = 0;
        $syncNeeded = false;
        
        try {
            $db = Database::connection();
            
            $stmt = $db->query("SELECT COUNT(*) as total FROM companies");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalEmpresas = (int) ($result['total'] ?? 0);
            
            $stmt = $db->query("SELECT COUNT(*) as total FROM municipalities");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $numMunicipios = (int) ($result['total'] ?? 0);
            
            if ($numMunicipios === 0) {
                $syncNeeded = true;
            }
        } catch (\Exception $e) {
        }
        
        if ($numMunicipios === 0) {
            $numMunicipios = 5570;
        }
        
        View::render('public/locations/brasil', [
            'title' => 'Brasil - Dados Gerais, População, PIB e Economia',
            'metaTitle' => 'Brasil - Dados Gerais | Plattadata',
            'metaDescription' => 'Dados completos do Brasil: população, PIB, indicadores de economia.',
            'populacao' => $populacao,
            'pib' => $pib,
            'totalEmpresas' => $totalEmpresas,
            'numMunicipios' => $numMunicipios,
            'syncNeeded' => $syncNeeded,
            'dadosBrasil' => $dadosBrasil,
        ]);
    }

    public function stateDeprecated(array $params): void
    {
        $uf = strtoupper((string) ($params['uf'] ?? ''));
        redirect('/localidades/' . strtolower($uf), 301);
    }

    public function state(array $params): void
    {
        $uf = strtoupper((string) ($params['uf'] ?? ''));

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/localidades/estado/') !== false) {
            redirect('/localidades/' . strtolower($uf), 301);
            return;
        }

        $state = $this->ibgeService->getStateByUf($uf);

        if (!$state) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Estado não encontrado']);
            return;
        }

        $dbState = null;
        try {
            $stmt = Database::connection()->prepare('SELECT * FROM states WHERE uf = :uf LIMIT 1');
            $stmt->execute(['uf' => $uf]);
            $dbState = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            Logger::warning('State DB fetch failed: ' . $e->getMessage());
        }

        $enrichedState = $this->ibgeService->fetchStateStats($uf);
        
        if ($enrichedState) {
            $state = array_merge($state, $enrichedState);
        }
        
        if ($dbState && !empty($dbState['population'])) {
            if (!empty($dbState['population'])) $state['population'] = $dbState['population'];
            if (!empty($dbState['gdp'])) $state['gdp'] = $dbState['gdp'];
            if (!empty($dbState['gdp_per_capita'])) $state['gdp_per_capita'] = $dbState['gdp_per_capita'];
            if (!empty($dbState['area_km2'])) $state['area_km2'] = $dbState['area_km2'];
        }

        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 100;
        $search = $_GET['search'] ?? null;

        $result = $this->ibgeService->getMunicipalitiesByState($uf, $page, $perPage, $search);
        $municipalities = $result['data'] ?? [];
        $total = $result['total'] ?? 0;

        if (empty($municipalities) && $page === 1) {
            $municipalities = $this->ibgeService->fetchAndCacheMunicipalitiesByState($uf);
            $total = count($municipalities);
        }

        $capitalWeather = null;
        $capitalIbge = null;
        $capitalCity = $state['capital_city'] ?? null;
        if ($capitalCity) {
            try {
                $capitalMuni = $this->ibgeService->getMunicipalityByNameAndState($capitalCity, $uf);
                if ($capitalMuni) {
                    $capitalIbge = (int) ($capitalMuni['ibge_code'] ?? 0);
                    if ($capitalIbge > 0) {
                        $capitalWeather = $this->weatherService->getWeather($capitalIbge);
                    }
                }
            } catch (\Throwable $e) {
                Logger::warning('Failed to get capital weather: ' . $e->getMessage());
            }
        }

        $arrecadacaoData = $this->ibgeService->getDadosEstado($uf);

        View::render('public/locations/state', [
            'title' => "{$state['name']} ({$uf}) - Dados e Empresas",
            'metaTitle' => "{$state['name']} ({$uf}) - Dados e Empresas | Plattadata",
            'metaDescription' => "Dados completos de {$state['name']}: população, PIB, economia e empresas. Consulte informações atualizadas.",
            'state' => $state,
            'municipalities' => $municipalities,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => ceil($total / $perPage),
            'search' => $search,
            'capitalWeather' => $capitalWeather,
            'capitalIbge' => $capitalIbge,
            'arrecadacao' => $arrecadacaoData,
        ]);
    }

    public function municipality(array $params): void
    {
        $ibgeParam = (string) ($params['ibge'] ?? '');
        
        if ($ibgeParam !== '' && is_numeric($ibgeParam)) {
            $ibgeCode = (int) $ibgeParam;
            $muni = $this->municipalityService->getMunicipalityByIbge($ibgeCode);
            
            if (!$muni) {
                http_response_code(404);
                View::render('errors/404', ['title' => 'Município não encontrado']);
                return;
            }
            
            $uf = $muni['state_uf'] ?? '';
            $slug = $muni['slug'] ?? '';
        } else {
            $uf = strtoupper((string) ($params['uf'] ?? ''));
            $slug = (string) ($params['slug'] ?? '');

            $muni = $this->municipalityService->getMunicipalityBySlug($slug, $uf);

            if (!$muni) {
                http_response_code(404);
                View::render('errors/404', ['title' => 'Município não encontrado']);
                return;
            }

            $ibgeCode = (int) ($muni['ibge_code'] ?? 0);
        }
        $muniWithStats = $this->municipalityService->getMunicipalityWithStats($ibgeCode);
        
        $ibgeService = $this->ibgeService;
        $gdpData = $ibgeService->getGdpSmart($ibgeCode);
        
        if ($gdpData && !empty($gdpData['gdp'])) {
            $muniWithStats['gdp'] = $gdpData['gdp'];
            $muniWithStats['gdp_per_capita'] = $gdpData['gdp_per_capita'];
        }

        $weather = null;
        if ($ibgeCode > 0) {
            try {
                $weather = $this->weatherService->getWeather($ibgeCode);
            } catch (\Throwable $e) {
                Logger::warning('Weather fetch failed: ' . $e->getMessage());
            }
        }

        $companies = [];
        $companyCount = 0;
        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM companies 
                WHERE municipal_ibge_code = :ibge AND is_hidden = 0
            ");
            $stmt->execute(['ibge' => $ibgeCode]);
            $companyCount = (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            Logger::warning('Municipality company count failed: ' . $e->getMessage());
        }

        $sectorsData = [];
        $stmt = Database::connection()->prepare("
            SELECT cnae_main_code, COUNT(*) as total
            FROM companies
            WHERE municipal_ibge_code = :ibge AND is_hidden = 0 AND cnae_main_code IS NOT NULL
            GROUP BY cnae_main_code
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute(['ibge' => $ibgeCode]);
        $sectorsData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $companyStats = [];
        try {
            $stmt = Database::connection()->prepare("
                SELECT COUNT(*) as total_companies, 
                       COALESCE(SUM(capital_social), 0) as total_capital,
                       COALESCE(AVG(capital_social), 0) as avg_capital
                FROM companies
                WHERE municipal_ibge_code = :ibge AND is_hidden = 0
            ");
            $stmt->execute(['ibge' => $ibgeCode]);
            $companyStats = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            Logger::warning('Municipality stats failed: ' . $e->getMessage());
        }

        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 20;
        
        $stmt = Database::connection()->prepare("
            SELECT * FROM companies
            WHERE municipal_ibge_code = :ibge AND is_hidden = 0
            ORDER BY legal_name
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('ibge', $ibgeCode, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();
        $companies = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $state = $this->ibgeService->getStateByUf($uf);
        $region = $state['region'] ?? 'N/A';

        View::render('public/locations/municipality', [
            'title' => "{$muni['name']} ({$uf}) - Dados e Empresas",
            'metaTitle' => "{$muni['name']} ({$uf}) - Dados e Empresas | Plattadata",
            'metaDescription' => "Dados completos de {$muni['name']}, {$uf}: população, PIB, economia e empresas.",
            'municipality' => $muniWithStats,
            'region' => $region,
            'companies' => $companies,
            'companyCount' => $companyCount,
            'companyStats' => $companyStats,
            'sectorsData' => $sectorsData,
            'page' => $page,
            'lastPage' => ceil($companyCount / $perPage),
            'weather' => $weather,
            'state' => $state,
        ]);
    }

    public function refresh(array $params): void
    {
        if (!Auth::check()) {
            redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
            return;
        }

        if (!Auth::can(['admin', 'editor'])) {
            http_response_code(403);
            echo 'Permissão negada.';
            return;
        }

        $uf = strtoupper((string) ($params['uf'] ?? ''));
        $slug = (string) ($params['slug'] ?? '');
        $force = isset($_GET['force']);

        try {
            $municipalities = $this->ibgeService->fetchAndCacheMunicipalitiesByState($uf, $force);
            Session::flash('success', count($municipalities) . ' municípios sincronizados.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Erro: ' . $e->getMessage());
        }

        if ($slug) {
            redirect('/localidades/' . strtolower($uf) . '/' . $slug);
        } else {
            redirect('/localidades/' . strtolower($uf));
        }
    }

    public function refreshState(array $params): void
    {
        if (!Auth::check()) {
            redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
            return;
        }

        if (!Auth::can(['admin'])) {
            http_response_code(403);
            echo 'Permissão negada.';
            return;
        }

        $uf = strtoupper((string) ($params['uf'] ?? ''));

        try {
            $repo = new \App\Repositories\StateRepository();
            $updated = $repo->syncStateStats($uf);
            
            if ($updated) {
                Session::flash('success', "Dados do estado {$uf} sincronizados.");
            } else {
                Session::flash('error', "Não foi possível atualizar {$uf}.");
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Erro: ' . $e->getMessage());
        }

        redirect('/localidades/' . strtolower($uf));
    }

    private function formatGdp(float $gdp): string
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

    private function fetchIbgeDirectFallback(array &$municipality, int $ibgeCode): void
    {
        try {
            $stats = $this->ibgeService->fetchMunicipalityStats($ibgeCode, true);
            if ($stats) {
                foreach (['population', 'gdp', 'gdp_per_capita', 'vehicle_fleet', 'business_units'] as $key) {
                    if (isset($stats[$key]) && empty($municipality[$key])) {
                        $municipality[$key] = $stats[$key];
                    }
                }
            }
        } catch (\Throwable $e) {
            Logger::warning("IbgeDirect fallback failed for {$ibgeCode}: " . $e->getMessage());
        }
    }
}