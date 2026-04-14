<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class EconomicIndicatorRepository
{
    public function saveIndicator(array $indicator, string $code, string $name, string $unit = '%'): bool
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare("
            INSERT INTO economic_indicators 
            (indicator_code, indicator_name, indicator_value, indicator_unit, indicator_period, data_referencia, fetched_at)
            VALUES 
            (:code, :name, :value, :unit, :period, :reference_date, :fetched_at)
            ON DUPLICATE KEY UPDATE
                indicator_value = VALUES(indicator_value),
                indicator_period = VALUES(indicator_period),
                fetched_at = VALUES(fetched_at)
        ");

        $referenceDate = isset($indicator['data_referencia']) 
            ? date('Y-m-d', strtotime($indicator['data_referencia']))
            : date('Y-m-d');

        $value = $indicator['valor'] ?? $indicator['value'] ?? 0;
        $period = $indicator['periodo'] ?? $indicator['period'] ?? null;

        return $stmt->execute([
            ':code' => $code,
            ':name' => $name,
            ':value' => (float) $value,
            ':unit' => $unit,
            ':period' => $period,
            ':reference_date' => $referenceDate,
            ':fetched_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function saveIndicators(array $indicators): int
    {
        $saved = 0;
        foreach ($indicators as $indicator) {
            $code = $indicator['code'] ?? null;
            $name = $indicator['name'] ?? '';
            $unit = $indicator['unit'] ?? '%';
            $data = $indicator['data'] ?? [];

            if ($code && $this->saveIndicator($data, $code, $name, $unit)) {
                $saved++;
            }
        }
        return $saved;
    }

    public function getLatestIndicators(): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->query("
            SELECT indicator_code, indicator_name, indicator_value, indicator_unit, indicator_period, data_referencia
            FROM economic_indicators e1
            WHERE data_referencia = (
                SELECT MAX(data_referencia) 
                FROM economic_indicators e2 
                WHERE e2.indicator_code = e1.indicator_code
            )
            ORDER BY indicator_code
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getIndicatorHistory(string $code, int $months = 12): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare("
            SELECT indicator_code, indicator_name, indicator_value, indicator_unit, indicator_period, data_referencia
            FROM economic_indicators
            WHERE indicator_code = :code 
              AND data_referencia >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
            ORDER BY data_referencia ASC
        ");

        $stmt->execute([':code' => $code, ':months' => $months]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getLastFetchedAt(): ?string
    {
        $pdo = Database::connection();

        $stmt = $pdo->query("SELECT MAX(fetched_at) as last_fetched FROM economic_indicators");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['last_fetched'] ?? null;
    }

    public function cleanupOldIndicators(int $keepMonths = 24): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare("
            DELETE FROM economic_indicators 
            WHERE data_referencia < DATE_SUB(CURDATE(), INTERVAL :months MONTH)
        ");

        $stmt->execute([':months' => $keepMonths]);

        return $stmt->rowCount();
    }
}
