<?php

declare(strict_types=1);

namespace App\Services;

final class RateLimiterService
{
    private const CACHE_PREFIX = 'rate_limit:';
    
    public static function hit(string $action, string $scope, int $maxAttempts, int $windowSeconds): array
    {
        $cacheKey = self::CACHE_PREFIX . $scope . ':' . $action;
        $attempts = CacheService::get($cacheKey, 0);
        
        $now = time();
        $windowStart = $now - ($now % $windowSeconds);
        $storedWindow = CacheService::get($cacheKey . ':window', 0);
        
        if ($storedWindow !== $windowStart) {
            $attempts = 0;
            $storedWindow = $windowStart;
            CacheService::set($cacheKey . ':window', $storedWindow, $windowSeconds + 10);
        }
        
        $attempts++;
        CacheService::set($cacheKey, $attempts, $windowSeconds + 10);
        
        $remaining = max(0, $maxAttempts - $attempts);
        $retryAfter = $windowSeconds - ($now - $windowStart);
        
        return [
            'success' => $attempts <= $maxAttempts,
            'limit' => $maxAttempts,
            'remaining' => $remaining,
            'retry_after' => $attempts > $maxAttempts ? $retryAfter : 0,
        ];
    }

    public static function check(string $action, string $scope, int $maxAttempts, int $windowSeconds): bool
    {
        $result = self::hit($action, $scope, $maxAttempts, $windowSeconds);
        return $result['success'];
    }

    public static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }
        
        return $ip;
    }

    public static function getClientKey(): string
    {
        return 'ip:' . self::getClientIp();
    }

    public static function getUserKey(?int $userId = null): string
    {
        if ($userId) {
            return 'user:' . $userId;
        }
        return self::getClientKey();
    }
}