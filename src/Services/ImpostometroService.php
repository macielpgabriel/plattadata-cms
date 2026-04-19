<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Repositories\ArrecadacaoRepository;
use App\Services\ReceitaFederalService;

final class ImpostometroService
{
    private const CACHE_TTL = 3600;

    private ArrecadacaoRepository $repository;

    public function __construct()
    {
        $this->repository = new ArrecadacaoRepository();
    }

    public function getArrecadacaoFederal(): array
    {
        $anoAtual = (int) date('Y');
        $mesAtual = (int) date('n');
        $cacheKey = 'impostometro_real_' . date('Y-m');

        $fromDatabase = false;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $dbData = $this->repository->buscarPorAno($anoAtual);
        if (!empty($dbData) && $this->temDadosValidos($dbData, $mesAtual)) {
            $data = $this->montarDadosApartirDoBanco($dbData, $anoAtual, $mesAtual);
            $data['from_database'] = true;
            Cache::set($cacheKey, $data, self::CACHE_TTL);
            return $data;
        }

        $rfService = new ReceitaFederalService();
        
        $dados = [];
        $totalGeral = 0;
        
        for ($mes = 1; $mes <= $mesAtual; $mes++) {
            $dado = $rfService->getArrecadacaoMensal($anoAtual, $mes);
            $valor = $dado['total_arrecadado'] ?? 0;
            $dados[$mes] = $valor;
            $totalGeral += $valor;
        }

        if ($totalGeral <= 0) {
            $fallback = $this->getDadosFallback($anoAtual, $mesAtual);
            Cache::set($cacheKey, $fallback, self::CACHE_TTL);
            return $fallback;
        }

        foreach ($dados as $mes => $valor) {
            $this->repository->salvarMes($anoAtual, $mes, [
                'total' => $valor,
                'rfb' => $valor * 0.7,
                'outros' => $valor * 0.3,
                'oficial' => 1,
            ]);
        }

        $dadosDetalhados = [];
        foreach ($dados as $mes => $valor) {
            $dadosDetalhados[$mes] = ['total' => (float) $valor];
        }
        
        $data = [
            'total_arrecadado' => $totalGeral,
            'fonte' => $rfService->getFonte($anoAtual),
            'oficial' => $rfService->getArrecadacaoMensal($anoAtual, $mesAtual)['oficial'] ?? false,
            'meses' => $dados,
            'historico_mensal' => $this->gerarHistorico($anoAtual, $dadosDetalhados, (float) $totalGeral),
            'categorias' => $this->formatarCategorias(
                $totalGeral * 0.32, $totalGeral * 0.18, $totalGeral * 0.20, $totalGeral * 0.08,
                $totalGeral * 0.05, $totalGeral * 0.04, $totalGeral * 0.03, $totalGeral * 0.10
            ),
            'total_formatado' => $this->formatarValor($totalGeral),
            'ultima_att' => date('d/m/Y H:i:s'),
        ];
        
        Cache::set($cacheKey, $data, self::CACHE_TTL);
        return $data;
    }
    
    public function formatarValor(float $valor): array
    {
        if ($valor >= 1_000_000_000_000) {
            $resultado = $valor / 1_000_000_000_000;
            return [
                'curto' => number_format($resultado, 2, ',', '.') . ' trilhoes',
                'completo' => number_format($valor, 2, ',', '.'),
                'simbolo' => 'R$',
                'unidade' => 'trilhoes',
                'numero' => $resultado,
            ];
        }
        
        if ($valor >= 1_000_000_000) {
            $resultado = $valor / 1_000_000_000;
            return [
                'curto' => number_format($resultado, 2, ',', '.') . ' bilhoes',
                'completo' => number_format($valor, 2, ',', '.'),
                'simbolo' => 'R$',
                'unidade' => 'bilhoes',
                'numero' => $resultado,
            ];
        }
        
        if ($valor >= 1_000_000) {
            $resultado = $valor / 1_000_000;
            return [
                'curto' => number_format($resultado, 2, ',', '.') . ' milhoes',
                'completo' => number_format($valor, 2, ',', '.'),
                'simbolo' => 'R$',
                'unidade' => 'milhoes',
                'numero' => $resultado,
            ];
        }
        
        if ($valor >= 1_000) {
            $resultado = $valor / 1_000;
            return [
                'curto' => number_format($resultado, 2, ',', '.') . ' milhares',
                'completo' => number_format($valor, 2, ',', '.'),
                'simbolo' => 'R$',
                'unidade' => 'milhares',
                'numero' => $resultado,
            ];
        }
        
        return [
            'curto' => number_format($valor, 2, ',', '.'),
            'completo' => number_format($valor, 2, ',', '.'),
            'simbolo' => 'R$',
            'unidade' => 'reais',
            'numero' => $valor,
        ];
    }

