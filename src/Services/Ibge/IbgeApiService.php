<?php

declare(strict_types=1);

namespace App\Services\Ibge;

use App\Core\Logger;

final class IbgeApiService
{
    private const TIMEOUT = 15;

    public function fetchJson(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT,
                'header' => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n",
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    public function getMunicipalityPopulationFromApi(int $ibgeCode): ?int
    {
        if ($pop = $this->getPopulationFromIbgeDirect($ibgeCode)) {
            return $pop;
        }
        
        if ($pop = $this->getPopulationFromIbgePesquisas($ibgeCode)) {
            return $pop;
        }

        return null;
    }

    private function getPopulationFromIbgeDirect(int $ibgeCode): ?int
    {
        $endpoints = [
            "https://servicodados.ibge.gov.br/api/v3/agregados/9514/periodos/2022/variaveis/93?localidades=N6[{$ibgeCode}]",
            "https://servicodados.ibge.gov.br/api/v3/agregados/9514/periodos/2022/variaveis/93?localidades=N6[{$ibgeCode}]&view=normal",
            "https://servicodados.ibge.gov.br/api/v1/pesquisa/resultados/censo/{$ibgeCode}",
        ];
        
        foreach ($endpoints as $url) {
            try {
                $data = $this->fetchJson($url);
                
                if ($data && !empty($data)) {
                    if (isset($data[0]['resultados'][0]['series'][0]['serie']['2022'])) {
                        $val = $data[0]['resultados'][0]['series'][0]['serie']['2022'];
                        if (is_numeric($val) && $val > 0) return (int) $val;
                    }
                    if (isset($data['res'][0]['D1C'])) {
                        $val = (int) preg_replace('/\D/', '', $data['res'][0]['D1C'] ?? '');
                        if ($val > 0) return $val;
                    }
                    if (isset($data['populacao']['2022'])) {
                        $val = (int) $data['populacao']['2022'];
                        if ($val > 0) return $val;
                    }
                }
            } catch (\Throwable $e) {
                Logger::warning('Erro ao buscar população IBGE Direct: ' . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    private function getPopulationFromIbgePesquisas(int $ibgeCode): ?int
    {
        $urls = [
            "https://servicodados.ibge.gov.br/api/v1/pesquisas/CP2022/periodos/2022/indicadores/9326/resultados/N6[{$ibgeCode}]",
            "https://servicodados.ibge.gov.br/api/v1/pesquisas/-/periodos/2022/indicadores/9324/resultados/N6[{$ibgeCode}]",
        ];
        
        foreach ($urls as $url) {
            try {
                $data = $this->fetchJson($url);
                if ($data && isset($data[0]['resultados'][0]['series'][0]['serie']['2022'])) {
                    $val = $data[0]['resultados'][0]['series'][0]['serie']['2022'];
                    if (is_numeric($val) && $val > 0) return (int) $val;
                }
            } catch (\Throwable $e) {
                Logger::warning('Erro IBGE Pesquisas: ' . $e->getMessage());
                continue;
            }
        }
        
        return null;
    }

    public function getMunicipalityGdpFromApi(int $ibgeCode): ?array
    {
        if ($gdpData = $this->getGdpFromIbgeDirect($ibgeCode)) {
            return $gdpData;
        }

        return null;
    }

    private function getGdpFromIbgeDirect(int $ibgeCode): ?array
    {
        $url = "https://servicodados.ibge.gov.br/api/v3/agregados/5938/periodos/2021/variaveis/37?localidades=N6[{$ibgeCode}]";
        $data = $this->fetchJson($url);
        
        if (!is_array($data) || empty($data)) {
            return null;
        }

        $gdp = null;
        foreach ($data as $variable) {
            foreach ($variable['resultados'] ?? [] as $result) {
                foreach ($result['series'] ?? [] as $series) {
                    foreach (['2021', '2022', '2020'] as $year) {
                        if (isset($series['serie'][$year]) && $series['serie'][$year] !== '...') {
                            $gdp = (float) $series['serie'][$year];
                            break 3;
                        }
                    }
                }
            }
        }

        if ($gdp === null) {
            return null;
        }

        return [
            'gdp' => $gdp * 1000,
            'gdp_per_capita' => null,
        ];
    }

    public function getMunicipalityVehicleFleetFromApi(int $ibgeCode): ?int
    {
        $url = "https://servicodados.ibge.gov.br/api/v3/agregados/6875/periodos/2017/variaveis/9573?localidades=N6[{$ibgeCode}]";
        $data = $this->fetchJson($url);
        
        if (!is_array($data) || empty($data)) {
            return null;
        }

        foreach ($data as $variable) {
            foreach ($variable['resultados'] ?? [] as $result) {
                foreach ($result['series'] ?? [] as $series) {
                    $value = $series['serie']['2017'] ?? null;
                    if (is_numeric($value)) {
                        return (int) $value;
                    }
                }
            }
        }

        return null;
    }

    public function getMunicipalityBusinessUnitsFromApi(int $ibgeCode): ?int
    {
        $url = "https://servicodados.ibge.gov.br/api/v3/agregados/1685/periodos/2021/variaveis/706?localidades=N6[{$ibgeCode}]";
        $data = $this->fetchJson($url);
        
        if (!is_array($data) || empty($data)) {
            return null;
        }

        foreach ($data as $variable) {
            foreach ($variable['resultados'] ?? [] as $result) {
                foreach ($result['series'] ?? [] as $series) {
                    foreach (['2021', '2020', '2019'] as $year) {
                        if (isset($series['serie'][$year]) && is_numeric($series['serie'][$year])) {
                            return (int) $series['serie'][$year];
                        }
                    }
                }
            }
        }

        return null;
    }

    public function getPopulationByGender(int $ibgeCode): ?array
    {
        $urls = [
            "https://servicodados.ibge.gov.br/api/v3/agregados/4719/periodos/2022/variaveis/9324?localidades=N6[{$ibgeCode}]",
            "https://servicodados.ibge.gov.br/api/v3/agregados/4719/periodos/2022/variaveis/9325?localidades=N6[{$ibgeCode}]",
        ];

        $male = null;
        $female = null;

        foreach ($urls as $url) {
            $data = $this->fetchJson($url);
            if (!$data || empty($data)) continue;

            foreach ($data as $item) {
                foreach ($item['resultados'] ?? [] as $result) {
                    foreach ($result['series'] ?? [] as $series) {
                        if (isset($series['localidade']['id']) && (int) $series['localidade']['id'] === $ibgeCode) {
                            foreach ($series['serie'] ?? [] as $year => $value) {
                                if ($value !== '...' && is_numeric($value)) {
                                    if (stripos($url, '9324') !== false) {
                                        $male = (int) $value;
                                    } else {
                                        $female = (int) $value;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($male === null || $female === null) {
            return null;
        }

        $total = $male + $female;

        return [
            'male' => $male,
            'female' => $female,
            'male_percent' => $total > 0 ? round(($male / $total) * 100, 2) : 0,
            'female_percent' => $total > 0 ? round(($female / $total) * 100, 2) : 0,
        ];
    }

    public function getStatesFromApi(): array
    {
        $urls = [
            "https://brasilapi.com.br/api/ibge/uf/v1",
            "https://servicodados.ibge.gov.br/api/v1/localidades/estados",
        ];

        foreach ($urls as $url) {
            try {
                $data = $this->fetchJson($url);
                if ($data && !empty($data)) {
                    return $data;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [];
    }
}