<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Services\RateLimiterService;
use App\Services\CnpjService;
use Throwable;

abstract class BaseApiController
{
    protected array $response = [];
    protected int $statusCode = 200;

    protected function json(array $data, ?int $statusCode = null): never
    {
        if ($statusCode !== null) {
            $this->statusCode = $statusCode;
        }

        http_response_code($this->statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
        header('X-Content-Type-Options: nosniff');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    protected function success(array $data = [], string $message = 'OK'): never
    {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c'),
        ]);
    }

    protected function error(string $message, int $statusCode = 400, ?array $errors = null): never
    {
        $this->statusCode = $statusCode;
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $statusCode,
            ],
            'timestamp' => date('c'),
        ];

        if ($errors !== null) {
            $response['error']['details'] = $errors;
        }

        $this->json($response, $statusCode);
    }

    protected function paginate(array $data, int $total, int $page, int $perPage): never
    {
        $this->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
                'has_more' => $page * $perPage < $total,
            ],
            'timestamp' => date('c'),
        ]);
    }

    protected function validateApiKey(): bool
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        $validKey = env('API_KEY', '');

        if ($validKey === '') {
            return false;
        }

        return hash_equals($validKey, $apiKey);
    }

    protected function checkRateLimit(string $action, int $limit, int $windowSeconds = 60): void
    {
        $scope = "api_{$action}_" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $limiter = new RateLimiterService();
        $result = $limiter->hit($action, $scope, $limit, $windowSeconds);

        if (empty($result['success'])) {
            $this->error(
                'Rate limit exceeded. Please wait before making more requests.',
                429,
                [
                    'limit' => $limit,
                    'window' => $windowSeconds,
                'retry_after' => $result['retry_after'] ?? $windowSeconds,
                ]
            );
        }
    }

    protected function getIntParam(string $name, int $default = 0): int
    {
        return max(0, (int) ($_GET[$name] ?? $default));
    }

    protected function getStringParam(string $name, string $default = ''): string
    {
        return trim((string) ($_GET[$name] ?? $default));
    }

    protected function getBoolParam(string $name, bool $default = false): bool
    {
        $value = $_GET[$name] ?? null;
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
