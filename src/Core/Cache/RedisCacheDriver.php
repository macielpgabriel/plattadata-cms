<?php

declare(strict_types=1);

namespace App\Core\Cache;

use App\Core\CacheDriver;
use Throwable;

final class RedisCacheDriver implements CacheDriver
{
    private mixed $redis = null;
    private string $prefix;

    public function __construct()
    {
        $this->prefix = env('CACHE_REDIS_PREFIX', 'cms_cache_');
        $this->connect();
    }

    private function connect(): void
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension is not loaded');
        }

        try {
            $this->redis = new \Redis();
            $host = env('CACHE_REDIS_HOST', '127.0.0.1');
            $port = (int) env('CACHE_REDIS_PORT', '6379');
            $timeout = (float) env('CACHE_REDIS_TIMEOUT', '0');
            $password = env('CACHE_REDIS_PASSWORD');

            if ($timeout > 0) {
                $this->redis->connect($host, $port, $timeout);
            } else {
                $this->redis->connect($host, $port);
            }

            if ($password !== null && $password !== '') {
                $this->redis->auth($password);
            }

            $database = (int) env('CACHE_REDIS_DATABASE', '0');
            $this->redis->select($database);
        } catch (Throwable $e) {
            throw new \RuntimeException('Failed to connect to Redis: ' . $e->getMessage());
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        try {
            $serialized = json_encode($value, JSON_THROW_ON_ERROR);
            return $this->redis->setex($this->prefix . $key, $ttl, $serialized);
        } catch (\JsonException $e) {
            return false;
        }
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($this->prefix . $key);
        if ($value === false) {
            return null;
        }
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($this->prefix . $key);
    }

    public function forget(string $key): bool
    {
        return (bool) $this->redis->del($this->prefix . $key);
    }

    public function flush(): bool
    {
        return $this->redis->flushDB();
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return $this->redis->incrBy($this->prefix . $key, $value);
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->redis->decrBy($this->prefix . $key, $value);
    }

    public function gc(): int
    {
        return 0;
    }
}
