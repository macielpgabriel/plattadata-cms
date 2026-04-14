#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Services\ArrecadacaoService;
use App\Repositories\ArrecadacaoRepository;

echo "\n";
echo "========================================\n";
echo "  Atualizador de Arrecadacao Federal\n";
echo "========================================\n";
echo "\n";

$ano = isset($argv[1]) ? (int) $argv[1] : (int) date('Y');
$repo = new ArrecadacaoRepository();

if (isset($argv[2]) && $argv[2] === '--seed') {
    echo "Inserindo dados iniciais...\n";
    seedDadosIniciais($repo, $ano);
    exit(0);
}

if (isset($argv[2]) && is_numeric($argv[2])) {
    $mes = (int) $argv[2];
    $valor = isset($argv[3]) ? (float) $argv[3] : null;
    
    if ($valor === null) {
        echo "Erro: Informe o valor do mes\n";
        echo "Uso: php update_arrecadacao.php {$ano} {$mes} VALOR\n";
        exit(1);
    }
    
    $repo->salvarMes($ano, $mes, [
        'total' => $valor,
        'rfb' => $valor * 0.95,
        'outros' => $valor * 0.05,
        'fonte' => 'Receita Federal',
        'data_publicacao' => date('Y-m-d'),
        'oficial' => true,
    ]);
    
    $meses = ['', 'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    echo "✓ {$meses[$mes]} de {$ano} inserido: R\$ " . number_format($valor, 2, ',', '.') . "\n";
    exit(0);
}

echo "Ano: {$ano}\n";
echo "Data: " . date('d/m/Y H:i:s') . "\n";
echo "\n";

$service = new ArrecadacaoService();

try {
    $resultado = $service->atualizarDados($ano);
    
    echo "\n";
    
    if ($resultado['sucesso']) {
        echo "✓ Atualizacao concluida com sucesso!\n";
        echo "\nDados atualizados:\n";
        foreach ($resultado['dados'] as $mes => $dados) {
            $meses = ['', 'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
            $valor = number_format($dados['total'], 2, ',', '.');
            echo "  - {$meses[$mes]}: R\$ {$valor}\n";
        }
    } else {
        echo "✗ Falha na atualizacao\n";
        if (isset($resultado['erro'])) {
            echo "  Erro: {$resultado['erro']}\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Erro fatal: " . $e->getMessage() . "\n";
    echo "  Arquivo: " . $e->getFile() . "\n";
    echo "  Linha: " . $e->getLine() . "\n";
    exit(1);
}

echo "\n";

function seedDadosIniciais(ArrecadacaoRepository $repo, int $ano): void
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
    
    $anos = $dados[$ano] ?? [];
    
    if (empty($anos)) {
        echo "Nenhum dado inicial para {$ano}\n";
        return;
    }
    
    foreach ($anos as $mes => $valor) {
        $repo->salvarMes($ano, $mes, [
            'total' => $valor,
            'rfb' => $valor * 0.95,
            'outros' => $valor * 0.05,
            'fonte' => 'Receita Federal',
            'data_publicacao' => date('Y-m-d'),
            'oficial' => true,
        ]);
    }
    
    $meses = ['', 'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    echo "✓ Dados de {$ano} inseridos:\n";
    foreach ($anos as $mes => $valor) {
        echo "  - {$meses[$mes]}: R\$ " . number_format($valor, 2, ',', '.') . "\n";
    }
}
echo "========================================\n";
echo "  Atualizador de Arrecadação Federal\n";
echo "========================================\n";
echo "\n";

$ano = isset($argv[1]) ? (int) $argv[1] : (int) date('Y');

echo "Ano: {$ano}\n";
echo "Data: " . date('d/m/Y H:i:s') . "\n";
echo "\n";

$service = new ArrecadacaoService();

try {
    $resultado = $service->atualizarDados($ano);
    
    echo "\n";
    
    if ($resultado['sucesso']) {
        echo "✓ Atualização concluída com sucesso!\n";
        echo "\nDados atualizados:\n";
        foreach ($resultado['dados'] as $mes => $dados) {
            $meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
            $valor = number_format($dados['total'], 2, ',', '.');
            echo "  - {$meses[$mes]}: R\$ {$valor}\n";
        }
    } else {
        echo "✗ Falha na atualização\n";
        if (isset($resultado['erro'])) {
            echo "  Erro: {$resultado['erro']}\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Erro fatal: " . $e->getMessage() . "\n";
    echo "  Arquivo: " . $e->getFile() . "\n";
    echo "  Linha: " . $e->getLine() . "\n";
    exit(1);
}

echo "\n";
