<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Repositories\ArrecadacaoRepository;
use DOMDocument;
use DOMXPath;
use Throwable;

final class ArrecadacaoService
{
    private const TIMEOUT = 30;
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private ArrecadacaoRepository $repository;

    public function __construct()
    {
        $this->repository = new ArrecadacaoRepository();
    }

    public function atualizarDados(int $ano = null): array
    {
        $ano = $ano ?? (int) date('Y');
        
        echo "=== Atualizando dados de arrecadação {$ano} ===\n";
        
        $resultado = [
            'sucesso' => true,
            'meses_atualizados' => 0,
            'meses_falharam' => [],
            'dados' => [],
        ];

        $url = "https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/relatorios/arrecadacao-federal/{$ano}";
        
        echo "Buscando lista de relatórios em: {$url}\n";
        
        $html = $this->fetchUrl($url);
        
        if ($html === false) {
            $resultado['sucesso'] = false;
            $resultado['erro'] = 'Falha ao acessar a página da Receita Federal';
            return $resultado;
        }

        $links = $this->extrairLinksRelatorios($html, $ano);
        
        if (empty($links)) {
            echo "Nenhum link de relatório encontrado. Tentando buscar dados diretamente...\n";
            $dados = $this->buscarDadosAlternativos($ano);
            if ($dados !== null) {
                $this->repository->salvar($ano, $dados);
                $resultado['meses_atualizados'] = count($dados);
                $resultado['dados'] = $dados;
            }
            return $resultado;
        }

        echo "Encontrados " . count($links) . " relatórios\n";
        
        foreach ($links as $mes => $link) {
            echo "\nProcessando {$mes}... ";
            
            try {
                $dadosMes = $this->buscarDadosMensal($link, $ano, $mes);
                
                if ($dadosMes !== null) {
                    $this->repository->salvarMes($ano, $mes, $dadosMes);
                    $resultado['meses_atualizados']++;
                    $resultado['dados'][$mes] = $dadosMes;
                    echo "OK - R$ " . number_format($dadosMes['total'], 0, ',', '.') . "\n";
                } else {
                    $resultado['meses_falharam'][] = $mes;
                    echo "SKIP (sem dados)\n";
                }
            } catch (Throwable $e) {
                $resultado['meses_falharam'][] = $mes;
                echo "ERRO: " . $e->getMessage() . "\n";
            }
        }

        $this->repository->salvarCache($ano, $resultado['dados']);
        Cache::delete('impostometro_real_' . date('Y-m'));
        
        echo "\n=== Atualização concluída ===\n";
        echo "Meses atualizados: {$resultado['meses_atualizados']}\n";
        echo "Meses falharam: " . count($resultado['meses_falharam']) . "\n";
        
        return $resultado;
    }