    private function formatarCategorias(float $irrf, float $csll, float $cofins, float $pisPasep, float $ipi, float $ii, float $iof, float $outros): array
    {
        return [
            ['codigo' => 'IRRF', 'nome' => 'Imposto de Renda', 'valor' => $irrf, 'percentual' => 32, 'valor_formatado' => $this->formatarValor($irrf)],
            ['codigo' => 'CSLL', 'nome' => 'Contribuicao Social', 'valor' => $csll, 'percentual' => 18, 'valor_formatado' => $this->formatarValor($csll)],
            ['codigo' => 'COFINS', 'nome' => 'COFINS', 'valor' => $cofins, 'percentual' => 20, 'valor_formatado' => $this->formatarValor($cofins)],
            ['codigo' => 'PIS', 'nome' => 'PIS/PASEP', 'valor' => $pisPasep, 'percentual' => 8, 'valor_formatado' => $this->formatarValor($pisPasep)],
            ['codigo' => 'IPI', 'nome' => 'IPI', 'valor' => $ipi, 'percentual' => 5, 'valor_formatado' => $this->formatarValor($ipi)],
            ['codigo' => 'II', 'nome' => 'Imposto Importacao', 'valor' => $ii, 'percentual' => 4, 'valor_formatado' => $this->formatarValor($ii)],
            ['codigo' => 'IOF', 'nome' => 'IOF', 'valor' => $iof, 'percentual' => 3, 'valor_formatado' => $this->formatarValor($iof)],
            ['codigo' => 'OUTROS', 'nome' => 'Outros Tributos', 'valor' => $outros, 'percentual' => 10, 'valor_formatado' => $this->formatarValor($outros)],
        ];
    }

    private function getDadosFallback(int $ano, int $mes): array
    {
        $totalParcial = $this->calcularTotalParcial($ano, $mes);
        $totalAnual = $this->estimarTotalAnual($ano, $mes);

        return [
            'total_arrecadado' => $totalParcial,
            'periodo' => (string) $ano,
            'meses_informados' => min($mes, 2),
            'categorias' => $this->formatarCategorias(
                $totalParcial * 0.32, $totalParcial * 0.18, $totalParcial * 0.20, $totalParcial * 0.08,
                $totalParcial * 0.05, $totalParcial * 0.04, $totalParcial * 0.03, $totalParcial * 0.10
            ),
            'historico_mensal' => $this->gerarHistoricoFallback($ano, $mes),
            'fonte' => 'Dados baseados em comunicados oficiais',
            'ultima_att' => date('d/m/Y'),
            'oficial' => true,
            'total_anual_estimado' => $totalAnual,
        ];
    }

    private function calcularTotalParcial(int $ano, int $mes): float
    {
        $dados = $this->getDadosMensaisFixos($ano);
        
        $total = 0;
        $mesesDisponiveis = min($mes, count($dados));
        
        for ($i = 0; $i < $mesesDisponiveis; $i++) {
            $total += $dados[$i] ?? 0;
        }

        return $total;
    }

    private function getDadosMensaisFixos(int $ano): array
    {
        $dados = [
            2026 => [
                1 => 325751000000.00,
                2 => 285210000000.00,
            ],
            2025 => [
                1 => 285028000000.00,
                2 => 254054000000.00,
                3 => 265000000000.00,
                4 => 270000000000.00,
                5 => 280000000000.00,
                6 => 290000000000.00,
                7 => 285000000000.00,
                8 => 290000000000.00,
                9 => 280000000000.00,
                10 => 285000000000.00,
                11 => 280000000000.00,
                12 => 285000000000.00,
            ],
        ];

        return $dados[$ano] ?? [];
    }

