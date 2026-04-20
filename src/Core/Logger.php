<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Logger Centralizado para o CMS.
 * Grava todos os logs em um único arquivo para facilitar a depuração.
 */
final class Logger
{
    private static string $logPath = '';
    private static string $logFile = '';

    public static function init(): void
    {
        if (self::$logPath === '') {
            self::$logPath = base_path('storage/logs');
            if (!is_dir(self::$logPath)) {
                @mkdir(self::$logPath, 0775, true);
            }
            self::$logFile = self::$logPath . '/cms.log';
        }
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }

    private static function log(string $level, string $message, array $context): void
    {
        self::init();
        
        $date = date('Y-m-d H:i:s');
        
        $entry = json_encode([
            'timestamp' => $date,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'request_id' => $_SERVER['REQUEST_ID'] ?? uniqid('req_', true),
            'remote_ip' => self::getClientIp(),
            'user_id' => self::getUserId(),
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        @file_put_contents(self::$logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function getClientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    private static function getUserId(): ?int
    {
        if (class_exists('\App\Core\Auth')) {
            try {
                $user = \App\Core\Auth::user();
                return $user['id'] ?? null;
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }

    public static function getRecentLogs(int $lines = 100, ?string $level = null): array
    {
        self::init();
        
        if (!is_file(self::$logFile)) {
            return [];
        }

        $logs = [];
        $handle = fopen(self::$logFile, 'r');
        
        if (!$handle) {
            return [];
        }
        
        while (($line = fgets($handle)) !== false) {
            $entry = json_decode(trim($line), true);
            if (!$entry) {
                continue;
            }
            
            if ($level && ($entry['level'] ?? '') !== $level) {
                continue;
            }
            
            $logs[] = $entry;
        }
        fclose($handle);
        
        usort($logs, static fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
        
        return array_slice($logs, 0, $lines);
    }

    public static function getSecurityEvents(): array
    {
        self::init();
        
        if (!is_file(self::$logFile)) {
            return [];
        }

        $events = [];
        $handle = fopen(self::$logFile, 'r');
        
        if (!$handle) {
            return [];
        }
        
        while (($line = fgets($handle)) !== false) {
            $entry = json_decode(trim($line), true);
            if (!$entry) {
                continue;
            }
            
            $message = $entry['message'] ?? '';
            $context = $entry['context'] ?? [];
            
            if (
                stripos($message, 'SECURITY') !== false ||
                stripos($message, 'ACCESS') !== false ||
                stripos($message, 'Login') !== false ||
                stripos($message, 'bloqueado') !== false ||
                stripos($message, 'rate limit') !== false ||
                stripos($message, 'PERMISSION') !== false ||
                ($entry['level'] ?? '') === 'WARNING' ||
                ($entry['level'] ?? '') === 'ERROR'
            ) {
                $events[] = $entry;
            }
        }
        fclose($handle);
        
        usort($events, static fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
        
        return array_slice($events, 0, 50);
    }

    public static function getStats(): array
    {
        self::init();
        
        if (!is_file(self::$logFile)) {
            return ['total' => 0, 'by_level' => [], 'last_entry' => null];
        }

        $stats = [
            'total' => 0,
            'by_level' => ['INFO' => 0, 'WARNING' => 0, 'ERROR' => 0, 'DEBUG' => 0],
            'last_entry' => null,
        ];

        $handle = fopen(self::$logFile, 'r');
        
        if (!$handle) {
            return $stats;
        }
        
        while (($line = fgets($handle)) !== false) {
            $entry = json_decode(trim($line), true);
            if (!$entry) {
                continue;
            }
            
            $stats['total']++;
            $level = $entry['level'] ?? 'INFO';
            if (isset($stats['by_level'][$level])) {
                $stats['by_level'][$level]++;
            }
            $stats['last_entry'] = $entry;
        }
        fclose($handle);
        
        return $stats;
    }

    public static function clear(): bool
    {
        self::init();
        return @file_put_contents(self::$logFile, '') !== false;
    }

    public static function autoCleanup(): array
    {
        $results = [
            'log_cleared' => false,
            'cache_files_removed' => 0,
            'log_size_before' => 0,
            'log_size_after' => 0,
        ];

        self::init();

        if (is_file(self::$logFile)) {
            $results['log_size_before'] = filesize(self::$logFile);
            
            $maxSize = 10 * 1024 * 1024;
            if (filesize(self::$logFile) > $maxSize) {
                $results['log_cleared'] = self::rotateLog();
                $results['log_size_after'] = is_file(self::$logFile) ? filesize(self::$logFile) : 0;
            }
        }

        $cachePath = base_path('storage/cache');
        if (is_dir($cachePath)) {
            $results['cache_files_removed'] = self::cleanupCache($cachePath);
        }

        return $results;
    }

    private static function rotateLog(): bool
    {
        if (!is_file(self::$logFile)) {
            return false;
        }

        $backupFile = self::$logPath . '/cms-' . date('Y-m-d-His') . '.log';
        return rename(self::$logFile, $backupFile);
    }

    private static function cleanupCache(string $cachePath): int
    {
        $count = 0;
        $now = time();

        foreach (glob($cachePath . '/*.cache') as $file) {
            try {
                $content = file_get_contents($file);
                if ($content === false) continue;
                
                $cached = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                if (!isset($cached['data'])) {
                    @unlink($file);
                    $count++;
                } else {
                    $data = json_decode($cached['data'], true, 512, JSON_THROW_ON_ERROR);
                    if (!$data || $now > ($data['expires_at'] ?? 0)) {
                        @unlink($file);
                        $count++;
                    }
                }
            } catch (\JsonException $e) {
                @unlink($file);
                $count++;
            }
        }

        return $count;
    }

    public static function cleanupOldLogs(int $daysToKeep = 7): int
    {
        self::init();
        $count = 0;
        $cutoff = time() - ($daysToKeep * 86400);

        foreach (glob(self::$logPath . '/cms-*.log') as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
