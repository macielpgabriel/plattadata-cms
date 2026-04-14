<?php

declare(strict_types=1);

namespace App\Core\Cache;

use App\Core\CacheDriver;

final class FileCacheDriver implements CacheDriver
{
    private string $cachePath;

    public function __construct(?string $path = null)
    {
        $this->cachePath = $path ?? base_path('storage/cache');
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);
        $data = [
            'expires_at' => time() + $ttl,
            'content' => $value
        ];

        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
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

        $data = unserialize($content);
        if (!$data || time() > $data['expires_at']) {
            @unlink($file);
            return null;
        }

        return $data['content'];
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
                $data = unserialize($content);
                if (!$data || time() > ($data['expires_at'] ?? 0)) {
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
