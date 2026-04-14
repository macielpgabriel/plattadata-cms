<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;
use App\Repositories\MarketAnalyticsRepository;
use App\Repositories\CompanyRepository;
use App\Controllers\Comparison\ComparisonSearchService;
use App\Controllers\Comparison\ComparisonRankingsService;
use App\Controllers\Comparison\ComparisonToolsService;

final class ComparisonController
{
    private MarketAnalyticsRepository $analytics;
    private CompanyRepository $companies;
    private ComparisonSearchService $searchService;
    private ComparisonRankingsService $rankingsService;
    private ComparisonToolsService $toolsService;

    public function __construct()
    {
        $this->analytics = new MarketAnalyticsRepository();
        $this->companies = new CompanyRepository();
        $this->searchService = new ComparisonSearchService();
        $this->rankingsService = new ComparisonRankingsService();
        $this->toolsService = new ComparisonToolsService();
    }

    public function apiSearch(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $term = trim((string) ($_GET['q'] ?? ''));
        
        if (strlen($term) < 2) {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $results = $this->searchService->search($term);
            echo json_encode($results, JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro na busca'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiCompareDetailed(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $cnpj1 = preg_replace('/\D/', '', (string) ($_GET['cnpj1'] ?? ''));
        $cnpj2 = preg_replace('/\D/', '', (string) ($_GET['cnpj2'] ?? ''));
        
        if (strlen($cnpj1) !== 14 || strlen($cnpj2) !== 14) {
            http_response_code(400);
            echo json_encode(['error' => 'CNPJs inválidos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $data = $this->searchService->getCompaniesForComparison($cnpj1, $cnpj2);
            
            if ($data === null) {
                http_response_code(404);
                echo json_encode(['error' => 'Uma ou ambas as empresas não foram encontradas'], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            $comp1 = $data['company1'];
            $comp2 = $data['company2'];
            
            $comparison = [
                'empresa1' => [
                    'cnpj' => $comp1['cnpj'],
                    'nome' => $comp1['trade_name'] ?: $comp1['legal_name'],
                    'cidade' => $comp1['city'],
                    'estado' => $comp1['state'],
                    'status' => $comp1['status'],
                ],
                'empresa2' => [
                    'cnpj' => $comp2['cnpj'],
                    'nome' => $comp2['trade_name'] ?: $comp2['legal_name'],
                    'cidade' => $comp2['city'],
                    'estado' => $comp2['state'],
                    'status' => $comp2['status'],
                ],
                'metricas' => [
                    'total_consults' => $this->searchService->compareValues($comp1, $comp2, 'total_consults'),
                    'total_views' => $this->searchService->compareValues($comp1, $comp2, 'total_views'),
                    'unique_users' => $this->searchService->compareValues($comp1, $comp2, 'unique_users'),
                    'days_consulted' => $this->searchService->compareValues($comp1, $comp2, 'days_consulted'),
                    'capital_social' => $this->searchService->compareValues($comp1, $comp2, 'capital_social', true),
                    'credit_score' => $this->searchService->compareValues($comp1, $comp2, 'credit_score'),
                ],
            ];
            
            echo json_encode($comparison, JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro na comparação'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function index(): void
    {
        $pages = $this->loadPages();
        
        View::render('public/comparisons', [
            'title' => 'Comparativo de Empresas',
            'metaTitle' => 'Comparativo de Empresas | Plattadata',
            'metaDescription' => 'Compare empresas brasileiras: rankings, análises e estatísticas do cadastro nacional.',
            'pages' => $pages,
        ]);
    }

    public function show(array $params = []): void
    {
        $slug = $params['slug'] ?? '';
        
        if ($slug === 'ranking' || $slug === 'rankings') {
            redirect('/ranking');
        }
        
        $pages = $this->loadPages();
        if (isset($pages[$slug])) {
            redirect('/ferramentas/' . $slug);
        }
        
        $content = [
            'title' => 'Análise Comparativa',
            'subtitle' => 'Dados detalhados do cenário empresarial brasileiro',
        ];
        
        View::render('public/comparison', [
            'title' => 'Análise Comparativa',
            'metaTitle' => 'Análise Comparativa | Plattadata',
            'metaDescription' => 'Análise comparativa detalhada do cenário empresarial brasileiro.',
            'content' => $content,
        ]);
    }

    public function rankings(): void
    {
        try {
            $rankings = [
                'empresas_estado' => $this->rankingsService->getRankEmpresasEstado(),
                'cidades' => $this->rankingsService->getRankCidades(),
                'cnae' => $this->rankingsService->getRankCnae(),
                'porte' => $this->rankingsService->getRankPorte(),
                'status' => $this->rankingsService->getRankStatus(),
                'arrecadacao' => $this->rankingsService->getRankArrecadacao(),
            ];

            View::render('public/rankings', [
                'title' => 'Rankings',
                'rankings' => $rankings,
                'estadoEmpRank' => $rankings['empresas_estado'] ?? null,
                'cidadeRank' => $rankings['cidades'] ?? null,
                'cnaeRank' => $rankings['cnae'] ?? null,
                'porteRank' => $rankings['porte'] ?? null,
                'statusRank' => $rankings['status'] ?? null,
                'regiaoRank' => $rankings['arrecadacao'] ?? null,
            ]);
        } catch (\Throwable $e) {
            View::render('public/rankings', [
                'title' => 'Rankings',
                'rankings' => [],
                'estadoEmpRank' => null,
                'cidadeRank' => null,
                'cnaeRank' => null,
                'porteRank' => null,
                'statusRank' => null,
                'regiaoRank' => null,
                'error' => 'Erro ao carregar rankings',
            ]);
        }
    }

    public function tool(array $params = []): void
    {
        $section = $_GET['section'] ?? null;

        try {
            $pages = $this->loadPages();
            $tools = $this->loadTools();

            $currentTool = null;
            $toolData = [];

            if ($section) {
                $currentTool = $tools[$section] ?? null;
                if ($currentTool) {
                    $methodName = $currentTool['method'];
                    if (method_exists($this, $methodName)) {
                        $toolData = $this->$methodName();
                    }
                }
            }

            View::render('public/comparison', [
                'title' => 'Ferramentas de Análise',
                'pages' => $pages,
                'tools' => $tools,
                'currentTool' => $currentTool,
                'toolData' => $toolData,
                'activeSection' => $section,
            ]);
        } catch (\Throwable $e) {
            View::render('public/comparison', [
                'title' => 'Ferramentas de Análise',
                'pages' => $this->loadPages(),
                'tools' => $this->loadTools(),
                'currentTool' => null,
                'toolData' => [],
                'activeSection' => $section,
                'error' => 'Erro ao carregar ferramenta',
            ]);
        }
    }

    private function loadPages(): array
    {
        return [
            'ranking' => [
                'title' => 'Rankings',
                'icon' => 'bi-trophy',
                'description' => 'Visualize rankings de empresas, cidades e muito mais',
            ],
            'economia' => [
                'title' => 'Economia',
                'icon' => 'bi-graph-up',
                'description' => 'Análise econômica e tendências do mercado',
            ],
            'tributacao' => [
                'title' => 'Tributação',
                'icon' => 'bi-percent',
                'description' => 'Comparativos tributários e carga fiscal',
            ],
        ];
    }

    private function loadTools(): array
    {
        return [
            'cnae-lucrativos' => [
                'title' => 'CNAEs Mais Lucrativos',
                'method' => 'getCnaeLucrativos',
                'icon' => 'bi-currency-dollar',
            ],
            'estados' => [
                'title' => 'Estados',
                'method' => 'getEstadosEmpresas',
                'icon' => 'bi-map',
            ],
            'regioes' => [
                'title' => 'Regiões do Brasil',
                'method' => 'getRegioesBrasil',
                'icon' => 'bi-geo-alt',
            ],
            'porte' => [
                'title' => 'Porte Empresarial',
                'method' => 'getDistribuicaoPorte',
                'icon' => 'bi-building',
            ],
            'status' => [
                'title' => 'Ativas vs Inativas',
                'method' => 'getAtivasVsInativas',
                'icon' => 'bi-check-circle',
            ],
            'cidades' => [
                'title' => 'Melhores Cidades',
                'method' => 'getMelhoresCidades',
                'icon' => 'bi-pin-map',
            ],
            'tributacao-mundo' => [
                'title' => 'Carga Tributária Global',
                'method' => 'getTributacaoMundo',
                'icon' => 'bi-globe',
            ],
            'comparativo-tributario' => [
                'title' => 'Simples vs Lucro Presumido',
                'method' => 'getComparativoTributario',
                'icon' => 'bi-calculator',
            ],
            'custos-estados' => [
                'title' => 'Custos por Estado',
                'method' => 'getCustosEstados',
                'icon' => 'bi-cash-stack',
            ],
        ];
    }

    public function getCnaeLucrativos(): array
    {
        return $this->toolsService->getCnaeLucrativos();
    }

    public function getCnaeFallback(): array
    {
        return [
            'title' => 'CNAEs Mais Lucrativos',
            'subtitle' => 'Dados estimados.',
            'results' => [],
            'fallback' => true,
        ];
    }

    public function getEstadosEmpresas(): array
    {
        return $this->toolsService->getEstadosEmpresas();
    }

    public function getEstadosFallback(): array
    {
        return [
            'title' => 'Estados',
            'subtitle' => 'Dados estimados.',
            'results' => [],
            'fallback' => true,
        ];
    }

    public function getCnaePorQuantidade(): array
    {
        return $this->toolsService->getCnaeLucrativos();
    }

    public function getCnaeQuantidadeFallback(): array
    {
        return $this->getCnaeFallback();
    }

    public function getRegioesBrasil(): array
    {
        return $this->toolsService->getRegioesBrasil();
    }

    public function getRegioesFallback(): array
    {
        return [
            'title' => 'Regiões do Brasil',
            'subtitle' => 'Dados estimados.',
            'results' => [],
            'fallback' => true,
        ];
    }

    public function getDistribuicaoPorte(): array
    {
        return $this->toolsService->getDistribuicaoPorte();
    }

    public function getPorteFallback(): array
    {
        return [
            'title' => 'Porte Empresarial',
            'subtitle' => 'Dados estimados.',
            'results' => [],
            'fallback' => true,
        ];
    }

    public function getAtivasVsInativas(): array
    {
        return $this->toolsService->getAtivasVsInativas();
    }

    public function getAtivasFallback(): array
    {
        return [
            'title' => 'Empresas Ativas vs Inativas',
            'subtitle' => 'Dados estimados.',
            'results' => [],
            'fallback' => true,
        ];
    }

    public function getMelhoresCidades(): array
    {
        return $this->toolsService->getMelhoresCidades();
    }

    public function getCidadesFallback(): array
    {
        return [
            'title' => 'Melhores Cidades para Abrir Empresa',
            'subtitle' => 'Dados estimados.',
            'results' => [],
            'fallback' => true,
        ];
    }

    public function getTributacaoMundo(): array
    {
        return [
            'type' => 'comparison_international',
            'title' => 'Carga Tributária Global - Comparativo por País',
            'subtitle' => 'Percentual do PIB. Fonte: OCDE, Banco Mundial.',
            'data' => [
                ['pais' => 'Brasil', 'carga_tributaria' => 33.5, 'ranking' => 1],
                ['pais' => 'França', 'carga_tributaria' => 30.8, 'ranking' => 2],
                ['pais' => 'Alemanha', 'carga_tributaria' => 29.7, 'ranking' => 3],
                ['pais' => 'Itália', 'carga_tributaria' => 28.5, 'ranking' => 4],
                ['pais' => 'Suécia', 'carga_tributaria' => 28.2, 'ranking' => 5],
                ['pais' => 'Estados Unidos', 'carga_tributaria' => 18.5, 'ranking' => 25],
                ['pais' => 'Chile', 'carga_tributaria' => 14.2, 'ranking' => 45],
                ['pais' => 'Emirados Árabes', 'carga_tributaria' => 1.5, 'ranking' => 180],
            ],
        ];
    }

    public function getComparativoTributario(): array
    {
        return [
            'type' => 'comparison_table',
            'title' => 'Comparativo: Simples Nacional vs Lucro Presumido',
            'subtitle' => 'Alíquotas efetivas médias.',
            'data' => [
                ['faturamento' => 'até 180 mil', 'simples' => '4% a 7,5%', 'presumido' => 'N/A', 'recomendado' => 'Simples'],
                ['faturamento' => '180mil - 360mil', 'simples' => '7,5% a 11,2%', 'presumido' => '13,5% a 16,9%', 'recomendado' => 'Depende'],
                ['faturamento' => '360mil - 720mil', 'simples' => '11,2% a 14,7%', 'presumido' => '13,5% a 16,9%', 'recomendado' => 'Simples'],
                ['faturamento' => '720mil - 3,6 milhões', 'simples' => '14,7% a 19%', 'presumido' => '13,5% a 16,9%', 'recomendado' => 'Lucro Presumido'],
                ['faturamento' => '3,6 - 4,8 milhões', 'simples' => '19% a 22,5%', 'presumido' => '13,5% a 16,9%', 'recomendado' => 'Lucro Presumido'],
                ['faturamento' => 'acima de 4,8 milhões', 'simples' => 'Não optante', 'presumido' => '16,9% a 32%', 'recomendado' => 'Lucro Real'],
            ],
        ];
    }

    public function getCustosEstados(): array
    {
        return [
            'type' => 'cost_comparison',
            'title' => 'Custos e Prazos para Abrir Empresa por Estado',
            'subtitle' => 'Valores médios em reais e tempo em dias úteis.',
            'data' => [
                ['estado' => 'Santa Catarina', 'uf' => 'SC', 'tempo' => 2, 'custo' => 500],
                ['estado' => 'Paraná', 'uf' => 'PR', 'tempo' => 3, 'custo' => 600],
                ['estado' => 'Goiás', 'uf' => 'GO', 'tempo' => 4, 'custo' => 750],
                ['estado' => 'Minas Gerais', 'uf' => 'MG', 'tempo' => 4, 'custo' => 800],
                ['estado' => 'São Paulo', 'uf' => 'SP', 'tempo' => 5, 'custo' => 1500],
                ['estado' => 'Rio de Janeiro', 'uf' => 'RJ', 'tempo' => 8, 'custo' => 1800],
            ],
            'average' => ['tempo_medio' => 5, 'custo_medio' => 967],
        ];
    }

    private function getSectionName(?string $section): string
    {
        $sections = [
            'A' => 'Agropecuária', 'B' => 'Extrativa Mineral', 'C' => 'Indústria',
            'D' => 'Eletricidade e Gás', 'E' => 'Água e Esgoto', 'F' => 'Construção',
            'G' => 'Comércio', 'H' => 'Transporte', 'I' => 'Alojamento e Alimentação',
            'J' => 'Informação e Comunicação', 'K' => 'Financeiras e Seguros',
            'L' => 'Imobiliárias', 'M' => 'Serviços Profissionais', 'N' => 'Administrativas',
            'O' => 'Administração Pública', 'P' => 'Educação', 'Q' => 'Saúde',
            'R' => 'Artes e Cultura', 'S' => 'Outras Atividades', 'T' => 'Serviços Domésticos',
            'U' => 'Organismos Internacionais',
        ];
        return $sections[$section] ?? 'Outros';
    }

    private function generateStateInsights(array $data): array
    {
        $insights = [];

        if (!empty($data)) {
            $top = $data[0] ?? null;
            if ($top) {
                $insights[] = ucfirst(strtolower($top['estado'] ?? '')) . ' lidera com ' . number_format($top['empresas'] ?? 0, 0, ',', '.') . ' empresas';
            }
        }

        return $insights ?: ['Dados atualizados em tempo real.'];
    }

    private function getPorteName(string $sigla): string
    {
        $portes = [
            'MEI' => 'Microempreendedor Individual', 'ME' => 'Microempresa',
            'EPP' => 'Empresa Pequeno Porte', 'GP' => 'Grande Empresa',
            'LTDA' => 'Sociedade Limitada', 'SA' => 'Sociedade Anônima',
        ];
        return $portes[$sigla] ?? $sigla;
    }

    private function getStatusName(string $status): string
    {
        $statuses = [
            'ATIVA' => 'Ativa', 'ATIV' => 'Ativa', 'INAPTA' => 'Inapta',
            'BAIXADA' => 'Baixada', 'SUSPENSA' => 'Suspensa',
            'CANCELADA' => 'Cancelada', 'NULA' => 'Nula', 'REGULAR' => 'Regular',
        ];
        return $statuses[strtoupper($status)] ?? $status;
    }

    public function compare(array $params = []): void
    {
        View::render('companies/compare', [
            'title' => 'Comparar Empresas',
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function apiAddToComparison(array $params): void
    {
        header('Content-Type: application/json');
        
        $cnpj = preg_replace('/\D/', '', (string) ($params['cnpj'] ?? ''));
        
        if (strlen($cnpj) !== 14) {
            http_response_code(400);
            echo json_encode(['error' => 'CNPJ inválido']);
            return;
        }

        $company = $this->companies->findByCnpj($cnpj);
        if (!$company) {
            http_response_code(404);
            echo json_encode(['error' => 'Empresa não encontrada']);
            return;
        }

        echo json_encode([
            'success' => true,
            'cnpj' => $cnpj,
            'name' => $company['legal_name'],
            'status' => $company['status'] ?? '',
            'city' => $company['city'] ?? '',
            'state' => $company['state'] ?? '',
        ]);
    }
}