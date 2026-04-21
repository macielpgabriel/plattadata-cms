<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Database;
use App\Services\IpBlocklistService;
use App\Services\EmailService;
use Throwable;

final class Cron
{
    private const TASK_HOOKS = [
        'cleanup_expired_sessions' => 86400,
        'cleanup_expired_tokens' => 3600,
        'cleanup_expired_locks' => 1800,
        'cleanup_ip_blocklist' => 3600,
        'rotate_session_keys' => 86400,
        'security_scan' => 3600,
    ];

    public static function run(string $hook): array
    {
        $startTime = microtime(true);
        $result = ['hook' => $hook, 'success' => true, 'output' => []];
        
        try {
$result['output'] = match ($hook) {
                'cleanup_expired_sessions' => self::cleanupExpiredSessions(),
                'cleanup_expired_tokens' => self::cleanupExpiredTokens(),
                'cleanup_expired_locks' => self::cleanupExpiredLocks(),
                'cleanup_ip_blocklist' => self::cleanupIpBlocklist(),
                'rotate_session_keys' => self::rotateSessionKeys(),
                'security_scan' => self::runSecurityScan(),
            };
        } catch (Throwable $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
            Logger::error("Cron error [$hook]: " . $e->getMessage());
        }
        
        $result['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        
        return $result;
    }

    public static function runAll(): array
    {
        $results = [];
        $timestamp = time();
        
        foreach (self::TASK_HOOKS as $hook => $interval) {
            $lastRun = (int) config("cron.last_run_{$hook}", 0);
            
            if ($timestamp - $lastRun >= $interval) {
                $results[$hook] = self::run($hook);
                config("cron.last_run_{$hook}", $timestamp);
            }
        }
        
        return $results;
    }

    public static function runDueTasks(): array
    {
        $results = [];
        $timestamp = time();
        
        foreach (self::TASK_HOOKS as $hook => $interval) {
            $lastRun = self::getLastRun($hook);
            
            if ($timestamp - $lastRun >= $interval) {
                $results[$hook] = self::run($hook);
                self::setLastRun($hook, $timestamp);
            }
        }
        
        return $results;
    }

    private static function getLastRun(string $hook): int
    {
        $key = "cron_last_run_{$hook}";
        return (int) Cache::get($key, 0);
    }

    private static function setLastRun(string $hook, int $timestamp): void
    {
        $key = "cron_last_run_{$hook}";
        Cache::set($key, $timestamp, 86400);
    }

    private static function cleanupExpiredSessions(): array
    {
        $affected = 0;
        
        try {
            $db = Database::connection();
            
            $stmt = $db->prepare("DELETE FROM sessions WHERE expires_at < NOW()");
            $stmt->execute();
            $affected = $stmt->rowCount();
        } catch (Throwable $e) {
            Logger::error('Cron cleanup_expired_sessions: ' . $e->getMessage());
        }
        
        return ["Deleted $affected expired sessions"];
    }

    private static function cleanupExpiredTokens(): array
    {
        $affected = 0;
        
        try {
            $db = Database::connection();
            
            $stmt = $db->prepare("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");
            $stmt->execute();
            $affected = $stmt->rowCount();
            
            $stmt = $db->prepare("DELETE FROM email_verification_tokens WHERE expires_at < NOW() OR verified_at IS NOT NULL");
            $stmt->execute();
            $affected += $stmt->rowCount();
        } catch (Throwable $e) {
            Logger::error('Cron cleanup_expired_tokens: ' . $e->getMessage());
        }
        
        return ["Deleted $affected expired tokens"];
    }

    private static function cleanupExpiredLocks(): array
    {
        try {
            Database::connection()->exec("DELETE FROM users WHERE locked_until IS NOT NULL AND locked_until < NOW()");
        } catch (Throwable $e) {
            Logger::error('Cron cleanup_expired_locks: ' . $e->getMessage());
        }
        
        return ['Cleaned up expired account locks'];
    }

    private static function cleanupIpBlocklist(): int
    {
        return IpBlocklistService::cleanup();
    }

    private static function rotateSessionKeys(): array
    {
        $rotated = 0;
        
        try {
            $configPath = base_path('.env');
            if (is_writable($configPath)) {
                $newKey = bin2hex(random_bytes(32));
                $newKeyHash = 'base64:' . base64_encode(hash('sha256', $newKey, true));
                
                $env = file_get_contents($configPath);
                $env = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$newKeyHash}", $env);
                file_put_contents($configPath, $env);
                
                $rotated = 1;
                Logger::info('APP_KEY rotated via cron');
            }
        } catch (Throwable $e) {
            Logger::error('Cron rotate_session_keys: ' . $e->getMessage());
        }
        
        return ["Rotated $rotated keys"];
    }

    private static function runSecurityScan(): array
    {
        $issues = [];
        
        try {
            $db = Database::connection();
            
            $stmt = $db->query("SELECT COUNT(*) as cnt FROM users WHERE is_active = 1 AND failed_login_attempts >= 5");
            $lockedUsers = $stmt->fetch();
            if ($lockedUsers && $lockedUsers['cnt'] > 0) {
                $issues[] = "{$lockedUsers['cnt']} usuarios com muitas tentativas falhas";
            }
            
            $stmt = $db->query("SELECT COUNT(*) as cnt FROM blocked_ips WHERE expires_at IS NULL OR expires_at > NOW()");
            $blockedIps = $stmt->fetch();
            if ($blockedIps && $blockedIps['cnt'] > 100) {
                $issues[] = "{$blockedIps['cnt']} IPs bloqueados (possivel ataque)";
            }
            
            if (empty($issues)) {
                Logger::info('Security scan: no issues found');
            } else {
                Logger::warning('Security scan: ' . implode(', ', $issues));
            }
        } catch (Throwable $e) {
            Logger::error('Security scan: ' . $e->getMessage());
        }
        
        return $issues ?: ['Scan limpo'];
    }

    public static function schedules(): array
    {
        return self::TASK_HOOKS;
    }
}