    private function fetchUrl(string $url): string|false
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
                'header' => "User-Agent: " . self::USER_AGENT . "\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        return @file_get_contents($url, false, $context);
    }

    private function extrairLinksRelatorios(string $html, int $ano): array
    {
        $links = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($doc);
        
        $mesesPortugues = [
            'jan' => 1, 'fev' => 2, 'mar' => 3, 'abr' => 4,
            'mai' => 5, 'jun' => 6, 'jul' => 7, 'ago' => 8,
            'set' => 9, 'out' => 10, 'nov' => 11, 'dez' => 12,
        ];
        
        $nodes = $xpath->query('//a[contains(@href, "/publicacoes/relatorios/arrecadacao-federal/' . $ano . '")]');
        
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $href = $node->getAttribute('href');
            $text = trim($node->textContent);
            
            foreach ($mesesPortugues as $mesNome => $mesNum) {
                if (stripos($text, $mesNome) !== false || stripos($href, $mesNome) !== false) {
                    if (!isset($links[$mesNum])) {
                        $links[$mesNum] = $href;
                    }
                    break;
                }
            }
        }
        
        return $links;
    }

    private function buscarDadosMensal(string $url, int $ano, int $mes): ?array
    {
        $html = $this->fetchUrl($url);
        
        if ($html === false) {
            return null;
        }

        $dados = $this->extrairDadosDoHtml($html, $ano, $mes);
        
        return $dados;
    }

    private function extrairDadosDoHtml(string $html, int $ano, int $mes): ?array
    {
        $valorTotal = $this->extrairValorTotal($html);
        
        if ($valorTotal === null) {
            return null;
        }

        return [
            'ano' => $ano,
            'mes' => $mes,
            'total' => $valorTotal,
            'rfb' => $this->extrairValorRfb($html, $valorTotal),
            'outros' => $this->extrairValorOutros($html, $valorTotal),
            'fonte' => 'Receita Federal do Brasil',
            'data_publicacao' => date('Y-m-d'),
            'oficial' => true,
        ];
    }

    private function extrairValorTotal(string $html): ?float
    {
        $patterns = [
            '/TOTAL\s*[:.]*\s*R\$\s*([\d\.,]+)/i',
            '/ARRECADAÇÃO\s*D[AO]\S*\s*(?:RECEITAS?\s*)?FEDERAIS?\s*[:.]*\s*([\d\.,]+)/i',
            '/([\d\.]+)\s*(?:bi|bilhões)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $valor = $this->limparNumero($matches[1]);
                if ($valor > 100_000_000_000) {
                    return $valor;
                }
            }
        }

        return null;
    }

    private function extrairValorRfb(string $html, float $total): float
    {
        $percentualRfb = 0.95;
        
        if (preg_match('/ADMINISTRADAS\s*(?:PELA)?\s*RFB.*?([\d\.,]+)/i', $html, $matches)) {
            $valor = $this->limparNumero($matches[1]);
            if ($valor > 1_000_000_000) {
                return $valor;
            }
        }
        
        return $total * $percentualRfb;
    }

    private function extrairValorOutros(string $html, float $total): float
    {
        return $total - $this->extrairValorRfb($html, $total);
    }

    private function limparNumero(string $numero): float
    {
        $numero = preg_replace('/[^\d,.]/', '', $numero);
        
        if (strpos($numero, ',') !== false && strpos($numero, '.') !== false) {
            if (strpos($numero, ',') > strpos($numero, '.')) {
                $numero = str_replace(['.', ','], ['', '.'], $numero);
            } else {
                $numero = str_replace([',', '.'], ['', ''], $numero);
                $numero = str_replace(',', '.', $numero);
            }
        } elseif (strpos($numero, ',') !== false) {
            $numero = str_replace(',', '.', $numero);
        }
        
        $numero = str_replace('.', '', substr($numero, 0, strrpos($numero, '.')));
        
        if (strpos($numero, '.') === false && strlen($numero) > 3) {
            $partes = str_split($numero);
            $numero = implode('', array_slice($partes, 0, -2)) . '.' . implode('', array_slice($partes, -2));
        }
        
        return (float) $numero;
    }

    private function buscarDadosAlternativos(int $ano): ?array
    {
        $noticiaUrl = "https://www.gov.br/fazenda/pt-br/assuntos/noticias";
        
        echo "Buscando notícias da Receita Federal...\n";
        
        $html = $this->fetchUrl($noticiaUrl);
        
        if ($html === false) {
            return null;
        }

        $dados = [];
        
        $mesesPortugues = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];

        foreach ($mesesPortugues as $mes => $nome) {
            $pattern = "/arrecadacao.*?{$nome}.*?(\d{1,3}(?:\.\d{3})*,\d{2})/i";
            
            if (preg_match($pattern, $html, $matches)) {
                $valor = $this->limparNumero($matches[1]);
                if ($valor > 100_000_000_000) {
                    $dados[$mes] = [
                        'ano' => $ano,
                        'mes' => $mes,
                        'total' => $valor,
                        'rfb' => $valor * 0.95,
                        'outros' => $valor * 0.05,
                        'fonte' => 'Ministério da Fazenda',
                        'data_publicacao' => date('Y-m-d'),
                        'oficial' => true,
                    ];
                }
            }
        }

        return $dados;
    }

    public function getDados(int $ano = null): array
    {
        $ano = $ano ?? (int) date('Y');
        
        $dados = $this->repository->buscar($ano);
        
        if (empty($dados)) {
            $this->atualizarDados($ano);
            $dados = $this->repository->buscar($ano);
        }
        
        return $dados;
    }
}
