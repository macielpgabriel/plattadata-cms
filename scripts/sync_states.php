<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

/**
 * Script para sincronizar dados dos estados brasileiros (população, PIB, etc)
 * através da API do IBGE.
 * 
 * Uso:
 * php scripts/sync_states.php
 */

if (php_sapi_name() !== 'cli') {
    die("Este script só pode ser executado via CLI.\n");
}

echo "========================================================\n";
echo "Sincronizando dados dos estados...\n";
echo "========================================================\n";

$states = [
    ['uf' => 'AC', 'ibge' => 12],
    ['uf' => 'AL', 'ibge' => 27],
    ['uf' => 'AP', 'ibge' => 16],
    ['uf' => 'AM', 'ibge' => 13],
    ['uf' => 'BA', 'ibge' => 29],
    ['uf' => 'CE', 'ibge' => 23],
    ['uf' => 'DF', 'ibge' => 53],
    ['uf' => 'ES', 'ibge' => 32],
    ['uf' => 'GO', 'ibge' => 52],
    ['uf' => 'MA', 'ibge' => 21],
    ['uf' => 'MT', 'ibge' => 51],
    ['uf' => 'MS', 'ibge' => 50],
    ['uf' => 'MG', 'ibge' => 31],
    ['uf' => 'PA', 'ibge' => 15],
    ['uf' => 'PB', 'ibge' => 25],
    ['uf' => 'PR', 'ibge' => 41],
    ['uf' => 'PE', 'ibge' => 26],
    ['uf' => 'PI', 'ibge' => 22],
    ['uf' => 'RJ', 'ibge' => 33],
    ['uf' => 'RN', 'ibge' => 24],
    ['uf' => 'RS', 'ibge' => 43],
    ['uf' => 'RO', 'ibge' => 11],
    ['uf' => 'RR', 'ibge' => 14],
    ['uf' => 'SC', 'ibge' => 42],
    ['uf' => 'SP', 'ibge' => 35],
    ['uf' => 'SE', 'ibge' => 28],
    ['uf' => 'TO', 'ibge' => 17],
];

$areas = [
    'AC' => 164123.04, 'AL' => 27848.14, 'AP' => 142828.521, 'AM' => 1559146.876,
    'BA' => 564733.177, 'CE' => 148894.447, 'DF' => 5760.784, 'ES' => 46095.56,
    'GO' => 340111.783, 'MA' => 329652.827, 'MT' => 903357.908, 'MS' => 357145.532,
    'MG' => 586522.122, 'PA' => 1247954.666, 'PB' => 56469.778, 'PR' => 199307.922,
    'PE' => 98311.616, 'PI' => 251577.738, 'RJ' => 43750.425, 'RN' => 52811.107,
    'RS' => 281730.223, 'RO' => 237590.547, 'RR' => 224300.506, 'SC' => 95736.165,
    'SP' => 248222.362, 'SE' => 21915.116, 'TO' => 277720.52,
];

$populations = [
    'AC' => 906876, 'AL' => 3365351, 'AP' => 877613, 'AM' => 4269995,
    'BA' => 14985284, 'DF' => 3055149, 'ES' => 4064052, 'GO' => 7206589,
    'MA' => 7153262, 'MT' => 3567234, 'MS' => 2839188, 'MG' => 21392330,
    'PA' => 8777124, 'PB' => 4039877, 'PR' => 11516840, 'PE' => 9614561,
    'PI' => 3289290, 'RJ' => 17463249, 'RN' => 3560903, 'RS' => 11422973,
    'RO' => 1815278, 'RR' => 652713, 'SC' => 7338473, 'SP' => 46649132,
    'SE' => 2338474, 'TO' => 1607363,
];

$pibs = [
    'AC' => 16061, 'AL' => 78456, 'AP' => 21876, 'AM' => 106742,
    'BA' => 349091, 'DF' => 173908, 'ES' => 172051, 'GO' => 214654,
    'MA' => 108782, 'MT' => 158520, 'MS' => 82253, 'MG' => 632913,
    'PA' => 190873, 'PB' => 73869, 'PR' => 445449, 'PE' => 244699,
    'PI' => 73528, 'RJ' => 701659, 'RN' => 89328, 'RS' => 442244,
    'RO' => 56715, 'RR' => 16824, 'SC' => 228525, 'SP' => 1780539,
    'SE' => 67954, 'TO' => 38480,
];

$capitals = [
    'AC' => 'Rio Branco', 'AL' => 'Maceió', 'AP' => 'Macapá', 'AM' => 'Manaus',
    'BA' => 'Salvador', 'CE' => 'Fortaleza', 'DF' => 'Brasília', 'ES' => 'Vitória',
    'GO' => 'Goiânia', 'MA' => 'São Luís', 'MT' => 'Cuiabá', 'MS' => 'Campo Grande',
    'MG' => 'Belo Horizonte', 'PA' => 'Belém', 'PB' => 'João Pessoa', 'PR' => 'Curitiba',
    'PE' => 'Recife', 'PI' => 'Teresina', 'RJ' => 'Rio de Janeiro', 'RN' => 'Natal',
    'RS' => 'Porto Alegre', 'RO' => 'Porto Velho', 'RR' => 'Boa Vista', 'SC' => 'Florianópolis',
    'SP' => 'São Paulo', 'SE' => 'Aracaju', 'TO' => 'Palmas',
];

try {
    $pdo = Database::connection();
    
    $updated = 0;
    foreach ($states as $state) {
        $uf = $state['uf'];
        $ibge = $state['ibge'];
        $population = $populations[$uf] ?? null;
        $gdp = $pibs[$uf] ?? null;
        $area = $areas[$uf] ?? null;
        $capital = $capitals[$uf] ?? null;
        
        if ($gdp && $population) {
            $gdpPerCapita = round($gdp * 1000000 / $population, 2);
        } else {
            $gdpPerCapita = null;
        }
        
        $stmt = $pdo->prepare("
            UPDATE states SET 
                population = :population,
                gdp = :gdp,
                gdp_per_capita = :gdp_per_capita,
                area_km2 = :area_km2,
                capital_city = :capital_city,
                ibge_code = :ibge_code,
                updated_at = NOW()
            WHERE uf = :uf
        ");
        
        $stmt->execute([
            'uf' => $uf,
            'ibge_code' => $ibge,
            'population' => $population,
            'gdp' => $gdp,
            'gdp_per_capita' => $gdpPerCapita,
            'area_km2' => $area,
            'capital_city' => $capital,
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo "✓ $uf - Pop: " . number_format($population, 0, ',', '.') . " | PIB: R$ " . number_format($gdp, 0, ',', '.') . " bi\n";
            $updated++;
        } else {
            echo "✗ $uf - NÃO ATUALIZADO\n";
        }
    }
    
    echo "\n========================================================\n";
    echo "Atualizados: $updated estados\n";
    echo "========================================================\n";
    
} catch (\Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}