<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Repositories\MunicipalityRepository;

final class OpenMeteoService
{
    use HttpFetchTrait;

    private MunicipalityRepository $municipalityRepository;
    private string $geocodeUrl;
    private string $weatherUrl;
    private ?int $lastIbgeCode = null;

    public function __construct()
    {
        $this->municipalityRepository = new MunicipalityRepository();
        $this->geocodeUrl = 'https://geocoding-api.open-meteo.com/v1/search';
        $this->weatherUrl = 'https://api.open-meteo.com/v1/forecast';
        $this->timeout = (int) config('app.openmeteo.timeout', 10);
    }

    public function getWeather(int $ibgeCode, bool $force = false): ?array
    {
        if (!$force) {
            $cached = $this->getCachedWeather($ibgeCode);
            if ($cached !== null) {
                // Ensure compatibility with old CPTEC data format
                if (isset($cached['current']) && is_array($cached['current'])) {
                    if (!isset($cached['current']['temp']) && isset($cached['current']['max_temp'])) {
                        $cached['current']['temp'] = $cached['current']['max_temp'];
                    }
                }
                // Add fetched_at from database weather_updated_at
                $municipality = $this->municipalityRepository->findByIbgeCode($ibgeCode);
                if ($municipality && !empty($municipality['weather_updated_at'])) {
                    $cached['fetched_at'] = $municipality['weather_updated_at'];
                    $cached['source'] = $cached['source'] ?? 'Open-Meteo';
                }
                return $cached;
            }
        }

        $weather = $this->fetchWeatherByIbge($ibgeCode);

        if ($weather === null) {
            $cptecService = new CptecService();
            $weather = $cptecService->getWeather($ibgeCode, $force);
        }

        return $weather;
    }

    public function fetchWeatherByIbge(int $ibgeCode): ?array
    {
        $municipality = $this->municipalityRepository->findByIbgeCode($ibgeCode);
        if (!$municipality) {
            return null;
        }

        $this->lastIbgeCode = $ibgeCode;

        $cityName = (string) ($municipality['name'] ?? '');
        $uf = strtoupper((string) ($municipality['state_uf'] ?? ''));

        if ($cityName === '' || $uf === '') {
            return null;
        }

        // Try multiple search patterns
        $searchQueries = [
            $cityName . ',' . $uf,
            $uf . '/' . $cityName,
            $cityName . ', Brazil',
            $cityName,
        ];

        $location = null;
        $lastError = null;
        foreach ($searchQueries as $query) {
            $geoData = $this->fetchGeocode($query);
            if ($geoData && !empty($geoData['results'])) {
                // Find best match
                foreach ($geoData['results'] as $result) {
                    if (($result['country_code'] ?? '') === 'BR') {
                        $location = $result;
                        break;
                    }
                }
                if (!$location) {
                    $location = $geoData['results'][0];
                }
                break;
            }
        }

        if (!$location) {
            Logger::warning("OpenMeteoService: No location found for query, ibge: $ibgeCode, city: $cityName, uf: $uf");
            return null;
        }

        try {
            $weather = $this->fetchWeather(
                (float) $location['latitude'],
                (float) $location['longitude']
            );
        } catch (\Throwable $e) {
            Logger::error("OpenMeteoService: fetchWeather failed for $ibgeCode: " . $e->getMessage());
            return null;
        }

        if ($weather === null) {
            return null;
        }

        return [
            'ibge_code' => $ibgeCode,
            'city' => $location['name'] ?? $cityName,
            'state' => $uf,
            'current' => $weather['current'],
            'forecast' => $weather['forecast'],
            'source' => 'Open-Meteo',
            'fetched_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function fetchGeocode(string $query): ?array
    {
        $url = $this->geocodeUrl . '?name=' . urlencode($query) . '&count=10&language=pt&format=json&country=Brasil';
        $data = $this->fetchJson($url);
        
        if (!$data || empty($data['results'])) {
            $url = $this->geocodeUrl . '?name=' . urlencode($query) . '&count=10&language=pt&format=json';
            $data = $this->fetchJson($url);
        }
        
        return $data;
    }

    private function fetchWeather(float $latitude, float $longitude): ?array
    {
        $params = http_build_query([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m,apparent_temperature',
            'hourly' => 'temperature_2m,weather_code',
            'daily' => 'temperature_2m_max,temperature_2m_min,weather_code',
            'timezone' => 'auto',
            'forecast_days' => 7,
        ]);

        $url = $this->weatherUrl . '?' . $params;
        $data = $this->fetchJson($url);

        if (!is_array($data)) {
            return null;
        }

        $current = null;
        if (isset($data['current'])) {
            $current = [
                'temp' => round($data['current']['temperature_2m'] ?? 0),
                'feels_like' => round($data['current']['apparent_temperature'] ?? 0),
                'humidity' => $data['current']['relative_humidity_2m'] ?? null,
                'wind_speed' => $data['current']['wind_speed_10m'] ?? null,
                'condition' => $this->translateWeatherCode($data['current']['weather_code'] ?? null),
                'condition_code' => $data['current']['weather_code'] ?? null,
            ];
        }

        $forecast = [];
        if (isset($data['daily']) && is_array($data['daily'])) {
            $dates = $data['daily']['time'] ?? [];
            $maxTemps = $data['daily']['temperature_2m_max'] ?? [];
            $minTemps = $data['daily']['temperature_2m_min'] ?? [];
            $codes = $data['daily']['weather_code'] ?? [];

            foreach ($dates as $i => $date) {
                $forecast[] = [
                    'date' => $date,
                    'max_temp' => isset($maxTemps[$i]) ? round($maxTemps[$i]) : null,
                    'min_temp' => isset($minTemps[$i]) ? round($minTemps[$i]) : null,
                    'condition' => $this->translateWeatherCode($codes[$i] ?? null),
                    'condition_code' => $codes[$i] ?? null,
                ];
            }
        }

        if (!empty($current) || !empty($forecast)) {
            $weatherToSave = [
                'current' => $current,
                'forecast' => $forecast,
                'source' => 'Open-Meteo',
                'fetched_at' => date('Y-m-d H:i:s'),
            ];
            $this->municipalityRepository->updateWeather(
                $this->lastIbgeCode,
                $weatherToSave
            );
        }

        return $weatherToSave;
    }

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

        $updatedAt = $municipality['weather_updated_at'] ?? null;
        if ($updatedAt !== null) {
            $cacheAge = (time() - strtotime((string) $updatedAt)) / 3600;
            if ($cacheAge < 1) {
                return $weatherData;
            }
        }

        return $weatherData;
    }

    private function translateWeatherCode(?int $code): string
    {
        if ($code === null) {
            return 'Desconhecido';
        }

        return match ($code) {
            0 => 'Ceu limpo',
            1, 2, 3 => 'Parcialmente nublado',
            45, 48 => 'Nevoeiro',
            51, 53, 55 => 'Garoa',
            56, 57 => 'Garoa gelada',
            61, 63, 65 => 'Chuva',
            66, 67 => 'Chuva gelada',
            71, 73, 75 => 'Neve',
            77 => 'Grãos de neve',
            80, 81, 82 => 'Pancadas de chuva',
            85, 86 => 'Pancadas de neve',
            95 => 'Trovoada',
            96, 99 => 'Trovoada com granizo',
            default => 'Desconhecido',
        };
    }
}
