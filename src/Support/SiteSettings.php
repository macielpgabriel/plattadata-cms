<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use Throwable;

final class SiteSettings
{
    private static bool $loaded = false;
    private static array $cache = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();

        if (!array_key_exists($key, self::$cache)) {
            return $default;
        }

        $value = self::$cache[$key];
        return is_string($value) ? $value : $default;
    }

    public static function all(): array
    {
        self::load();
        return self::$cache;
    }

    public static function setCache(array $data): void
    {
        self::$cache = $data;
        self::$loaded = true;
    }

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        try {
            $stmt = Database::connection()->query('SELECT key_name, value_text FROM site_settings');
            $rows = $stmt->fetchAll();
            $map = [];
            foreach ($rows as $row) {
                if (!empty($row['key_name'])) {
                    $map[(string) $row['key_name']] = (string) ($row['value_text'] ?? '');
                }
            }
            self::$cache = $map;
        } catch (Throwable) {
            self::$cache = [];
        }
    }
}
