<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MunicipalityRepository;

final class CptecService
{
    use HttpFetchTrait;

    private MunicipalityRepository $municipalityRepository;
    private string $baseUrl;

    public function __construct()
    {
        $this->municipalityRepository = new MunicipalityRepository();
        $this->baseUrl = (string) config('app.cptec.base_url', 'https://brasilapi.com.br/api/cptec/v1');
        $this->timeout = (int) config('app.cptec.timeout', 10);
    }

    public function getWeather(int $ibgeCode, bool $force = false): ?array
    {
        if (!$force) {
            $cached = $this->getCachedWeather($ibgeCode);
            if ($cached !== null) {
                return $cached;
            }
        }

        $weather = $this->fetchWeatherByIbge($ibgeCode);

        if ($weather === null) {
            $weather = $this->getWeatherFallback($ibgeCode);
        }

        return $weather;
    }

    private function getWeatherFallback(int $ibgeCode): ?array
    {
        $municipality = $this->municipalityRepository->findByIbgeCode($ibgeCode);
        if (!$municipality) {
            return null;
        }

        $uf = strtoupper((string) ($municipality['state_uf'] ?? ''));
        $capitalMap = [
            'AC' => ['id' => 1, 'name' => 'Rio Branco'],
            'AL' => ['id' => 10, 'name' => 'Maceio'],
            'AP' => ['id' => 17, 'name' => 'Macapa'],
            'AM' => ['id' => 2, 'name' => 'Manaus'],
            'BA' => ['id' => 3, 'name' => 'Salvador'],
            'CE' => ['id' => 4, 'name' => 'Fortaleza'],
            'DF' => ['id' => 411, 'name' => 'Brasilia'],
            'ES' => ['id' => 18, 'name' => 'Vitoria'],
            'GO' => ['id' => 52, 'name' => 'Goiania'],
            'MA' => ['id' => 19, 'name' => 'Sao Luis'],
            'MT' => ['id' => 14, 'name' => 'Cuiaba'],
            'MS' => ['id' => 27, 'name' => 'Campo Grande'],
            'MG' => ['id' => 5, 'name' => 'Belo Horizonte'],
            'PA' => ['id' => 6, 'name' => 'Belem'],
            'PB' => ['id' => 20, 'name' => 'Joao Pessoa'],
            'PR' => ['id' => 7, 'name' => 'Curitiba'],
            'PE' => ['id' => 8, 'name' => 'Recife'],
            'PI' => ['id' => 21, 'name' => 'Teresina'],
            'RJ' => ['id' => 9, 'name' => 'Rio de Janeiro'],
            'RN' => ['id' => 22, 'name' => 'Natal'],
            'RS' => ['id' => 11, 'name' => 'Porto Alegre'],
            'RO' => ['id' => 23, 'name' => 'Porto Velho'],
            'RR' => ['id' => 24, 'name' => 'Boa Vista'],
            'SC' => ['id' => 12, 'name' => 'Florianopolis'],
            'SP' => ['id' => 13, 'name' => 'Sao Paulo'],
            'SE' => ['id' => 25, 'name' => 'Aracaju'],
            'TO' => ['id' => 26, 'name' => 'Palmas'],
        ];

        if (isset($capitalMap[$uf])) {
            $capital = $capitalMap[$uf];
            $url = $this->baseUrl . '/clima/previsao/' . $capital['id'];
            $data = $this->fetchJson($url);

            if (is_array($data) && !empty($data)) {
                return [
                    'ibge_code' => $ibgeCode,
                    'city' => $municipality['name'] ?? $capital['name'],
                    'state' => $uf,
                    'updated_at' => $data['atualizado_em'] ?? null,
                    'current' => $data['clima'][0] ?? null,
                    'forecast' => $data['clima'] ?? [],
                    'source' => 'CPTEC/INPE via BrasilAPI (capital fallback)',
                    'fetched_at' => date('Y-m-d H:i:s'),
                    'fallback' => true,
                ];
            }
        }

        return null;
    }

