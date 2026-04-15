<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Logger;
use App\Repositories\MunicipalityRepository;
use App\Repositories\StateRepository;
use App\Repositories\VehicleTypeRepository;
use App\Services\Ibge\IbgeApiService;
use App\Services\Ibge\IbgeBusinessUnitsService;
use App\Services\Ibge\IbgeDemographicsService;
use App\Services\Ibge\IbgeGdpService;
use App\Services\Ibge\IbgePopulationService;
use App\Services\Ibge\IbgeSyncService;
use App\Services\Ibge\IbgeVehicleFleetService;
use Throwable;

final class IbgeService
{
    private const CACHE_TTL = 86400;
    private const TIMEOUT = 15;

    private int $cacheTtlDays;
    private MunicipalityRepository $municipalityRepository;
    private StateRepository $stateRepository;
    private VehicleTypeRepository $vehicleTypeRepository;

    private IbgeApiService $apiService;
    private IbgePopulationService $populationService;
    private IbgeGdpService $gdpService;
    private IbgeVehicleFleetService $vehicleFleetService;
    private IbgeBusinessUnitsService $businessUnitsService;
    private IbgeDemographicsService $demographicsService;
    private IbgeSyncService $syncService;

    private const TTL_POPULATION = 180;
    private const TTL_GDP = 365;
    private const TTL_DEMOGRAPHICS = 365;
    private const TTL_VEHICLE_FLEET = 365;
    private const TTL_BUSINESS_UNITS = 180;
    private const TTL_WEATHER = 1;
    private const TTL_AGE_GROUPS = 365;

    public function __construct()
    {
        $this->municipalityRepository = new MunicipalityRepository();
        $this->stateRepository = new StateRepository();
        $this->vehicleTypeRepository = new VehicleTypeRepository();
        $this->cacheTtlDays = (int) config('ibge.cache_ttl_days', 30);

        $this->apiService = new IbgeApiService();
        $this->populationService = new IbgePopulationService();
        $this->gdpService = new IbgeGdpService();
        $this->vehicleFleetService = new IbgeVehicleFleetService();
        $this->businessUnitsService = new IbgeBusinessUnitsService();
        $this->demographicsService = new IbgeDemographicsService();
        $this->syncService = new IbgeSyncService();
    }

    public function fetchJson(string $url): ?array
    {
        return $this->apiService->fetchJson($url);
    }

    private function needsRefresh(?string $updatedAt, int $ttlDays): bool
    {
        return $this->populationService->needsRefresh($updatedAt, $ttlDays);
    }

    public function getPopulationSmart(int $ibgeCode): ?int
    {
        return $this->populationService->getPopulationSmart($ibgeCode);
    }

    public function getGdpSmart(int $ibgeCode): ?array
    {
        return $this->gdpService->getGdpSmart($ibgeCode);
    }

    public function getVehicleFleetSmart(int $ibgeCode): ?int
    {
        return $this->vehicleFleetService->getVehicleFleetSmart($ibgeCode);
    }

    public function getBusinessUnitsSmart(int $ibgeCode): ?int
    {
        return $this->businessUnitsService->getBusinessUnitsSmart($ibgeCode);
    }

    public function getGenderDataSmart(int $ibgeCode): ?array
    {
        return $this->demographicsService->getGenderDataSmart($ibgeCode);
    }

    private function getPopulationByGender(int $ibgeCode): ?array
    {
        return $this->apiService->getPopulationByGender($ibgeCode);
    }

    public function getMunicipalityDataSmart(int $ibgeCode, bool $skipRefresh = false): array
    {
        $muni = $this->municipalityRepository->findByIbgeCode($ibgeCode);
        $data = $muni ?: ['ibge_code' => $ibgeCode];
        
        if (!$muni) {
            if (!$skipRefresh) {
                $data = $this->fetchMunicipalityStats($ibgeCode, true) ?: $data;
            }
            return $data;
        }
        
        if ($skipRefresh) {
            return $data;
        }
        
        $updatedAt = $muni['updated_at'] ?? null;
        $now = new \DateTime();
        
        if ($this->needsRefresh($updatedAt, self::TTL_POPULATION)) {
            $pop = $this->apiService->getMunicipalityPopulationFromApi($ibgeCode);
            if ($pop) {
                $this->municipalityRepository->updateField($ibgeCode, 'population', $pop);
                $data['population'] = $pop;
            }
        }
        
        if ($this->needsRefresh($updatedAt, self::TTL_GDP)) {
            $gdp = $this->apiService->getMunicipalityGdpFromApi($ibgeCode);
            if ($gdp) {
                if (!empty($gdp['gdp'])) {
                    $this->municipalityRepository->updateField($ibgeCode, 'gdp', $gdp['gdp']);
                    $data['gdp'] = $gdp['gdp'];
                }
                if (!empty($gdp['gdp_per_capita'])) {
                    $this->municipalityRepository->updateField($ibgeCode, 'gdp_per_capita', $gdp['gdp_per_capita']);
                    $data['gdp_per_capita'] = $gdp['gdp_per_capita'];
                }
            }
        }
        
        if ($this->needsRefresh($updatedAt, self::TTL_VEHICLE_FLEET)) {
            $fleet = $this->apiService->getMunicipalityVehicleFleetFromApi($ibgeCode);
            if ($fleet) {
                $this->municipalityRepository->updateField($ibgeCode, 'vehicle_fleet', $fleet);
                $data['vehicle_fleet'] = $fleet;
            }
        }
        
        if ($this->needsRefresh($updatedAt, self::TTL_BUSINESS_UNITS)) {
            $units = $this->apiService->getMunicipalityBusinessUnitsFromApi($ibgeCode);
            if ($units) {
                $this->municipalityRepository->updateField($ibgeCode, 'business_units', $units);
                $data['business_units'] = $units;
            }
        }
        
        if ($this->needsRefresh($updatedAt, self::TTL_DEMOGRAPHICS)) {
            $gender = $this->apiService->getPopulationByGender($ibgeCode);
            if ($gender) {
                $this->municipalityRepository->updateField($ibgeCode, 'population_male', $gender['male']);
                $this->municipalityRepository->updateField($ibgeCode, 'population_female', $gender['female']);
                $this->municipalityRepository->updateField($ibgeCode, 'population_male_percent', $gender['male_percent']);
                $this->municipalityRepository->updateField($ibgeCode, 'population_female_percent', $gender['female_percent']);
                $data['population_male'] = $gender['male'];
                $data['population_female'] = $gender['female'];
                $data['population_male_percent'] = $gender['male_percent'];
                $data['population_female_percent'] = $gender['female_percent'];
            }
        }
        
        return $data;
    }

