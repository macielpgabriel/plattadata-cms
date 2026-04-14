<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class SiteSettingRepository
{
    public function allAssoc(): array
    {
        $stmt = Database::connection()->query('SELECT key_name, value_text FROM site_settings');
        $rows = $stmt->fetchAll();
        $data = [];

        foreach ($rows as $row) {
            if (!empty($row['key_name'])) {
                $data[(string) $row['key_name']] = (string) ($row['value_text'] ?? '');
            }
        }

        return $data;
    }

    public function upsertMany(array $settings): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO site_settings (key_name, value_text, updated_at) VALUES (:key_name, :value_text, NOW()) ON DUPLICATE KEY UPDATE value_text = VALUES(value_text), updated_at = NOW()'
        );

        foreach ($settings as $key => $value) {
            $stmt->execute([
                'key_name' => (string) $key,
                'value_text' => (string) $value,
            ]);
        }
    }
}