    /**
     * Fetch weather forecast for a municipality by IBGE code.
     * Uses BrasilAPI's CPTEC endpoint.
     *
     * @param int $ibgeCode IBGE municipality code
     * @return array|null Weather data or null on failure
     */
    public function fetchWeatherByIbge(int $ibgeCode): ?array
    {
        $municipality = $this->municipalityRepository->findByIbgeCode($ibgeCode);
        if (!$municipality) {
            return null;
        }

        $cityName = (string) ($municipality['name'] ?? '');
        $uf = strtoupper((string) ($municipality['state_uf'] ?? ''));

        if ($cityName === '' || $uf === '') {
            return null;
        }

        $searchUrl = $this->baseUrl . '/cidade/' . urlencode($cityName);
        $cities = $this->fetchJson($searchUrl);

        $cptecId = null;
        if (is_array($cities)) {
            $cityNameNormalized = $this->normalizeComparableText($cityName);

            foreach ($cities as $city) {
                if (!is_array($city)) {
                    continue;
                }

                $candidateName = (string) ($city['nome'] ?? '');
                $candidateUf = strtoupper((string) ($city['estado'] ?? ''));

                if ($candidateUf !== $uf) {
                    continue;
                }

                if ($this->normalizeComparableText($candidateName) === $cityNameNormalized) {
                    $cptecId = (int) ($city['id'] ?? 0);
                    break;
                }
            }

            if (!$cptecId) {
                foreach ($cities as $city) {
                    if (!is_array($city)) {
                        continue;
                    }
                    if (strtoupper((string) ($city['estado'] ?? '')) === $uf) {
                        $cptecId = (int) ($city['id'] ?? 0);
                        break;
                    }
                }
            }
        }

        if (!$cptecId) {
            return null;
        }

        $url = $this->baseUrl . '/clima/previsao/' . $cptecId;
        $data = $this->fetchJson($url);

        if (!is_array($data)) {
            return null;
        }

        $weather = [
            'ibge_code' => $ibgeCode,
            'city' => $data['cidade'] ?? $data['name'] ?? null,
            'state' => $data['estado'] ?? null,
            'updated_at' => $data['atualizado_em'] ?? $data['updated_at'] ?? null,
            'current' => null,
            'forecast' => [],
            'source' => 'CPTEC/INPE via BrasilAPI',
            'fetched_at' => date('Y-m-d H:i:s'),
        ];

        // Current weather conditions (if available)
        if (isset($data['clima']) && is_array($data['clima'])) {
            foreach ($data['clima'] as $day) {
                $forecast = [
                    'date' => $day['data'] ?? null,
                    'condition' => $day['condicao'] ?? $day['condition'] ?? null,
                    'condition_description' => $day['condicao_desc'] ?? $day['description'] ?? null,
                    'min_temp' => $day['min'] ?? $day['min_temp'] ?? null,
                    'max_temp' => $day['max'] ?? $day['max_temp'] ?? null,
                    'uv_index' => $day['indice_uv'] ?? $day['uv_index'] ?? null,
                    'rain_probability' => $day['probabilidade_chuva'] ?? $day['rain_probability'] ?? null,
                ];

                $weather['forecast'][] = $forecast;

                // First day is current
                if ($weather['current'] === null) {
                    $weather['current'] = $forecast;
                }
            }
        }

        // Alternative format: direct current weather
        if (isset($data['temp']) || isset($data['temperatura'])) {
            $weather['current'] = [
                'temp' => $data['temp'] ?? $data['temperatura'] ?? null,
                'condition' => $data['condicao'] ?? $data['condition'] ?? null,
                'humidity' => $data['umidade'] ?? $data['humidity'] ?? null,
                'wind_speed' => $data['vento'] ?? $data['wind_speed'] ?? null,
                'wind_direction' => $data['direcao_vento'] ?? $data['wind_direction'] ?? null,
            ];
        }

        // Cache the weather data
        if (!empty($weather['current']) || !empty($weather['forecast'])) {
            $this->municipalityRepository->updateWeather($ibgeCode, $weather);
        }

        return $weather;
    }

    /**
     * Fetch weather for capital cities.
     */
    public function fetchCapitalWeather(): array
    {
        $url = $this->baseUrl . '/clima/capitais';
        $data = $this->fetchJson($url);

        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * Get cached weather for a municipality.
     */
    public function getCachedWeather(int $ibgeCode): ?array
    {
        $municipality = $this->municipalityRepository->findByIbgeCode($ibgeCode);

        if ($municipality === null || empty($municipality['weather_data'])) {
            return null;
        }

        $weatherData = json_decode((string) $municipality['weather_data'], true);
        if (!is_array($weatherData)) {
            return null;
        }

        // Check if cache is fresh (within 12 hours for weather)
        $updatedAt = $municipality['weather_updated_at'] ?? null;
        if ($updatedAt !== null) {
            $cacheAge = (time() - strtotime((string) $updatedAt)) / 3600; // hours
            if ($cacheAge < 12) {
                return $weatherData;
            }
        }

        return $weatherData;
    }

    /**
     * Translate weather condition code to human-readable text.
     */
    public static function translateCondition(?string $code): string
    {
        if ($code === null) {
            return 'Nao disponivel';
        }

        return match (strtoupper($code)) {
            'EC', 'EL', 'EN' => 'Encoberto',
            'CI', 'CIN' => 'Ceu Encoberto com Possibilidade de Chuva',
            'CT' => 'Ceu Encoberto',
            'PN', 'PT', 'PNT' => 'Possibilidade de Pancadas de Chuva',
            'PC', 'PCT' => 'Possibilidade de Chuva',
            'PNB', 'PNV', 'PNC', 'PNP' => 'Possibilidade de Chuva a Noite',
            'PP', 'PPT' => 'Possibilidade de Chuva',
            'PR', 'PRT' => 'Possibilidade de Chuva',
            'PS', 'PST' => 'Possibilidade de Chuva pela Manha',
            'PV' => 'Possibilidade de Chuva a Noite',
            'CH', 'CHT' => 'Chuva',
            'CM', 'CMT' => 'Chuva pela Manha',
            'CN', 'CNT' => 'Chuva a Noite',
            'CS', 'CST' => 'Chuva pela Manha',
            'CV', 'CVT' => 'Chuva a Noite',
            'CVV' => 'Chuva a Noite',
            'NP', 'NPT' => 'Nublado e Pancadas de Chuva',
            'NC', 'NCT' => 'Nublado com Possibilidade de Chuva',
            'NM', 'NMT' => 'Nublado',
            'NU', 'NUT' => 'Nublado',
            'NV', 'NVT' => 'Nublado a Noite',
            'NS', 'NST' => 'Nublado pela Manha',
            'PA', 'PAT' => 'Parcialmente Nublado',
            'SC', 'SCT' => 'Sol com Possibilidade de Chuva',
            'SP', 'SPT' => 'Sol e Pancadas de Chuva',
            'SU', 'SUT' => 'Sol',
            'SV', 'SVT' => 'Sol a Noite',
            'SS', 'SST' => 'Sol pela Manha',
            'IN', 'INT' => 'Instavel',
            'PS' => 'Predominio de Sol',
            'PM' => 'Predominio de Sol pela Manha',
            'VV' => 'Variavel',
            default => $code,
        };
    }

    private function normalizeComparableText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($normalized) && $normalized !== '') {
                $value = $normalized;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s]+/', '', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