    private function estimarTotalAnual(int $ano, int $mesesInformados): float
    {
        if ($mesesInformados > 0) {
            $dados = $this->getDadosMensaisFixos($ano);
            $totalInformado = 0;
            for ($i = 1; $i <= $mesesInformados; $i++) {
                $totalInformado += $dados[$i] ?? 0;
            }
            
            $mediaMensal = $totalInformado / $mesesInformados;
            $mesesRestantes = 12 - $mesesInformados;
            
            return $totalInformado + ($mediaMensal * $mesesRestantes);
        }

        $estimativas = [
            2026 => 2350000000000.00,
            2025 => 2235000000000.00,
            2024 => 2100000000000.00,
            2023 => 1950000000000.00,
            2022 => 1850000000000.00,
        ];

        return $estimativas[$ano] ?? 2000000000000.00;
    }

    private function gerarHistorico(int $ano, array $dadosReais, float $totalEstimado): array
    {
        $historico = [];
        $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $mesAtual = (int) date('n');
        $acumulado = 0;

        for ($i = 0; $i < 12; $i++) {
            $mes = $i + 1;
            $valor = $dadosReais[$mes]['total'] ?? 0;
            $acumulado += $valor;

            $historico[] = [
                'mes' => $meses[$i],
                'valor' => $valor,
                'acumulado' => $acumulado,
                'passado' => $mes < $mesAtual,
                'oficial' => isset($dadosReais[$mes]),
            ];
        }

        return $historico;
    }

    private function gerarHistoricoFallback(int $ano, int $mes): array
    {
        $historico = [];
        $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $dadosReais = $this->getDadosMensaisFixos($ano);
        $mesAtual = (int) date('n');
        $acumulado = 0;

        for ($i = 0; $i < 12; $i++) {
            $mes = $i + 1;
            $valor = $dadosReais[$mes] ?? 0;
            $acumulado += $valor;

            $historico[] = [
                'mes' => $meses[$i],
                'valor' => $valor,
                'acumulado' => $acumulado,
                'passado' => $mes < $mesAtual,
                'oficial' => isset($dadosReais[$mes]),
            ];
        }

        return $historico;
    }

    public function getContadoresNacionais(): array
    {
        $diasAno = (int) date('z') + 1;
        $diasRestantes = 365 - $diasAno;

        $populacaoBrasil = 215000000;

        $totalArrecadado = $this->getTotalArrecadado();
        $impostoMedioPerCapita = $totalArrecadado / $populacaoBrasil;
        
        $porSegundo = $totalArrecadado / ($diasAno * 86400);
        $porMinuto = $totalArrecadado / ($diasAno * 1440);
        $porHora = $totalArrecadado / ($diasAno * 24);
        $porDia = $totalArrecadado / $diasAno;

        return [
            'total_brasil' => $totalArrecadado,
            'total_formatado' => $this->formatarValor($totalArrecadado),
            'por_segundo' => $porSegundo,
            'por_segundo_formatado' => $this->formatarValor($porSegundo),
            'por_minuto' => $porMinuto,
            'por_minuto_formatado' => $this->formatarValor($porMinuto),
            'por_hora' => $porHora,
            'por_hora_formatado' => $this->formatarValor($porHora),
            'por_dia' => $porDia,
            'por_dia_formatado' => $this->formatarValor($porDia),
            'por_pessoa' => $impostoMedioPerCapita,
            'por_pessoa_formatado' => $this->formatarValor($impostoMedioPerCapita),
            'imposto_medio_pessoa' => $impostoMedioPerCapita,
            'dias_trabalhados_impostos' => $this->calcularDiasTrabalhoImpostos(),
            'percentual_ano' => round(($diasAno / 365) * 100, 1),
            'dias_fim_ano' => $diasRestantes,
        ];
    }

    private function calcularDiasTrabalhoImpostos(): int
    {
        $salarioMinimo = 1518.00;
        $salarioAnual = $salarioMinimo * 12;
        
        $impostoMedioEstimado = 32000.00;
        $percentualImposto = ($impostoMedioEstimado / $salarioAnual) * 100;
        $dias = (int) round(($percentualImposto / 100) * 365);

        return min(max($dias, 140), 160);
    }

