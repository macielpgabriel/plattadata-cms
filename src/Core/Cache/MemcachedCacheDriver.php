<?php

declare(strict_types=1);

namespace App\Core\Cache;

use App\Core\CacheDriver;
use Throwable;

final class MemcachedCacheDriver implements CacheDriver
{
    private mixed $memcached = null;
    private string $prefix;

    public function __construct()
    {
        $this->prefix = env('CACHE_MEMCACHED_PREFIX', 'cms_cache_');
        $this->connect();
    }

    private function connect(): void
    {
        if (!extension_loaded('memcached')) {
            throw new \RuntimeException('Memcached extension is not loaded');
        }

        try {
            $this->memcached = new \Memcached();
            $servers = [
                [
                    'host' => env('CACHE_MEMCACHED_HOST', '127.0.0.1'),
                    'port' => (int) env('CACHE_MEMCACHED_PORT', '11211'),
                    'weight' => 0,
                ]
            ];
            $this->memcached->addServers($servers);

            $username = env('CACHE_MEMCACHED_USERNAME');
            $password = env('CACHE_MEMCACHED_PASSWORD');
            if ($username !== null && $password !== null) {
                $this->memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
                $this->memcached->setSaslAuthData($username, $password);
            }
        } catch (Throwable $e) {
            throw new \RuntimeException('Failed to connect to Memcached: ' . $e->getMessage());
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->memcached->set($this->prefix . $key, $value, $ttl);
    }

    public function get(string $key): mixed
    {
        $value = $this->memcached->get($this->prefix . $key);
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return null;
        }
        return $value;
    }

    public function has(string $key): bool
    {
        $this->memcached->get($this->prefix . $key);
        return $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    public function forget(string $key): bool
    {
        return $this->memcached->delete($this->prefix . $key);
    }

    public function flush(): bool
    {
        return $this->memcached->flush();
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return $this->memcached->increment($this->prefix . $key, $value);
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->memcached->decrement($this->prefix . $key, $value);
    }

    public function gc(): int
    {
        return 0;
    }
}
