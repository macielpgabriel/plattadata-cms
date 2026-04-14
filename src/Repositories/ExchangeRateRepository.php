<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ExchangeRateRepository
{
    public function saveRate(array $rate, string $currencyCode, string $currencyName): bool
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            INSERT INTO exchange_rates 
            (currency_code, currency_name, paridade_compra, paridade_venda, cotacao_compra, cotacao_venda, tipo_boletim, data_cotacao, fetched_at)
            VALUES 
            (:code, :name, :paridade_compra, :paridade_venda, :cotacao_compra, :cotacao_venda, :tipo_boletim, :data_cotacao, :fetched_at)
            ON DUPLICATE KEY UPDATE
                paridade_compra = VALUES(paridade_compra),
                paridade_venda = VALUES(paridade_venda),
                cotacao_compra = VALUES(cotacao_compra),
                cotacao_venda = VALUES(cotacao_venda),
                tipo_boletim = VALUES(tipo_boletim),
                fetched_at = VALUES(fetched_at)
        ");

        $dataCotacao = isset($rate['dataCotacao']) ? date('Y-m-d', strtotime($rate['dataCotacao'])) : date('Y-m-d');
        $fetchedAt = date('Y-m-d H:i:s');

        return $stmt->execute([
            ':code' => $currencyCode,
            ':name' => $currencyName,
            ':paridade_compra' => $rate['paridadeCompra'] ?? null,
            ':paridade_venda' => $rate['paridadeVenda'] ?? null,
            ':cotacao_compra' => (float) ($rate['cotacaoCompra'] ?? 0),
            ':cotacao_venda' => (float) ($rate['cotacaoVenda'] ?? 0),
            ':tipo_boletim' => $rate['tipoBoletim'] ?? null,
            ':data_cotacao' => $dataCotacao,
            ':fetched_at' => $fetchedAt,
        ]);
    }

    public function getLatestRates(): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->query("
            SELECT currency_code, currency_name, paridade_compra, paridade_venda, 
                   cotacao_compra, cotacao_venda, tipo_boletim, data_cotacao, fetched_at
            FROM exchange_rates
            WHERE data_cotacao = (SELECT MAX(data_cotacao) FROM exchange_rates)
            ORDER BY currency_code
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRateHistory(string $currencyCode, int $days = 30): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            SELECT currency_code, currency_name, cotacao_compra, cotacao_venda, data_cotacao
            FROM exchange_rates
            WHERE currency_code = :code 
              AND data_cotacao >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            ORDER BY data_cotacao ASC
        ");
        
        $stmt->execute([':code' => $currencyCode, ':days' => $days]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getPreviousRate(string $currencyCode, string $beforeDate): ?array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            SELECT currency_code, currency_name, cotacao_compra, cotacao_venda, data_cotacao
            FROM exchange_rates
            WHERE currency_code = :code AND data_cotacao < :date
            ORDER BY data_cotacao DESC
            LIMIT 1
        ");
        
        $stmt->execute([':code' => $currencyCode, ':date' => $beforeDate]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getLastUpdateDate(): ?string
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->query("SELECT MAX(data_cotacao) as last_date FROM exchange_rates");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['last_date'] ?? null;
    }

    public function getLastFetchedAt(): ?string
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->query("SELECT MAX(fetched_at) as last_fetched FROM exchange_rates");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['last_fetched'] ?? null;
    }

    public function cleanupOldRates(int $keepDays = 90): int
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            DELETE FROM exchange_rates 
            WHERE data_cotacao < DATE_SUB(CURDATE(), INTERVAL :days DAY)
        ");
        
        $stmt->execute([':days' => $keepDays]);
        
        return $stmt->rowCount();
    }
}
