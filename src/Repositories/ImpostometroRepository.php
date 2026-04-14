<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ImpostometroRepository
{
    public function saveArrecadacao(array $data): bool
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            INSERT INTO impostometro_data 
            (category, item_code, item_name, value, percentage, reference_period, collected_at, created_at)
            VALUES 
            (:category, :code, :name, :value, :percentage, :period, :collected, NOW())
            ON DUPLICATE KEY UPDATE
                item_name = VALUES(item_name),
                value = VALUES(value),
                percentage = VALUES(percentage),
                collected_at = VALUES(collected_at)
        ");

        foreach ($data['categorias'] ?? [] as $categoria) {
            $stmt->execute([
                ':category' => 'tributo',
                ':code' => $categoria['codigo'] ?? 'UNK',
                ':name' => $categoria['nome'] ?? 'Desconhecido',
                ':value' => (float) ($categoria['valor'] ?? 0),
                ':percentage' => (float) ($categoria['percentual'] ?? 0),
                ':period' => $data['periodo'] ?? date('Y'),
                ':collected' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    public function getLatestArrecadacao(): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->query("
            SELECT item_code, item_name, value, percentage, reference_period, collected_at
            FROM impostometro_data
            WHERE category = 'tributo'
              AND collected_at = (
                  SELECT MAX(collected_at) 
                  FROM impostometro_data 
                  WHERE category = 'tributo'
              )
            ORDER BY percentage DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getHistorico(int $months = 12): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            SELECT DISTINCT DATE_FORMAT(collected_at, '%Y-%m') as mes,
                   SUM(value) as total_mes
            FROM impostometro_data
            WHERE category = 'tributo'
              AND collected_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
            GROUP BY mes
            ORDER BY mes ASC
        ");
        
        $stmt->execute([':months' => $months]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getTotalArrecadado(): ?array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->query("
            SELECT SUM(value) as total, MAX(collected_at) as ultima_att
            FROM impostometro_data
            WHERE category = 'tributo'
              AND collected_at = (
                  SELECT MAX(collected_at) 
                  FROM impostometro_data 
                  WHERE category = 'tributo'
              )
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveContadores(array $contadores): bool
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            INSERT INTO impostometro_data 
            (category, item_code, item_name, value, reference_period, collected_at, created_at)
            VALUES 
            ('contador', :code, :name, :value, :period, :collected, NOW())
            ON DUPLICATE KEY UPDATE
                item_name = VALUES(item_name),
                value = VALUES(value),
                collected_at = VALUES(collected_at)
        ");

        foreach ($contadores as $code => $value) {
            $stmt->execute([
                ':code' => $code,
                ':name' => $this->getContadorName($code),
                ':value' => (float) $value,
                ':period' => date('Y-m'),
                ':collected' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    private function getContadorName(string $code): string
    {
        $names = [
            'total_brasil' => 'Total Arrecadado Brasil',
            'por_segundo' => 'Por Segundo',
            'por_minuto' => 'Por Minuto',
            'por_hora' => 'Por Hora',
            'por_dia' => 'Por Dia',
            'por_pessoa' => 'Por Pessoa',
        ];

        return $names[$code] ?? $code;
    }

    public function getContadores(): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->query("
            SELECT item_code, item_name, value, reference_period, collected_at
            FROM impostometro_data
            WHERE category = 'contador'
              AND reference_period = (
                  SELECT MAX(reference_period) 
                  FROM impostometro_data 
                  WHERE category = 'contador'
              )
        ");
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        $contadores = [];
        foreach ($results as $row) {
            $contadores[$row['item_code']] = [
                'nome' => $row['item_name'],
                'valor' => (float) $row['value'],
            ];
        }
        
        return $contadores;
    }
}