    public function getRegionByUf(string $uf): string
    {
        $regions = [
            'AC' => 'Norte', 'AL' => 'Nordeste', 'AP' => 'Norte', 'AM' => 'Norte',
            'BA' => 'Nordeste', 'CE' => 'Nordeste', 'DF' => 'Centro-Oeste', 'ES' => 'Sudeste',
            'GO' => 'Centro-Oeste', 'MA' => 'Nordeste', 'MT' => 'Centro-Oeste', 'MS' => 'Centro-Oeste',
            'MG' => 'Sudeste', 'PA' => 'Norte', 'PB' => 'Nordeste', 'PR' => 'Sul',
            'PE' => 'Nordeste', 'PI' => 'Nordeste', 'RJ' => 'Sudeste', 'RN' => 'Nordeste',
            'RS' => 'Sul', 'RO' => 'Norte', 'RR' => 'Norte', 'SC' => 'Sul',
            'SP' => 'Sudeste', 'SE' => 'Nordeste', 'TO' => 'Norte',
        ];
        
        return $regions[strtoupper(trim($uf))] ?? 'N/A';
    }

    public function getArrecadacaoPorEstado(int $ano = null): array
    {
        $ano = $ano ?? (int) date('Y');
        $cacheKey = "ibge_arrecadacao_estados_{$ano}";
        
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $dados = $this->buscarDados($ano);
            Cache::set($cacheKey, $dados, self::CACHE_TTL);
            return $dados;
        } catch (Throwable $e) {
            return $this->getDadosFallback($ano);
        }
    }

    private function buscarDados(int $ano): array
    {
        $url = "https://servicos.receita.fazenda.gov.br/SimpleRestfulService/arrecadacao/uf/{$ano}";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
                'header' => "User-Agent: Mozilla/5.0\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return $this->getDadosFallback($ano);
        }

        $data = json_decode($response, true);
        
        if (!is_array($data) || empty($data)) {
            return $this->getDadosFallback($ano);
        }

        return $this->processarDados($data, $ano);
    }

    private function processarDados(array $data, int $ano): array
    {
        $estados = $this->getListaEstados();
        $resultado = [];
        $total = 0;

        foreach ($data as $item) {
            $uf = $item['uf'] ?? null;
            if ($uf === null) continue;

            $valor = (float) ($item['valorArrecadacao'] ?? 0);
            $total += $valor;
            
            $sigla = strtoupper($uf);
            $infoEstado = $estados[$sigla] ?? ['nome' => $sigla, 'regiao' => 'N/A'];
            
            $resultado[$sigla] = [
                'sigla' => $sigla,
                'nome' => $infoEstado['nome'],
                'regiao' => $infoEstado['regiao'],
                'valor' => $valor,
            ];
        }

        foreach ($resultado as $sigla => &$item) {
            $item['participacao'] = $total > 0 ? ($item['valor'] / $total) * 100 : 0;
            $item['valor_formatado'] = $this->formatarValor($item['valor']);
        }

        usort($resultado, fn($a, $b) => $b['participacao'] <=> $a['participacao']);

        return [
            'estados' => $resultado,
            'total' => $total,
            'ano' => $ano,
            'fonte' => 'Receita Federal do Brasil',
            'oficial' => true,
        ];
    }

    private function getDadosFallback(int $ano): array
    {
        $estados = $this->getListaEstados();
        
        $participacoes = [
            'SP' => 32.5, 'RJ' => 14.2, 'MG' => 10.8, 'RS' => 6.5,
            'PR' => 6.2, 'BA' => 4.8, 'SC' => 4.2, 'GO' => 3.5,
            'DF' => 3.2, 'ES' => 2.8, 'PE' => 2.5, 'CE' => 2.3,
            'AM' => 1.8, 'PA' => 1.5, 'MT' => 1.4, 'MS' => 1.2,
            'RN' => 1.1, 'MA' => 1.0, 'PB' => 0.9, 'PI' => 0.8,
            'AL' => 0.7, 'RO' => 0.6, 'SE' => 0.5, 'TO' => 0.4,
            'AC' => 0.2, 'AP' => 0.2, 'RR' => 0.1,
        ];

        $totalEstimado = $this->estimarTotal($ano);
        $resultado = [];

        foreach ($estados as $sigla => $info) {
            $participacao = $participacoes[$sigla] ?? 0.1;
            $valor = $totalEstimado * ($participacao / 100);
            
            $resultado[] = [
                'sigla' => $sigla,
                'nome' => $info['nome'],
                'regiao' => $info['regiao'],
                'participacao' => $participacao,
                'valor' => $valor,
                'valor_formatado' => $this->formatarValor($valor),
            ];
        }

        usort($resultado, fn($a, $b) => $b['participacao'] <=> $a['participacao']);

        return [
            'estados' => $resultado,
            'total' => $totalEstimado,
            'ano' => $ano,
            'fonte' => 'Estimativa baseada em dados oficiais',
            'oficial' => false,
        ];
    }

    private function estimarTotal(int $ano): float
    {
        $estimativas = [
            2026 => 610961000000.00, 2025 => 2235000000000.00, 2024 => 2100000000000.00,
            2023 => 1950000000000.00, 2022 => 1850000000000.00,
        ];
        return $estimativas[$ano] ?? 2000000000000.00;
    }

    private function getListaEstados(): array
    {
        return [
            'AC' => ['nome' => 'Acre', 'regiao' => 'Norte', 'capital_city' => 'Rio Branco', 'area_km2' => 164123.04],
            'AL' => ['nome' => 'Alagoas', 'regiao' => 'Nordeste', 'capital_city' => 'Maceió', 'area_km2' => 27848.14],
            'AP' => ['nome' => 'Amapá', 'regiao' => 'Norte', 'capital_city' => 'Macapá', 'area_km2' => 142828.521],
            'AM' => ['nome' => 'Amazonas', 'regiao' => 'Norte', 'capital_city' => 'Manaus', 'area_km2' => 1559146.876],
            'BA' => ['nome' => 'Bahia', 'regiao' => 'Nordeste', 'capital_city' => 'Salvador', 'area_km2' => 564733.177],
            'CE' => ['nome' => 'Ceará', 'regiao' => 'Nordeste', 'capital_city' => 'Fortaleza', 'area_km2' => 148894.447],
            'DF' => ['nome' => 'Distrito Federal', 'regiao' => 'Centro-Oeste', 'capital_city' => 'Brasília', 'area_km2' => 5760.784],
            'ES' => ['nome' => 'Espírito Santo', 'regiao' => 'Sudeste', 'capital_city' => 'Vitória', 'area_km2' => 46095.56],
            'GO' => ['nome' => 'Goiás', 'regiao' => 'Centro-Oeste', 'capital_city' => 'Goiânia', 'area_km2' => 340111.783],
            'MA' => ['nome' => 'Maranhão', 'regiao' => 'Nordeste', 'capital_city' => 'São Luís', 'area_km2' => 329652.827],
            'MT' => ['nome' => 'Mato Grosso', 'regiao' => 'Centro-Oeste', 'capital_city' => 'Cuiabá', 'area_km2' => 903357.908],
            'MS' => ['nome' => 'Mato Grosso do Sul', 'regiao' => 'Centro-Oeste', 'capital_city' => 'Campo Grande', 'area_km2' => 357145.532],
            'MG' => ['nome' => 'Minas Gerais', 'regiao' => 'Sudeste', 'capital_city' => 'Belo Horizonte', 'area_km2' => 586522.122],
            'PA' => ['nome' => 'Pará', 'regiao' => 'Norte', 'capital_city' => 'Belém', 'area_km2' => 1247954.666],
            'PB' => ['nome' => 'Paraíba', 'regiao' => 'Nordeste', 'capital_city' => 'João Pessoa', 'area_km2' => 56469.778],
            'PR' => ['nome' => 'Paraná', 'regiao' => 'Sul', 'capital_city' => 'Curitiba', 'area_km2' => 199307.922],
            'PE' => ['nome' => 'Pernambuco', 'regiao' => 'Nordeste', 'capital_city' => 'Recife', 'area_km2' => 98311.616],
            'PI' => ['nome' => 'Piauí', 'regiao' => 'Nordeste', 'capital_city' => 'Teresina', 'area_km2' => 251577.738],
            'RJ' => ['nome' => 'Rio de Janeiro', 'regiao' => 'Sudeste', 'capital_city' => 'Rio de Janeiro', 'area_km2' => 43750.425],
            'RN' => ['nome' => 'Rio Grande do Norte', 'regiao' => 'Nordeste', 'capital_city' => 'Natal', 'area_km2' => 52811.107],
            'RS' => ['nome' => 'Rio Grande do Sul', 'regiao' => 'Sul', 'capital_city' => 'Porto Alegre', 'area_km2' => 281730.223],
            'RO' => ['nome' => 'Rondônia', 'regiao' => 'Norte', 'capital_city' => 'Porto Velho', 'area_km2' => 237590.547],
            'RR' => ['nome' => 'Roraima', 'regiao' => 'Norte', 'capital_city' => 'Boa Vista', 'area_km2' => 224300.506],
            'SC' => ['nome' => 'Santa Catarina', 'regiao' => 'Sul', 'capital_city' => 'Florianópolis', 'area_km2' => 95736.165],
            'SP' => ['nome' => 'São Paulo', 'regiao' => 'Sudeste', 'capital_city' => 'São Paulo', 'area_km2' => 248222.362],
            'SE' => ['nome' => 'Sergipe', 'regiao' => 'Nordeste', 'capital_city' => 'Aracaju', 'area_km2' => 21915.116],
            'TO' => ['nome' => 'Tocantins', 'regiao' => 'Norte', 'capital_city' => 'Palmas', 'area_km2' => 277720.52],
        ];
    }

    private function getGeographicData(string $uf): array
    {
        $estados = $this->getListaEstados();
        $uf = strtoupper($uf);
        return $estados[$uf] ?? ['capital_city' => null, 'area_km2' => 0];
    }

    private function formatarValor(float $valor): array
    {
        if ($valor >= 1_000_000_000) {
            $resultado = $valor / 1_000_000_000;
            return [
                'curto' => number_format($resultado, 2, ',', '.') . ' bi',
                'completo' => number_format($valor, 2, ',', '.'),
            ];
        }
        
        if ($valor >= 1_000_000) {
            $resultado = $valor / 1_000_000;
            return [
                'curto' => number_format($resultado, 2, ',', '.') . ' mi',
                'completo' => number_format($valor, 2, ',', '.'),
            ];
        }
        
        return [
            'curto' => number_format($valor, 2, ',', '.'),
            'completo' => number_format($valor, 2, ',', '.'),
        ];
    }
    
    public function getDadosEstado(string $uf): array
    {
        $uf = strtoupper($uf);
        $ano = (int) date('Y');
        $dados = $this->getArrecadacaoPorEstado($ano);
        
        foreach ($dados['estados'] as $estado) {
            if ($estado['sigla'] === $uf) {
                $ranking = 1;
                foreach ($dados['estados'] as $e) {
                    if ($e['participacao'] > $estado['participacao']) {
                        $ranking++;
                    }
                }
                
                return [
                    'sigla' => $uf,
                    'nome' => $estado['nome'],
                    'arrecadacao' => $estado['valor'],
                    'arrecadacao_formatada' => $estado['valor_formatado'],
                    'participacao' => $estado['participacao'],
                    'ranking' => $ranking,
                    'total_estados' => count($dados['estados']),
                    'regiao' => $estado['regiao'],
                ];
            }
        }
        
        return [];
    }
    
    public function getPopulacaoBrasil(): int
    {
        return $this->getBrasilIndicador('populacao') ?? 203080400;
    }
    
    public function getPibBrasil(): float
    {
        return $this->getBrasilIndicador('pib') ?? 9983000000000;
    }
    
    public function getBrasilIndicador(string $indicador): ?float
    {
        $cacheKey = "brasil_{$indicador}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return (float) $cached;
        }
        
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("SELECT valor FROM brasil_info WHERE indicador = :indicador LIMIT 1");
            $stmt->execute(['indicador' => $indicador]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result && $result['valor'] !== null) {
                Cache::set($cacheKey, $result['valor'], self::CACHE_TTL);
                return (float) $result['valor'];
            }
        } catch (\Throwable $e) {
            Logger::warning("Erro ao buscar {$indicador} do Brasil: " . $e->getMessage());
        }
        
        return null;
    }
    
    public function getBrasilTexto(string $indicador): ?string
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("SELECT texto FROM brasil_info WHERE indicador = :indicador LIMIT 1");
            $stmt->execute(['indicador' => $indicador]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result['texto'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    public function getAllBrasilDados(): array
    {
        $cacheKey = "brasil_all_dados";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query("SELECT indicador, valor, texto, fuente, ano FROM brasil_info");
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $dados = [];
            $fontes = [];
            foreach ($results as $row) {
                $dados[$row['indicador']] = [
                    'valor' => (float) $row['valor'],
                    'texto' => $row['texto'],
                ];
                if ($row['fuente'] && $row['ano']) {
                    $fontes[$row['fuente']] = $row['ano'];
                }
            }
            
            $dados['_meta'] = [
                'fontes' => $fontes,
                'updated_at' => date('Y-m-d'),
            ];
            
            Cache::set($cacheKey, $dados, self::CACHE_TTL);
            return $dados;
        } catch (\Throwable $e) {
            Logger::warning("Erro ao buscar dados do Brasil: " . $e->getMessage());
            return [];
        }
    }
    
    public function syncBrasilDados(): bool
    {
        try {
            $pdo = Database::connection();
            
            $dados = [
                ['indicador' => 'populacao', 'valor' => 203080400, 'texto' => '203.080.400', 'fuente' => 'IBGE - Census 2022', 'ano' => 2022],
                ['indicador' => 'pib', 'valor' => 9983000000000, 'texto' => '9,983 trilhões', 'fuente' => 'IBGE - PIB 2020', 'ano' => 2020],
                ['indicador' => 'area_km2', 'valor' => 8515767, 'texto' => '8.515.767', 'fuente' => 'IBGE', 'ano' => 2022],
                ['indicador' => 'municipios', 'valor' => 5570, 'texto' => '5.570', 'fuente' => 'IBGE', 'ano' => 2022],
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO brasil_info (indicador, valor, texto, fuente, ano, updated_at)
                VALUES (:indicador, :valor, :texto, :fuente, :ano, NOW())
                ON DUPLICATE KEY UPDATE valor = :valor, texto = :texto, fuente = :fuente, ano = :ano, updated_at = NOW()
            ");
            
            foreach ($dados as $d) {
                $stmt->execute($d);
            }
            
            Cache::forget('brasil_populacao');
            Cache::forget('brasil_pib');
            
            return true;
        } catch (\Throwable $e) {
            Logger::error("Erro ao sincronizar dados do Brasil: " . $e->getMessage());
            return false;
        }
    }
    
    public function estimateMunicipalityTaxData(int $ibgeCode, string $name, string $uf): array
    {
        $cacheKey = "muni_tax_{$ibgeCode}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $estadoData = $this->getDadosEstado($uf);
        $participacaoEstado = $estadoData['participacao'] ?? 0;
        $totalArrecadacaoEstado = $estadoData['arrecadacao'] ?? 0;

        $popMunicipio = $this->populationService->getMunicipalityPopulation($ibgeCode) ?? 0;
        $popEstado = $this->populationService->getStatePopulation($uf) ?? 1;
        
        $popRatio = $popMunicipio > 0 && $popEstado > 0 ? $popMunicipio / $popEstado : 0.01;
        
        $coef = 0.7 + (random_int(0, 60) / 100);
        
        $arrecadacaoEstimada = $totalArrecadacaoEstado * $popRatio * $coef;

        $result = [
            'ibge_code' => $ibgeCode,
            'nome' => $name,
            'uf' => $uf,
            'arrecadacao_estimada' => $arrecadacaoEstimada,
            'arrecadacao_formatada' => $this->formatarValor($arrecadacaoEstimada),
            'participacao_estado' => $participacaoEstado,
            'populacao' => $popMunicipio,
            'populacao_estado' => $popEstado,
            'proporcao_populacional' => $popRatio * 100,
            'fonte' => 'Estimativa baseada em participação populacional',
            'calculado_em' => date('Y-m-d H:i:s'),
        ];

        Cache::set($cacheKey, $result, 86400);
        return $result;
    }
    
    private function getMunicipalityPopulation(int $ibgeCode): ?int
    {
        return $this->populationService->getMunicipalityPopulation($ibgeCode);
    }
    
    private function getStatePopulation(string $uf): ?int
    {
        return $this->populationService->getStatePopulation($uf);
    }
    
    public function getStateByUf(string $uf): ?array
    {
        $states = $this->fetchAndCacheStates();
        foreach ($states as $state) {
            if (strtoupper($state['uf']) === strtoupper($uf)) {
                $capital = $this->getStateCapital($uf);
                if ($capital) {
                    $state['capital_city'] = $capital;
                }
                return $state;
            }
        }

        $ufUpper = strtoupper($uf);
        $statesFallback = [
            'AC' => ['uf' => 'AC', 'name' => 'Acre', 'region' => 'Norte', 'ibge_code' => 12, 'capital_city' => 'Rio Branco'],
            'AL' => ['uf' => 'AL', 'name' => 'Alagoas', 'region' => 'Nordeste', 'ibge_code' => 27, 'capital_city' => 'Maceió'],
            'AP' => ['uf' => 'AP', 'name' => 'Amapá', 'region' => 'Norte', 'ibge_code' => 16, 'capital_city' => 'Macapá'],
            'AM' => ['uf' => 'AM', 'name' => 'Amazonas', 'region' => 'Norte', 'ibge_code' => 13, 'capital_city' => 'Manaus'],
            'BA' => ['uf' => 'BA', 'name' => 'Bahia', 'region' => 'Nordeste', 'ibge_code' => 29, 'capital_city' => 'Salvador'],
            'CE' => ['uf' => 'CE', 'name' => 'Ceará', 'region' => 'Nordeste', 'ibge_code' => 23, 'capital_city' => 'Fortaleza'],
            'DF' => ['uf' => 'DF', 'name' => 'Distrito Federal', 'region' => 'Centro-Oeste', 'ibge_code' => 53, 'capital_city' => 'Brasília'],
            'ES' => ['uf' => 'ES', 'name' => 'Espírito Santo', 'region' => 'Sudeste', 'ibge_code' => 32, 'capital_city' => 'Vitória'],
            'GO' => ['uf' => 'GO', 'name' => 'Goiás', 'region' => 'Centro-Oeste', 'ibge_code' => 52, 'capital_city' => 'Goiânia'],
            'MA' => ['uf' => 'MA', 'name' => 'Maranhão', 'region' => 'Nordeste', 'ibge_code' => 21, 'capital_city' => 'São Luís'],
            'MT' => ['uf' => 'MT', 'name' => 'Mato Grosso', 'region' => 'Centro-Oeste', 'ibge_code' => 51, 'capital_city' => 'Cuiabá'],
            'MS' => ['uf' => 'MS', 'name' => 'Mato Grosso do Sul', 'region' => 'Centro-Oeste', 'ibge_code' => 50, 'capital_city' => 'Campo Grande'],
            'MG' => ['uf' => 'MG', 'name' => 'Minas Gerais', 'region' => 'Sudeste', 'ibge_code' => 31, 'capital_city' => 'Belo Horizonte'],
            'PA' => ['uf' => 'PA', 'name' => 'Pará', 'region' => 'Norte', 'ibge_code' => 15, 'capital_city' => 'Belém'],
            'PB' => ['uf' => 'PB', 'name' => 'Paraíba', 'region' => 'Nordeste', 'ibge_code' => 25, 'capital_city' => 'João Pessoa'],
            'PR' => ['uf' => 'PR', 'name' => 'Paraná', 'region' => 'Sul', 'ibge_code' => 41, 'capital_city' => 'Curitiba'],
            'PE' => ['uf' => 'PE', 'name' => 'Pernambuco', 'region' => 'Nordeste', 'ibge_code' => 26, 'capital_city' => 'Recife'],
            'PI' => ['uf' => 'PI', 'name' => 'Piauí', 'region' => 'Nordeste', 'ibge_code' => 22, 'capital_city' => 'Teresina'],
            'RJ' => ['uf' => 'RJ', 'name' => 'Rio de Janeiro', 'region' => 'Sudeste', 'ibge_code' => 33, 'capital_city' => 'Rio de Janeiro'],
            'RN' => ['uf' => 'RN', 'name' => 'Rio Grande do Norte', 'region' => 'Nordeste', 'ibge_code' => 24, 'capital_city' => 'Natal'],
            'RO' => ['uf' => 'RO', 'name' => 'Rondônia', 'region' => 'Norte', 'ibge_code' => 11, 'capital_city' => 'Porto Velho'],
            'RR' => ['uf' => 'RR', 'name' => 'Roraima', 'region' => 'Norte', 'ibge_code' => 14, 'capital_city' => 'Boa Vista'],
            'RS' => ['uf' => 'RS', 'name' => 'Rio Grande do Sul', 'region' => 'Sul', 'ibge_code' => 43, 'capital_city' => 'Porto Alegre'],
            'SC' => ['uf' => 'SC', 'name' => 'Santa Catarina', 'region' => 'Sul', 'ibge_code' => 42, 'capital_city' => 'Florianópolis'],
            'SE' => ['uf' => 'SE', 'name' => 'Sergipe', 'region' => 'Nordeste', 'ibge_code' => 28, 'capital_city' => 'Aracaju'],
            'SP' => ['uf' => 'SP', 'name' => 'São Paulo', 'region' => 'Sudeste', 'ibge_code' => 35, 'capital_city' => 'São Paulo'],
            'TO' => ['uf' => 'TO', 'name' => 'Tocantins', 'region' => 'Norte', 'ibge_code' => 17, 'capital_city' => 'Palmas'],
        ];

        return $statesFallback[$ufUpper] ?? null;
    }
    
    private function getStateCapital(string $uf): ?string
    {
        $capitals = [
            'AC' => 'Rio Branco', 'AL' => 'Maceió', 'AP' => 'Macapá', 'AM' => 'Manaus',
            'BA' => 'Salvador', 'CE' => 'Fortaleza', 'DF' => 'Brasília', 'ES' => 'Vitória',
            'GO' => 'Goiânia', 'MA' => 'São Luís', 'MT' => 'Cuiabá', 'MS' => 'Campo Grande',
            'MG' => 'Belo Horizonte', 'PA' => 'Belém', 'PB' => 'João Pessoa', 'PR' => 'Curitiba',
            'PE' => 'Recife', 'PI' => 'Teresina', 'RJ' => 'Rio de Janeiro', 'RN' => 'Natal',
            'RO' => 'Porto Velho', 'RR' => 'Boa Vista', 'RS' => 'Porto Alegre', 'SC' => 'Florianópolis',
            'SE' => 'Aracaju', 'SP' => 'São Paulo', 'TO' => 'Palmas',
        ];
        return $capitals[strtoupper($uf)] ?? null;
    }

    public function getMunicipalityByNameAndState(string $name, string $uf): ?array
    {
        $muni = $this->municipalityRepository->findByNameAndState($name, $uf);
        return $muni ?: null;
    }
    
    public function getMunicipalityByIbge(int $ibgeCode): ?array
    {
        $cached = $this->getMunicipalityFromCache($ibgeCode);
        if ($cached !== null) {
            return $cached;
        }

        $municipality = $this->municipalityRepository->findByIbgeCode($ibgeCode);
        
        if ($municipality !== null) {
            $this->saveMunicipalityToCache($ibgeCode, $municipality);
        }
        
        return $municipality;
    }

    private function getMunicipalityFromCache(int $ibgeCode): ?array
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "SELECT result_json FROM municipality_cache WHERE ibge_code = ? AND expires_at > NOW() LIMIT 1"
            );
            $stmt->execute([$ibgeCode]);
            $row = $stmt->fetch();
            
            if ($row) {
                return json_decode($row['result_json'], true);
            }
        } catch (Throwable $e) {
            Logger::warning('Municipality cache read failed: ' . $e->getMessage());
        }
        
        return null;
    }

    private function saveMunicipalityToCache(int $ibgeCode, array $data): void
    {
        try {
            $db = Database::connection();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->cacheTtlDays} days"));
            
            $stmt = $db->prepare(
                "INSERT INTO municipality_cache (ibge_code, result_json, expires_at) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE result_json = VALUES(result_json), cached_at = NOW(), expires_at = VALUES(expires_at)"
            );
            $stmt->execute([$ibgeCode, json_encode($data), $expiresAt]);
        } catch (Throwable $e) {
            Logger::warning('Municipality cache write failed: ' . $e->getMessage());
        }
    }

    public function invalidateMunicipalityCache(int $ibgeCode): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("DELETE FROM municipality_cache WHERE ibge_code = ?");
            $stmt->execute([$ibgeCode]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function getMunicipalitiesByState(string $uf, int $page = 1, int $perPage = 50, ?string $search = null): array
    {
        return $this->municipalityRepository->findByState($uf, $page, $perPage, $search);
    }

    public function fetchAndCacheMunicipalitiesByState(string $uf, bool $forceRefresh = false): array
    {
        $cacheKey = "ibge_api_municipalities_{$uf}";
        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $urls = [
            "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$uf}/municipios",
            "https://brasilapi.com.br/api/ibge/municipios/v1/{$uf}",
        ];

        $municipalitiesData = [];
        foreach ($urls as $url) {
            try {
                $data = $this->fetchJson($url);
                if ($data && !empty($data)) {
                    foreach ($data as $item) {
                        $parsed = $this->parseMunicipioApi($item, $uf);
                        if ($parsed) {
                            $municipalitiesData[] = $parsed;
                        }
                    }
                    if (!empty($municipalitiesData)) {
                        break;
                    }
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        if (!empty($municipalitiesData)) {
            $chunks = array_chunk($municipalitiesData, 100);
            foreach ($chunks as $chunk) {
                $this->municipalityRepository->upsertMany($chunk);
            }
            Cache::set($cacheKey, $municipalitiesData, self::CACHE_TTL);
        }

        return $municipalitiesData;
    }

    public function syncAllMunicipalities(): int
    {
        return $this->syncService->syncAllMunicipalities();
    }

    public function fetchMunicipalityStats(int $ibgeCode, bool $forceRefresh = false): ?array
    {
        $cacheKey = "municipality_stats_{$ibgeCode}";
        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $existing = $this->municipalityRepository->findByIbgeCode($ibgeCode);
            if ($existing && !empty($existing['population']) && !empty($existing['gdp'])) {
                $hasNewData = !empty($existing['gdp_per_capita']) && !empty($existing['vehicle_fleet']) && !empty($existing['business_units']);
                
                $updatedAt = strtotime($existing['updated_at'] ?? '2000-01-01');
                if ($hasNewData && (time() - $updatedAt < 15552000)) {
                    Cache::set($cacheKey, $existing, self::CACHE_TTL);
                    return $existing;
                }
            }
        }

        $stats = [];
        $existing = $this->municipalityRepository->findByIbgeCode($ibgeCode);
        if ($existing) {
            $stats = $existing;
        }

        $municipalityData = $this->getMunicipalityByIbge($ibgeCode);
        if ($municipalityData) {
            $stats['name'] = $municipalityData['name'];
            $stats['state_uf'] = $municipalityData['state_uf'];
            $stats['slug'] = slugify($municipalityData['name']);
            if (!empty($municipalityData['mesoregion'])) {
                $stats['mesoregion'] = $municipalityData['mesoregion'];
            }
            if (!empty($municipalityData['microregion'])) {
                $stats['microregion'] = $municipalityData['microregion'];
            }
        }

        if ($forceRefresh || empty($stats['population'])) {
            $population = $this->apiService->getMunicipalityPopulationFromApi($ibgeCode);
            if ($population) {
                $stats['population'] = $population;
            }
        }

        if ($forceRefresh || empty($stats['gdp']) || empty($stats['gdp_per_capita'])) {
            $gdpData = $this->apiService->getMunicipalityGdpFromApi($ibgeCode);
            if ($gdpData) {
                $stats['gdp'] = $gdpData['gdp'] ?? null;
                $stats['gdp_per_capita'] = $gdpData['gdp_per_capita'] ?? null;
            }
        }

        if ($forceRefresh || empty($stats['vehicle_fleet'])) {
            $frota = $this->apiService->getMunicipalityVehicleFleetFromApi($ibgeCode);
            if ($frota) {
                $stats['vehicle_fleet'] = $frota;
            }
        }

        if ($forceRefresh || empty($stats['business_units'])) {
            $companies = $this->apiService->getMunicipalityBusinessUnitsFromApi($ibgeCode);
            if ($companies) {
                $stats['business_units'] = $companies;
            }
        }

        if (empty($stats['ddd']) && !empty($stats['state_uf'])) {
            $map = [
                'AC' => '68', 'AL' => '82', 'AM' => '92', 'AP' => '96', 'BA' => '71',
                'CE' => '85', 'DF' => '61', 'ES' => '27', 'GO' => '62', 'MA' => '98',
                'MG' => '31', 'MS' => '67', 'MT' => '65', 'PA' => '91', 'PB' => '83',
                'PE' => '81', 'PI' => '86', 'PR' => '41', 'RJ' => '21', 'RN' => '84',
                'RO' => '69', 'RR' => '95', 'RS' => '51', 'SC' => '48', 'SE' => '79',
                'SP' => '11', 'TO' => '63'
            ];
            $stats['ddd'] = $map[$stats['state_uf']] ?? null;
        }
        
        if (!empty($stats)) {
            $this->municipalityRepository->updateStats($ibgeCode, $stats);
            Cache::set($cacheKey, $stats, self::CACHE_TTL);
            return $stats;
        }

        return null;
    }

    public function syncBulkPopulation(): int
    {
        return $this->syncService->syncBulkPopulation();
    }

    public function syncBulkGdp(): array
    {
        return $this->syncService->syncBulkGdp();
    }

    public function syncBulkFrota(): int
    {
        return $this->syncService->syncBulkFrota();
    }

    public function syncBulkCompanies(): int
    {
        return $this->syncService->syncBulkCompanies();
    }

    public function syncBulkDdd(): int
    {
        return $this->syncService->syncBulkDdd();
    }

    public function fetchAndCacheStates(): array
    {
        $cacheKey = "ibge_api_states_v2";
        $cached = Cache::get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        $states = $this->getStatesFromApi();
        
        if (!empty($states)) {
            $normalized = [];
            foreach ($states as $state) {
                $item = $this->parseStateApi($state);
                if ($item['uf']) {
                    $normalized[] = $item;
                }
            }
            $states = $normalized;
            Cache::set($cacheKey, $states, self::CACHE_TTL);
        }
        return $states;
    }

    private function getStatesFromApi(): array
    {
        return $this->apiService->getStatesFromApi();
    }

    public function getMunicipalityPopulationFromApi(int $ibgeCode): ?int
    {
        return $this->apiService->getMunicipalityPopulationFromApi($ibgeCode);
    }

    public function getMunicipalityGdpFromApi(int $ibgeCode): ?array
    {
        return $this->apiService->getMunicipalityGdpFromApi($ibgeCode);
    }

    public function getMunicipalityVehicleFleetFromApi(int $ibgeCode): ?int
    {
        return $this->apiService->getMunicipalityVehicleFleetFromApi($ibgeCode);
    }

    public function getMunicipalityBusinessUnitsFromApi(int $ibgeCode): ?int
    {
        return $this->apiService->getMunicipalityBusinessUnitsFromApi($ibgeCode);
    }

    public function fetchStateStats(string $uf): ?array
    {
        return null;
    }

    private function getStateArea(string $uf): ?float
    {
        $areas = [
            'AC' => 164123.04, 'AL' => 27848.14, 'AP' => 142828.521, 'AM' => 1559146.876,
            'BA' => 564733.177, 'CE' => 148894.447, 'DF' => 5760.784, 'ES' => 46095.56,
            'GO' => 340111.783, 'MA' => 329652.827, 'MT' => 903357.908, 'MS' => 357145.532,
            'MG' => 586522.122, 'PA' => 1247954.666, 'PB' => 56469.778, 'PR' => 199307.922,
            'PE' => 98311.616, 'PI' => 251577.738, 'RJ' => 43750.425, 'RN' => 52811.107,
            'RS' => 281730.223, 'RO' => 237590.547, 'RR' => 224300.506, 'SC' => 95736.165,
            'SP' => 248222.362, 'SE' => 21915.116, 'TO' => 277720.52,
        ];
        return $areas[strtoupper($uf)] ?? null;
    }

    public function getStateGdpFromApi(string $uf): ?array
    {
        return null;
    }

    public function getStateAgeGroups(string $uf): ?array
    {
        return null;
    }

    public function syncBulkPopulationByGender(): array
    {
        return [];
    }

    private function parseMunicipioApi(array $item, string $uf): array
    {
        return [
            'ibge_code' => (int) ($item['id'] ?? 0),
            'name' => (string) ($item['nome'] ?? ''),
            'state_uf' => $uf,
            'mesoregion' => !empty($item['microrregiao']['mesorregiao']['nome']) 
                ? (string) $item['microrregiao']['mesorregiao']['nome'] : null,
            'microregion' => !empty($item['microrregiao']['nome']) 
                ? (string) $item['microrregiao']['nome'] : null,
            'slug' => slugify((string) ($item['nome'] ?? '')),
        ];
    }

    private function parseStateApi(array $state): array
    {
        return [
            'uf' => $state['sigla'] ?? $state['uf'] ?? null,
            'name' => $state['nome'] ?? $state['name'] ?? null,
            'region' => $state['regiao'] ?? $state['region'] ?? null,
            'ibge_code' => $state['id'] ?? null,
        ];
    }
}