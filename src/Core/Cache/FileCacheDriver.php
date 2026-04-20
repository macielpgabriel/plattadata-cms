<?php

declare(strict_types=1);

namespace App\Core\Cache;

use App\Core\CacheDriver;

final class FileCacheDriver implements CacheDriver
{
    private string $cachePath;
    private string $hmacKey;

    public function __construct(?string $path = null)
    {
        $this->cachePath = $path ?? base_path('storage/cache');
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
        $key = env('APP_KEY', 'default-cache-key-change-in-production');
        $this->hmacKey = hash('sha256', $key, true);
    }

    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->hmacKey);
    }

    private function verify(string $data, string $signature): bool
    {
        return hash_equals($this->sign($data), $signature);
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);
        
        $payload = json_encode([
            'expires_at' => time() + $ttl,
            'content' => $value
        ], JSON_THROW_ON_ERROR);
        
        $signature = $this->sign($payload);
        
        $data = json_encode([
            'sig' => $signature,
            'data' => $payload
        ], JSON_THROW_ON_ERROR);

        return file_put_contents($file, $data, LOCK_EX) !== false;
    }

    public function get(string $key): mixed
    {
        $file = $this->getFilePath($key);

        if (!is_file($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        try {
            $cached = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
            if (!isset($cached['sig'], $cached['data'])) {
                @unlink($file);
                return null;
            }
            
            if (!$this->verify($cached['data'], $cached['sig'])) {
                @unlink($file);
                return null;
            }
            
            $data = json_decode($cached['data'], true, 512, JSON_THROW_ON_ERROR);
            
            if (!$data || time() > $data['expires_at']) {
                @unlink($file);
                return null;
            }

            return $data['content'];
        } catch (\JsonException $e) {
            @unlink($file);
            return null;
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function forget(string $key): bool
    {
        $file = $this->getFilePath($key);
        return is_file($file) ? @unlink($file) : true;
    }

    public function flush(): bool
    {
        $count = 0;
        foreach (glob($this->cachePath . DIRECTORY_SEPARATOR . '*.cache') as $file) {
            @unlink($file);
            $count++;
        }
        return $count > 0;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $current = (int) $this->get($key);
        $new = $current + $value;
        if (!$this->set($key, $new, 86400)) {
            return false;
        }
        return $new;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    public function gc(): int
    {
        $count = 0;
        foreach (glob($this->cachePath . DIRECTORY_SEPARATOR . '*.cache') as $file) {
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

    private function getFilePath(string $key): string
    {
        return $this->cachePath . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }
}
