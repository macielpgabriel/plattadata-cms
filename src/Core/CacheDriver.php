<?php

declare(strict_types=1);

namespace App\Core;

interface CacheDriver
{
    public function set(string $key, mixed $value, int $ttl = 3600): bool;
    public function get(string $key): mixed;
    public function has(string $key): bool;
    public function forget(string $key): bool;
    public function flush(): bool;
    public function increment(string $key, int $value = 1): int|false;
    public function decrement(string $key, int $value = 1): int|false;
    public function gc(): int;
}