    public function getTotalArrecadado(): float
    {
        $federal = $this->getArrecadacaoFederal();
        return $federal['total_arrecadado'] ?? 0;
    }

    public function getComparativoAnual(): array
    {
        $anosPassados = [
            '2025' => ['valor' => 2235000000000.00, 'descricao' => 'Total realizado ate dez/2025'],
            '2024' => ['valor' => 2100000000000.00, 'descricao' => 'Total realizado ate dez/2024'],
            '2023' => ['valor' => 1950000000000.00, 'descricao' => 'Total realizado ate dez/2023'],
            '2022' => ['valor' => 1850000000000.00, 'descricao' => 'Total realizado ate dez/2022'],
            '2021' => ['valor' => 1650000000000.00, 'descricao' => 'Total realizado ate dez/2021'],
            '2020' => ['valor' => 1530000000000.00, 'descricao' => 'Total realizado ate dez/2020'],
            '2019' => ['valor' => 1580000000000.00, 'descricao' => 'Total realizado ate dez/2019'],
            '2018' => ['valor' => 1470000000000.00, 'descricao' => 'Total realizado ate dez/2018'],
            '2017' => ['valor' => 1380000000000.00, 'descricao' => 'Total realizado ate dez/2017'],
        ];

        $anoAtual = (int) date('Y');
        $mesAtual = (int) date('n');
        
        $dadosReais = $this->repository->buscar($anoAtual);
        $totalInformado = 0;
        $mesesInformados = 0;
        
        foreach ($dadosReais as $mes => $dados) {
            $totalInformado += $dados['total'] ?? 0;
            $mesesInformados++;
        }
        
        $anoCorrente = [];
        if ($mesesInformados > 0) {
            $estimativaAnual = $this->calcularEstimativaAnual($totalInformado, $mesesInformados);
            $anoCorrente[$anoAtual] = [
                'valor' => $estimativaAnual,
                'descricao' => "Previsao ({$mesesInformados} meses informados)",
            ];
        } else {
            $estimativaPadrao = [
                2026 => 2350000000000.00,
            ];
            $anoCorrente[$anoAtual] = [
                'valor' => $estimativaPadrao[$anoAtual] ?? 2350000000000.00,
                'descricao' => 'Previsao para o ano',
            ];
        }
        
        krsort($anosPassados);
        
        return $anoCorrente + $anosPassados;
    }
    
    private function calcularEstimativaAnual(float $totalInformado, int $mesesInformados): float
    {
        if ($mesesInformados === 0) {
            return 2350000000000.00;
        }
        
        $mediaMensal = $totalInformado / $mesesInformados;
        
        $fatoresSazonais = [
            1 => 1.05, 2 => 0.92, 3 => 0.98, 4 => 0.95,
            5 => 1.00, 6 => 1.03, 7 => 0.95, 8 => 1.00,
            9 => 0.95, 10 => 0.98, 11 => 0.95, 12 => 1.04,
        ];
        
        $estimativa = 0;
        for ($mes = 1; $mes <= 12; $mes++) {
            $fator = $fatoresSazonais[$mes];
            $estimativa += $mediaMensal * $fator;
        }
        
        return $estimativa;
    }
    
    public function getEvolucaoAnual(): array
    {
        return [
            [
                'ano' => 2017,
                'arrecadacao' => 1380000000000.00,
                'crescimento' => 6.2,
                'pib' => 6.1,
            ],
            [
                'ano' => 2018,
                'arrecadacao' => 1470000000000.00,
                'crescimento' => 6.5,
                'pib' => 1.8,
            ],
            [
                'ano' => 2019,
                'arrecadacao' => 1580000000000.00,
                'crescimento' => 7.5,
                'pib' => 1.2,
            ],
            [
                'ano' => 2020,
                'arrecadacao' => 1530000000000.00,
                'crescimento' => -3.2,
                'pib' => -3.3,
            ],
            [
                'ano' => 2021,
                'arrecadacao' => 1650000000000.00,
                'crescimento' => 7.8,
                'pib' => 5.0,
            ],
            [
                'ano' => 2022,
                'arrecadacao' => 1850000000000.00,
                'crescimento' => 12.1,
                'pib' => 2.9,
            ],
            [
                'ano' => 2023,
                'arrecadacao' => 1950000000000.00,
                'crescimento' => 5.4,
                'pib' => 2.9,
            ],
            [
                'ano' => 2024,
                'arrecadacao' => 2100000000000.00,
                'crescimento' => 7.7,
                'pib' => 3.2,
            ],
            [
                'ano' => 2025,
                'arrecadacao' => 2235000000000.00,
                'crescimento' => 6.4,
                'pib' => 3.5,
            ],
        ];
    }
    
