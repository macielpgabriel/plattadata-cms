<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

final class CacheService
{
    private static $redis = null;
    private static bool $useRedis = false;
    private static string $cacheDir;

    public static function init(): void
    {
        self::$cacheDir = base_path('storage/cache');
        
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
        
        $redisHost = env('REDIS_HOST', '');
        $redisPort = (int) env('REDIS_PORT', 6379);
        
        if (!empty($redisHost) && class_exists('Redis')) {
            try {
                self::$redis = new \Redis();
                self::$redis->connect($redisHost, $redisPort);
                self::$useRedis = true;
                Logger::info('Cache Redis conectado');
            } catch (\Exception $e) {
                self::$useRedis = false;
            }
        }
    }

    public static function get(string $key, $default = null)
    {
        if (self::$useRedis && self::$redis) {
            try {
                $value = self::$redis->get($key);
                return $value !== false ? unserialize($value) : $default;
            } catch (\Exception $e) {
                return self::fileGet($key, $default);
            }
        }
        
        return self::fileGet($key, $default);
    }

    public static function set(string $key, $value, int $ttl = 3600): bool
    {
        $serialized = serialize($value);
        
        if (self::$useRedis && self::$redis) {
            try {
                return self::$redis->setex($key, $ttl, $serialized);
            } catch (\Exception $e) {
                return self::fileSet($key, $serialized, $ttl);
            }
        }
        
        return self::fileSet($key, $serialized, $ttl);
    }

    public static function forget(string $key): bool
    {
        if (self::$useRedis && self::$redis) {
            try {
                return self::$redis->del($key) > 0;
            } catch (\Exception $e) {
                return self::fileDelete($key);
            }
        }
        
        return self::fileDelete($key);
    }

    public static function has(string $key): bool
    {
        if (self::$useRedis && self::$redis) {
            try {
                return self::$redis->exists($key) > 0;
            } catch (\Exception $e) {
                return self::fileExists($key);
            }
        }
        
        return self::fileExists($key);
    }

    public static function flush(): bool
    {
        if (self::$useRedis && self::$redis) {
            try {
                return self::$redis->flushDB();
            } catch (\Exception $e) {
                return false;
            }
        }
        
        $files = glob(self::$cacheDir . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }

    private static function fileGet(string $key, $default)
    {
        $file = self::$cacheDir . '/' . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $content = file_get_contents($file);
        $data = unserialize($content);
        
        if ($data['expires'] < time()) {
            @unlink($file);
            return $default;
        }
        
        return $data['value'];
    }

    private static function fileSet(string $key, string $value, int $ttl): bool
    {
        $file = self::$cacheDir . '/' . md5($key) . '.cache';
        $data = [
            'value' => unserialize($value),
            'expires' => time() + $ttl,
        ];
        
        return file_put_contents($file, serialize($data)) !== false;
    }

    private static function fileDelete(string $key): bool
    {
        $file = self::$cacheDir . '/' . md5($key) . '.cache';
        return @unlink($file);
    }

    private static function fileExists(string $key): bool
    {
        $file = self::$cacheDir . '/' . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return false;
        }
        
        $content = file_get_contents($file);
        $data = unserialize($content);
        
        if ($data['expires'] < time()) {
            @unlink($file);
            return false;
        }
        
        return true;
    }
}

CacheService::init();