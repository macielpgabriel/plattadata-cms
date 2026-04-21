<?php

declare(strict_types=1);

use App\Controllers\Api\CompanyApiController;
use App\Controllers\Api\InfoApiController;
use App\Controllers\Api\WeatherApiController;
use App\Middleware\AuthMiddleware;

$router->get('/api/v1', [InfoApiController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/v1/health', [InfoApiController::class, 'health'], [AuthMiddleware::class]);
$router->get('/api/v1/cnpj', [CompanyApiController::class, 'search'], [AuthMiddleware::class]);
$router->get('/api/v1/cnpj/{cnpj}', [CompanyApiController::class, 'get'], [AuthMiddleware::class]);
$router->get('/api/v1/companies', [CompanyApiController::class, 'list'], [AuthMiddleware::class]);
$router->get('/api/v1/exchange-rates', [InfoApiController::class, 'exchangeRates'], [AuthMiddleware::class]);
$router->get('/api/v1/exchange-rates/{currency}/history', [InfoApiController::class, 'exchangeRateHistory'], [AuthMiddleware::class]);
$router->post('/api/v1/weather/refresh', [WeatherApiController::class, 'refresh'], [AuthMiddleware::class]);
$router->get('/api/v1/health/drive', [InfoApiController::class, 'testDriveConnection'], [AuthMiddleware::class]);
$router->post('/api/v1/exchange-rates/refresh', [InfoApiController::class, 'refreshExchangeRates'], [AuthMiddleware::class]);
$router->get('/api/v1/cron', [\App\Controllers\ObservabilityController::class, 'runCron'], [AuthMiddleware::class]);
