<?php

declare(strict_types=1);

use App\Controllers\Api\CompanyApiController;
use App\Controllers\Api\InfoApiController;
use App\Controllers\Api\WeatherApiController;

$router->get('/api/v1', [InfoApiController::class, 'index']);
$router->get('/api/v1/health', [InfoApiController::class, 'health']);
$router->get('/api/v1/cnpj', [CompanyApiController::class, 'search']);
$router->get('/api/v1/cnpj/{cnpj}', [CompanyApiController::class, 'get']);
$router->get('/api/v1/companies', [CompanyApiController::class, 'list']);
$router->get('/api/v1/exchange-rates', [InfoApiController::class, 'exchangeRates']);
$router->get('/api/v1/exchange-rates/{currency}/history', [InfoApiController::class, 'exchangeRateHistory']);
$router->post('/api/v1/weather/refresh', [WeatherApiController::class, 'refresh']);
$router->get('/api/v1/health/drive', [InfoApiController::class, 'testDriveConnection']);
$router->post('/api/v1/exchange-rates/refresh', [InfoApiController::class, 'refreshExchangeRates']);
