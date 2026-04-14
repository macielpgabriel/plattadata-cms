<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class VehicleTypeRepository
{
    public function saveTypes(int $ibgeCode, array $types): bool
    {
        if (empty($types)) {
            return false;
        }

        $pdo = Database::connection();
        
        $isEstimated = isset($types['_estimated']) && $types['_estimated'] === true;
        unset($types['_estimated']);
        
        $stmt = $pdo->prepare(
            'INSERT INTO municipality_vehicle_types (ibge_code, vehicle_type, vehicle_count, year, fetched_at)
             VALUES (:ibge_code, :vehicle_type, :vehicle_count, :year, NOW())
             ON DUPLICATE KEY UPDATE
                vehicle_count = VALUES(vehicle_count),
                year = VALUES(year),
                fetched_at = NOW()'
        );

        $saved = 0;
        foreach ($types as $type => $count) {
            $year = date('Y');
            if (is_array($count)) {
                $year = $count['year'] ?? date('Y');
                $count = $count['count'] ?? 0;
            }
            
            if ($stmt->execute([
                'ibge_code' => $ibgeCode,
                'vehicle_type' => $type,
                'vehicle_count' => (int) $count,
                'year' => $year,
            ])) {
                $saved++;
            }
        }

        if ($saved > 0 && $isEstimated) {
            $pdo->prepare(
                'INSERT INTO municipality_vehicle_types (ibge_code, vehicle_type, vehicle_count, year, fetched_at)
                 VALUES (:ibge_code, :type, :count, :year, NOW())
                 ON DUPLICATE KEY UPDATE vehicle_count = :count2, year = :year2'
            )->execute([
                'ibge_code' => $ibgeCode,
                'type' => '_estimated',
                'count' => 1,
                'year' => date('Y'),
                'count2' => 1,
                'year2' => date('Y'),
            ]);
        }

        return $saved > 0;
    }

    public function getTypesByIbgeCode(int $ibgeCode): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT vehicle_type, vehicle_count, year
             FROM municipality_vehicle_types
             WHERE ibge_code = :ibge_code
             ORDER BY vehicle_count DESC'
        );
        $stmt->execute(['ibge_code' => $ibgeCode]);
        
        $result = [];
        $isEstimated = false;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['vehicle_type'] === '_estimated') {
                $isEstimated = (int) $row['vehicle_count'] === 1;
                continue;
            }
            $result[$row['vehicle_type']] = [
                'count' => (int) $row['vehicle_count'],
                'year' => (int) $row['year'],
            ];
        }
        
        return [
            'types' => $result,
            'is_estimated' => $isEstimated,
        ];
    }

    public function getTotalByIbgeCode(int $ibgeCode): ?int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT SUM(vehicle_count) as total
             FROM municipality_vehicle_types
             WHERE ibge_code = :ibge_code'
        );
        $stmt->execute(['ibge_code' => $ibgeCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int) $result['total'] : null;
    }

    public function getStateVehicleTypes(string $uf): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare(
            'SELECT v.vehicle_type, SUM(v.vehicle_count) as total_count
             FROM municipality_vehicle_types v
             INNER JOIN municipalities m ON v.ibge_code = m.ibge_code
             WHERE m.state_uf = :uf AND v.vehicle_type != "_estimated"
             GROUP BY v.vehicle_type
             ORDER BY total_count DESC'
        );
        $stmt->execute(['uf' => $uf]);
        
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['vehicle_type']] = (int) $row['total_count'];
        }
        
        return $result;
    }
}
