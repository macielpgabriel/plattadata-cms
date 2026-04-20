<?php

declare(strict_types=1);

namespace App\Core;

final class Cache
{
    private static string $cachePath = '';
    private static array $memoryCache = [];
    private static string $hmacKey = '';

    private static function init(): void
    {
        if (self::$cachePath === '') {
            self::$cachePath = base_path('storage/cache');
            if (!is_dir(self::$cachePath)) {
                mkdir(self::$cachePath, 0755, true);
            }
            $key = env('APP_KEY', 'default-cache-key-change-in-production');
            self::$hmacKey = hash('sha256', $key, true);
        }
    }

    private static function sign(string $data): string
    {
        return hash_hmac('sha256', $data, self::$hmacKey);
    }

    private static function verify(string $data, string $signature): bool
    {
        return hash_equals(self::sign($data), $signature);
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        self::init();
        $file = self::getFilePath($key);
        
        $content = json_encode($value, JSON_THROW_ON_ERROR);
        $expiresAt = time() + $ttl;
        
        $payload = json_encode([
            'expires_at' => $expiresAt,
            'content' => $value
        ], JSON_THROW_ON_ERROR);
        
        $signature = self::sign($payload);
        
        $data = json_encode([
            'sig' => $signature,
            'data' => $payload
        ], JSON_THROW_ON_ERROR);

        self::$memoryCache[$key] = $value;

        return file_put_contents($file, $data) !== false;
    }

    public static function get(string $key): mixed
    {
        if (isset(self::$memoryCache[$key])) {
            return self::$memoryCache[$key];
        }

        self::init();
        $file = self::getFilePath($key);

        if (!is_file($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $cached = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        
        if (!isset($cached['sig'], $cached['data'])) {
            @unlink($file);
            return null;
        }
        
        if (!self::verify($cached['data'], $cached['sig'])) {
            @unlink($file);
            return null;
        }
        
        $data = json_decode($cached['data'], true, 512, JSON_THROW_ON_ERROR);
        
        if (!$data || time() > $data['expires_at']) {
            @unlink($file);
            unset(self::$memoryCache[$key]);
            return null;
        }

        self::$memoryCache[$key] = $data['content'];
        return $data['content'];
    }

    public static function forget(string $key): bool
    {
        self::init();
        unset(self::$memoryCache[$key]);
        $file = self::getFilePath($key);
        return is_file($file) ? @unlink($file) : true;
    }

    private static function getFilePath(string $key): string
    {
        return self::$cachePath . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    public static function gc(): int
    {
        self::init();
        $count = 0;
        foreach (glob(self::$cachePath . DIRECTORY_SEPARATOR . '*.cache') as $file) {
            $content = file_get_contents($file);
            if ($content !== false) {
                try {
                    $cached = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                    if (!isset($cached['sig'], $cached['data'])) {
                        @unlink($file);
                        $count++;
                    } else {
                        $data = json_decode($cached['data'], true, 512, JSON_THROW_ON_ERROR);
                        if (!$data || time() > ($data['expires_at'] ?? 0)) {
                            @unlink($file);
                            $count++;
                        }
                    }
                } catch (\JsonException $e) {
                    @unlink($file);
                    $count++;
                }
            }
        }
        return $count;
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }
}
