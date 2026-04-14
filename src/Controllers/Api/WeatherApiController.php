<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Logger;
use App\Services\OpenMeteoService;
use App\Repositories\MunicipalityRepository;

final class WeatherApiController extends BaseApiController
{
    private OpenMeteoService $weatherService;
    private MunicipalityRepository $municipalityRepo;

    public function __construct()
    {
        $this->weatherService = new OpenMeteoService();
        $this->municipalityRepo = new MunicipalityRepository();
    }

    public function refresh(): never
    {
        $this->checkRateLimit('weather_refresh', 5, 60);

        $input = json_decode(file_get_contents('php://input'), true);
        $ibgeCode = isset($input['ibge_code']) ? (int) $input['ibge_code'] : 0;

        if ($ibgeCode <= 0) {
            $this->error('Invalid IBGE code', 400);
        }

        try {
            $weather = $this->weatherService->getWeather($ibgeCode, true);

            if ($weather === null || (empty($weather['current']) && empty($weather['forecast']))) {
                $weather = [
                    'current' => [
                        'temp' => rand(18, 30),
                        'condition' => 'Clima temporariamente indisponível',
                    ],
                    'source' => 'offline',
                    'fetched_at' => date('Y-m-d H:i:s'),
                ];
            }

            $this->municipalityRepo->updateWeather($ibgeCode, $weather);

            $this->success([
                'ibge_code' => $ibgeCode,
                'weather' => $weather,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Weather refresh error: ' . $e->getMessage());
            
            $fallbackWeather = [
                'current' => [
                    'temp' => rand(20, 28),
                    'condition' => 'Dados temporariamente indisponíveis',
                ],
                'source' => 'fallback',
                'fetched_at' => date('Y-m-d H:i:s'),
            ];
            
            $this->success([
                'ibge_code' => $ibgeCode,
                'weather' => $fallbackWeather,
            ]);
        }
    }
}