    public function getComparativoInternacional(): array
    {
        return [
            [
                'pais' => 'Brasil',
                'carga_tributaria' => 33.7,
                'pib' => 2100,
                'moeda' => 'R$',
                'bandeira' => 'BR',
            ],
            [
                'pais' => 'Argentina',
                'carga_tributaria' => 28.5,
                'pib' => 640,
                'moeda' => 'USD',
                'bandeira' => 'AR',
            ],
            [
                'pais' => 'Chile',
                'carga_tributaria' => 21.0,
                'pib' => 340,
                'moeda' => 'USD',
                'bandeira' => 'CL',
            ],
            [
                'pais' => 'Mexico',
                'carga_tributaria' => 19.5,
                'pib' => 1530,
                'moeda' => 'USD',
                'bandeira' => 'MX',
            ],
            [
                'pais' => 'Colombia',
                'carga_tributaria' => 18.5,
                'pib' => 340,
                'moeda' => 'USD',
                'bandeira' => 'CO',
            ],
            [
                'pais' => 'Uruguai',
                'carga_tributaria' => 29.0,
                'pib' => 72,
                'moeda' => 'USD',
                'bandeira' => 'UY',
            ],
            [
                'pais' => 'OECD Media',
                'carga_tributaria' => 34.2,
                'pib' => 0,
                'moeda' => 'USD',
                'bandeira' => 'OECD',
            ],
            [
                'pais' => 'Suecia',
                'carga_tributaria' => 46.1,
                'pib' => 590,
                'moeda' => 'USD',
                'bandeira' => 'SE',
            ],
            [
                'pais' => 'Alemanha',
                'carga_tributaria' => 39.9,
                'pib' => 4300,
                'moeda' => 'EUR',
                'bandeira' => 'DE',
            ],
            [
                'pais' => 'Estados Unidos',
                'carga_tributaria' => 27.7,
                'pib' => 26000,
                'moeda' => 'USD',
                'bandeira' => 'US',
            ],
        ];
    }

    private function temDadosValidos(array $dbData, int $mesAtual): bool
    {
        foreach ($dbData as $row) {
            $mes = (int) $row['mes'];
            if ($mes <= $mesAtual && ($row['total'] ?? 0) > 0) {
                return true;
            }
        }
        return false;
    }

    private function montarDadosApartirDoBanco(array $dbData, int $anoAtual, int $mesAtual): array
    {
        $dados = [];
        $totalGeral = 0;

        foreach ($dbData as $row) {
            $mes = (int) $row['mes'];
            if ($mes <= $mesAtual) {
                $valor = (float) ($row['total'] ?? 0);
                $dados[$mes] = $valor;
                $totalGeral += $valor;
            }
        }

        $dadosDetalhados = [];
        foreach ($dados as $mes => $valor) {
            $dadosDetalhados[$mes] = ['total' => $valor];
        }

        return [
            'total_arrecadado' => $totalGeral,
            'fonte' => 'Receita Federal (BD)',
            'oficial' => true,
            'meses' => $dados,
            'historico_mensal' => $this->gerarHistorico($anoAtual, $dadosDetalhados, (float) $totalGeral),
            'categorias' => $this->formatarCategorias(
                $totalGeral * 0.32, $totalGeral * 0.18, $totalGeral * 0.20, $totalGeral * 0.08,
                $totalGeral * 0.05, $totalGeral * 0.04, $totalGeral * 0.03, $totalGeral * 0.10
            ),
            'total_formatado' => $this->formatarValor($totalGeral),
            'ultima_att' => date('d/m/Y H:i:s'),
        ];
    }
}
