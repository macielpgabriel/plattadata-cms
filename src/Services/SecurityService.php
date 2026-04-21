<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

final class SecurityService
{
    private const LFI_PATTERNS = [
        '../', '..\\', '%2e%2e', '%252e', '...',
        '/etc/passwd', '/etc/shadow', '/etc/hosts',
        'c:\\windows', 'c:\\boot.ini',
    ];
    
    private const XXE_PATTERNS = [
        '<!DOCTYPE', '<!ENTITY', '<!ENTITY', 'SYSTEM ', 'PUBLIC ',
        'data:', 'expect:', 'php://',
    ];
    
    private const SSRF_DOMAINS = [
        '169.254.169.254', 'metadata.google.internal',
        'metadata.googleusercontent.com',
    ];

    public static function sanitizeInput(string $input): string
    {
        $input = trim($input);
        $input = preg_replace('/[\x00-\x1F\x7F]/', '', $input);
        
        return $input;
    }

    public static function checkLfi(string $input): bool
    {
        $lower = strtolower($input);
        
        foreach (self::LFI_PATTERNS as $pattern) {
            if (str_contains($lower, strtolower($pattern))) {
                Logger::warning('LFI attempt detected', [
                    'input' => $input,
                    'pattern' => $pattern,
                ]);
                return true;
            }
        }
        
        return false;
    }

    public static function checkXxe(string $input): bool
    {
        foreach (self::XXE_PATTERNS as $pattern) {
            if (str_starts_with($input, $pattern)) {
                Logger::warning('XXE attempt detected', [
                    'input' => $input,
                    'pattern' => $pattern,
                ]);
                return true;
            }
        }
        
        return false;
    }

    public static function checkSsrf(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $host = parse_url($url, PHP_URL_HOST);
        
        if (!$host) {
            return false;
        }
        
        $ip = gethostbyname($host);
        
        foreach (self::SSRF_DOMAINS as $forbidden) {
            if ($ip === $forbidden || str_contains($host, $forbidden)) {
                Logger::warning('SSRF attempt detected', [
                    'url' => $url,
                    'resolved_ip' => $ip,
                ]);
                return true;
            }
        }
        
        if (str_starts_with($ip, '10.') || 
            str_starts_with($ip, '192.168.') ||
            str_starts_with($ip, '172.16.') ||
            str_starts_with($ip, '127.') ||
            $ip === '0.0.0.0') {
            Logger::warning('Internal IP in URL', [
                'url' => $url,
                'resolved_ip' => $ip,
            ]);
            return true;
        }
        
        return false;
    }

    public static function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        
        return $filename;
    }

    public static function blockIp(string $ip, string $reason): void
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "INSERT INTO blocked_ips (ip, reason, expires_at) VALUES (:ip, :reason, DATE_ADD(NOW(), INTERVAL 60 MINUTE))"
            );
            $stmt->execute([
                'ip' => $ip,
                'reason' => $reason,
            ]);
            
            Logger::warning('IP blocked automatically', [
                'ip' => $ip,
                'reason' => $reason,
            ]);
        } catch (Throwable $e) {
            Logger::error('Failed to block IP: ' . $e->getMessage());
        }
    }

    public static function isIpBlocked(string $ip): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "SELECT 1 FROM blocked_ips WHERE ip = :ip AND (expires_at IS NULL OR expires_at > NOW())"
            );
            $stmt->execute(['ip' => $ip]);
            
            return (bool) $stmt->fetch();
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function checkRequestQuota(string $key, int $maxPerMinute = 60, int $maxPerHour = 1000): bool
    {
        $cache = \App\Core\Cache::class;
        
        $minuteKey = "quota:{$key}:min";
        $hourKey = "quota:{$key}:hour";
        
        $minuteHits = (int) $cache::get($minuteKey, 0);
        $hourHits = (int) $cache::get($hourKey, 0);
        
        if ($minuteHits >= $maxPerMinute || $hourHits >= $maxPerHour) {
            return false;
        }
        
        $cache::set($minuteKey, $minuteHits + 1, 60);
        $cache::set($hourKey, $hourHits + 1, 3600);
        
        return true;
    }
    
    public static function applyRequestProtections(): void
    {
        self::applyLfiProtection();
        self::applyXssProtection();
    }
    
    private static function applyLfiProtection(): void
    {
        foreach ($_GET as $key => $value) {
            if (is_string($value) && self::checkLfi($value)) {
                Logger::warning('LFI attempt blocked', [
                    'param' => $key,
                    'value' => $value,
                ]);
                http_response_code(400);
                exit('Bad request');
            }
        }
    }
    
    private static function applyXssProtection(): void
    {
        $xssPatterns = [
            '/<script\b/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
        ];
        
        foreach ($_POST as $key => $value) {
            if (is_string($value)) {
                foreach ($xssPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        Logger::warning('XSS attempt blocked', [
                            'param' => $key,
                            'value' => substr($value, 0, 100),
                        ]);
                        http_response_code(400);
                        exit('Bad request');
                    }
                }
            }
        }
    }
}