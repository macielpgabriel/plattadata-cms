<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Logger;
use PDO;

final class StateRepository
{
    public function findAll(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM states ORDER BY name ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByUf(string $uf): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM states WHERE uf = :uf LIMIT 1'
        );
        $stmt->execute(['uf' => strtoupper($uf)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByIbgeCode(int $ibgeCode): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM states WHERE ibge_code = :ibge_code LIMIT 1'
        );
        $stmt->execute(['ibge_code' => $ibgeCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function count(): int
    {
        $stmt = Database::connection()->query('SELECT COUNT(*) AS total FROM states');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['total'] ?? 0);
    }

    public function upsert(array $data): bool
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO states (uf, name, region, ibge_code, population, gdp, gdp_per_capita, gdp_agri, gdp_industry, gdp_services, gdp_admin, area_km2, capital_city, updated_at)
             VALUES (:uf, :name, :region, :ibge_code, :population, :gdp, :gdp_per_capita, :gdp_agri, :gdp_industry, :gdp_services, :gdp_admin, :area_km2, :capital_city, NOW())
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                region = VALUES(region),
                population = VALUES(population),
                gdp = VALUES(gdp),
                gdp_per_capita = VALUES(gdp_per_capita),
                gdp_agri = VALUES(gdp_agri),
                gdp_industry = VALUES(gdp_industry),
                gdp_services = VALUES(gdp_services),
                gdp_admin = VALUES(gdp_admin),
                area_km2 = VALUES(area_km2),
                capital_city = VALUES(capital_city),
                updated_at = NOW()'
        );

        return $stmt->execute([
            'uf' => strtoupper($data['uf']),
            'name' => $data['name'],
            'region' => $data['region'],
            'ibge_code' => $data['ibge_code'],
            'population' => $data['population'] ?? null,
            'gdp' => $data['gdp'] ?? null,
            'gdp_per_capita' => $data['gdp_per_capita'] ?? null,
            'gdp_agri' => $data['gdp_agri'] ?? null,
            'gdp_industry' => $data['gdp_industry'] ?? null,
            'gdp_services' => $data['gdp_services'] ?? null,
            'gdp_admin' => $data['gdp_admin'] ?? null,
            'area_km2' => $data['area_km2'] ?? null,
            'capital_city' => $data['capital_city'] ?? null,
        ]);
    }

