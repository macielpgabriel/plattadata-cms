<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

final class AccessLogService
{
    public static function log(string $path, int $statusCode = 200, ?string $userId = null): void
    {
        if ($statusCode < 400) {
            return;
        }

        $ip = self::getClientIp();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $level = $statusCode >= 500 ? 'error' : 'warning';
        
        $message = sprintf(
            'ACCESS %s %s - %d | User: %s | IP: %s',
            $method,
            $path,
            $statusCode,
            $userId ?? 'guest',
            $ip
        );
        
        Logger::$level($message, [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'status' => $statusCode,
            'method' => $method,
            'path' => $path,
        ]);
    }
    
    public static function logSecurity(string $event, array $context = []): void
    {
        $ip = self::getClientIp();
        
        Logger::warning('SECURITY: ' . $event, array_merge([
            'ip' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ], $context));
    }
    
    public static function logLoginAttempt(string $email, bool $success, ?string $reason = null): void
    {
        $ip = self::getClientIp();
        
        if ($success) {
            Logger::info('Login bem-sucedido', [
                'email' => $email,
                'ip' => $ip,
            ]);
        } else {
            Logger::warning('Login falhou', [
                'email' => $email,
                'ip' => $ip,
                'reason' => $reason,
            ]);
        }
    }
    
    private static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        
        foreach ($headers as $header) {
            $value = $_SERVER[$header] ?? null;
            if ($value) {
                $ips = explode(',', $value);
                return trim($ips[0]);
            }
        }
        
        return 'unknown';
    }
}
