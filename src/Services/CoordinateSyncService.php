<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

final class CoordinateSyncService
{
    private const BATCH_SIZE = 50;
    private const REQUEST_DELAY_MS = 1500;
    private const MAX_RETRIES = 2;

    public function syncMissingCoordinates(int $limit = 100): array
    {
        $db = Database::connection();
        
        $stmt = $db->query("SHOW COLUMNS FROM municipalities LIKE 'latitude'");
        $hasLatLng = $stmt->fetch() !== false;
        
        if (!$hasLatLng) {
            return ['success' => 0, 'errors' => 0, 'message' => 'Tabela municipalities sem latitude'];
        }
        
        $stmt = $db->prepare("
            SELECT ibge_code, name, state_code 
            FROM municipalities 
            WHERE (latitude IS NULL OR longitude IS NULL)
            ORDER BY RAND()
            LIMIT :limit
        ");
        $stmt->execute(['limit' => $limit]);
        $municipalities = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($municipalities)) {
            return ['success' => 0, 'errors' => 0, 'message' => 'Nenhum municipio sem coordenadas'];
        }
        
        $success = 0;
        $errors = 0;
        
        foreach ($municipalities as $muni) {
            $coords = $this->fetchCoordinates($muni['name'], $muni['state_code']);
            
            if ($coords) {
                $this->updateCoordinates($db, $muni['ibge_code'], $coords['lat'], $coords['lng']);
                $success++;
            } else {
                $errors++;
            }
            
            usleep(self::REQUEST_DELAY_MS * 1000);
        }
        
        return [
            'success' => $success,
            'errors' => $errors,
            'total' => count($municipalities)
        ];
    }

    private function fetchCoordinates(string $city, string $state): ?array
    {
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'city' => $city,
            'state' => $state,
            'country' => 'Brazil',
            'format' => 'json',
            'limit' => 1
        ]);

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: Plattadata-CMS/1.0 (contact@plattadata.com)\r\n"
            ]
        ]);

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            try {
                $response = @file_get_contents($url, false, $context);
                
                if (!$response) {
                    continue;
                }
                
                $data = json_decode($response, true);
                
                if (empty($data[0]['lat']) || empty($data[0]['lon'])) {
                    continue;
                }
                
                return [
                    'lat' => (float) $data[0]['lat'],
                    'lng' => (float) $data[0]['lon']
                ];
            } catch (\Throwable $e) {
                Logger::error("CoordinateSync fetch error: " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    private function updateCoordinates(\PDO $db, int $ibgeCode, float $lat, float $lng): void
    {
        $stmt = $db->prepare("
            UPDATE municipalities 
            SET latitude = :lat, longitude = :lng, updated_at = NOW() 
            WHERE ibge_code = :ibge
        ");
        
        $stmt->execute([
            'lat' => $lat,
            'lng' => $lng,
            'ibge' => $ibgeCode
        ]);
    }

    public function getStats(): array
    {
        $db = Database::connection();
        
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 ELSE 0 END) as with_coords,
                SUM(CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 ELSE 0 END) as without_coords
            FROM municipalities
        ");
        
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return [
            'total' => (int) ($row['total'] ?? 0),
            'with_coordinates' => (int) ($row['with_coords'] ?? 0),
            'without_coordinates' => (int) ($row['without_coords'] ?? 0)
        ];
    }
}