    public function updateStats(string $uf, array $stats): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE states SET
                population = :population,
                gdp = :gdp,
                gdp_per_capita = :gdp_per_capita,
                gdp_agri = :gdp_agri,
                gdp_industry = :gdp_industry,
                gdp_services = :gdp_services,
                gdp_admin = :gdp_admin,
                area_km2 = :area_km2,
                capital_city = :capital_city,
                updated_at = NOW()
             WHERE uf = :uf'
        );

        return $stmt->execute([
            'uf' => strtoupper($uf),
            'population' => $stats['population'] ?? null,
            'gdp' => $stats['gdp'] ?? null,
            'gdp_per_capita' => $stats['gdp_per_capita'] ?? null,
            'gdp_agri' => $stats['gdp_agri'] ?? null,
            'gdp_industry' => $stats['gdp_industry'] ?? null,
            'gdp_services' => $stats['gdp_services'] ?? null,
            'gdp_admin' => $stats['gdp_admin'] ?? null,
            'area_km2' => $stats['area_km2'] ?? null,
            'capital_city' => $stats['capital_city'] ?? null,
        ]);
    }

    public function getCompaniesCountByState(): array
    {
        $stmt = Database::connection()->query(
            'SELECT state AS uf, COUNT(*) AS count
             FROM companies
             WHERE state IS NOT NULL AND is_hidden = 0
             GROUP BY state
             ORDER BY count DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function syncStateStats(string $uf): bool
    {
        $pdo = Database::connection();
        
        $existingState = $this->findByUf($uf);
        
        $statesApiData = $this->fetchStateDataFromApi($uf);
        
        if (!$statesApiData) {
            Logger::warning("StateRepository: dados da API não disponíveis para {$uf}, usando cache local");
            $statesApiData = $this->getHardcodedStateData($uf);
        }
        
        if (!$statesApiData) {
            return false;
        }

        $areaFromDb = $existingState['area_km2'] ?? 0;
        $areaToSave = !empty($statesApiData['area']) ? $statesApiData['area'] : $areaFromDb;

        $gdpPerCapita = $statesApiData['population'] > 0 
            ? round($statesApiData['gdp'] * 1000000 / $statesApiData['population'], 2) 
            : null;

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

        return $stmt->execute([
            'uf' => $uf,
            'ibge_code' => $statesApiData['ibge'],
            'population' => $statesApiData['population'],
            'gdp' => $statesApiData['gdp'],
            'gdp_per_capita' => $gdpPerCapita,
            'area_km2' => $areaToSave,
            'capital_city' => $statesApiData['capital'],
        ]);
    }

    private function fetchStateDataFromApi(string $uf): ?array
    {
        $uf = strtoupper($uf);
        
        $urls = [
            "https://brasilapi.com.br/api/ibge/uf/v1/{$uf}",
            "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$uf}",
        ];
        
        foreach ($urls as $url) {
            try {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'timeout' => 15,
                        'header' => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n",
                    ]
                ]);
                
                $response = @file_get_contents($url, false, $context);
                if ($response === false) {
                    continue;
                }
                
                $data = json_decode($response, true);
                if (!is_array($data) || empty($data)) {
                    continue;
                }
                
                if (isset($data['populacao']) && $data['populacao'] > 0) {
                    return [
                        'ibge' => (int) ($data['codigo_ibge'] ?? $data['id'] ?? 0),
                        'population' => (int) $data['populacao'],
                        'gdp' => isset($data['pib']) ? (float) $data['pib'] : $this->getStateGdpFromApi($uf),
                        'area' => isset($data['area_km2']) ? (float) $data['area_km2'] : 0,
                        'capital' => $data['capital'] ?? '',
                    ];
                }
            } catch (\Throwable $e) {
                Logger::warning("StateRepository: erro ao buscar {$uf}: " . $e->getMessage());
                continue;
            }
        }
        
        return null;
    }

    private function getStateGdpFromApi(string $uf): float
    {
        $url = "https://servicodados.ibge.gov.br/api/v3/agregados/5938/periodos/2021/variaveis/37?localidades=N3[{$uf}]";
        
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 15,
                    'header' => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n",
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return 0;
            }
            
            $data = json_decode($response, true);
            if (!is_array($data)) {
                return 0;
            }
            
            foreach ($data as $variable) {
                foreach ($variable['resultados'] ?? [] as $result) {
                    foreach ($result['series'] ?? [] as $series) {
                        foreach (['2021', '2022', '2020'] as $year) {
                            if (isset($series['serie'][$year]) && $series['serie'][$year] !== '...') {
                                return (float) $series['serie'][$year] * 1000;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Logger::warning("StateRepository: erro PIB {$uf}: " . $e->getMessage());
        }
        
        return 0;
    }

    private function getHardcodedStateData(string $uf): ?array
    {
        $states = [
            'AC' => ['ibge' => 12, 'population' => 906876, 'gdp' => 16061, 'area' => 164123.04, 'capital' => 'Rio Branco'],
            'AL' => ['ibge' => 27, 'population' => 3365351, 'gdp' => 78456, 'area' => 27848.14, 'capital' => 'Maceió'],
            'AP' => ['ibge' => 16, 'population' => 877613, 'gdp' => 21876, 'area' => 142828.521, 'capital' => 'Macapá'],
            'AM' => ['ibge' => 13, 'population' => 4269995, 'gdp' => 106742, 'area' => 1559146.876, 'capital' => 'Manaus'],
            'BA' => ['ibge' => 29, 'population' => 14985284, 'gdp' => 349091, 'area' => 564733.177, 'capital' => 'Salvador'],
            'CE' => ['ibge' => 23, 'population' => 9240580, 'gdp' => 134000, 'area' => 148894.447, 'capital' => 'Fortaleza'],
            'DF' => ['ibge' => 53, 'population' => 3055149, 'gdp' => 173908, 'area' => 5760.784, 'capital' => 'Brasília'],
            'ES' => ['ibge' => 32, 'population' => 4064052, 'gdp' => 172051, 'area' => 46095.56, 'capital' => 'Vitória'],
            'GO' => ['ibge' => 52, 'population' => 7206589, 'gdp' => 214654, 'area' => 340111.783, 'capital' => 'Goiânia'],
            'MA' => ['ibge' => 21, 'population' => 7153262, 'gdp' => 108782, 'area' => 329652.827, 'capital' => 'São Luís'],
            'MT' => ['ibge' => 51, 'population' => 3567234, 'gdp' => 158520, 'area' => 903357.908, 'capital' => 'Cuiabá'],
            'MS' => ['ibge' => 50, 'population' => 2839188, 'gdp' => 82253, 'area' => 357145.532, 'capital' => 'Campo Grande'],
            'MG' => ['ibge' => 31, 'population' => 21392330, 'gdp' => 632913, 'area' => 586522.122, 'capital' => 'Belo Horizonte'],
            'PA' => ['ibge' => 15, 'population' => 8777124, 'gdp' => 190873, 'area' => 1247954.666, 'capital' => 'Belém'],
            'PB' => ['ibge' => 25, 'population' => 4039877, 'gdp' => 73869, 'area' => 56469.778, 'capital' => 'João Pessoa'],
            'PR' => ['ibge' => 41, 'population' => 11516840, 'gdp' => 445449, 'area' => 199307.922, 'capital' => 'Curitiba'],
            'PE' => ['ibge' => 26, 'population' => 9614561, 'gdp' => 244699, 'area' => 98311.616, 'capital' => 'Recife'],
            'PI' => ['ibge' => 22, 'population' => 3289290, 'gdp' => 73528, 'area' => 251577.738, 'capital' => 'Teresina'],
            'RJ' => ['ibge' => 33, 'population' => 17463249, 'gdp' => 701659, 'area' => 43750.425, 'capital' => 'Rio de Janeiro'],
            'RN' => ['ibge' => 24, 'population' => 3560903, 'gdp' => 89328, 'area' => 52811.107, 'capital' => 'Natal'],
            'RS' => ['ibge' => 43, 'population' => 11422973, 'gdp' => 442244, 'area' => 281730.223, 'capital' => 'Porto Alegre'],
            'RO' => ['ibge' => 11, 'population' => 1815278, 'gdp' => 56715, 'area' => 237590.547, 'capital' => 'Porto Velho'],
            'RR' => ['ibge' => 14, 'population' => 652713, 'gdp' => 16824, 'area' => 224300.506, 'capital' => 'Boa Vista'],
            'SC' => ['ibge' => 42, 'population' => 7338473, 'gdp' => 228525, 'area' => 95736.165, 'capital' => 'Florianópolis'],
            'SP' => ['ibge' => 35, 'population' => 46649132, 'gdp' => 1780539, 'area' => 248222.362, 'capital' => 'São Paulo'],
            'SE' => ['ibge' => 28, 'population' => 2338474, 'gdp' => 67954, 'area' => 21915.116, 'capital' => 'Aracaju'],
            'TO' => ['ibge' => 17, 'population' => 1607363, 'gdp' => 38480, 'area' => 277720.52, 'capital' => 'Palmas'],
        ];

        $uf = strtoupper($uf);
        return $states[$uf] ?? null;
    }
}