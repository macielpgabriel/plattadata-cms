<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CompanyTaxRepository
{
    public function findByCompanyId(int $companyId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM company_tax_data WHERE company_id = :company_id LIMIT 1'
        );
        $stmt->execute(['company_id' => $companyId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByCnpj(string $cnpj): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM company_tax_data WHERE cnpj = :cnpj LIMIT 1'
        );
        $stmt->execute(['cnpj' => $cnpj]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function upsert(int $companyId, string $cnpj, array $data, string $source): bool
    {
        $stateRegistrations = isset($data['state_registrations']) && is_array($data['state_registrations'])
            ? json_encode($data['state_registrations'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $stmt = Database::connection()->prepare(
            'INSERT INTO company_tax_data (
                company_id, cnpj, simples_opt_in, simples_since, mei_opt_in, mei_since,
                state_registrations, source, fetched_at, updated_at
            ) VALUES (
                :company_id, :cnpj, :simples_opt_in, :simples_since, :mei_opt_in, :mei_since,
                :state_registrations, :source, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                simples_opt_in = VALUES(simples_opt_in),
                simples_since = VALUES(simples_since),
                mei_opt_in = VALUES(mei_opt_in),
                mei_since = VALUES(mei_since),
                state_registrations = VALUES(state_registrations),
                source = VALUES(source),
                fetched_at = NOW(),
                updated_at = NOW()'
        );

        return $stmt->execute([
            'company_id' => $companyId,
            'cnpj' => $cnpj,
            'simples_opt_in' => $this->nullableBool($data['simples_opt_in'] ?? null),
            'simples_since' => $this->nullableDate($data['simples_since'] ?? null),
            'mei_opt_in' => $this->nullableBool($data['mei_opt_in'] ?? null),
            'mei_since' => $this->nullableDate($data['mei_since'] ?? null),
            'state_registrations' => $stateRegistrations,
            'source' => $source,
        ]);
    }

    public function deleteByCompanyId(int $companyId): bool
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM company_tax_data WHERE company_id = :company_id'
        );
        return $stmt->execute(['company_id' => $companyId]);
    }

    public function getFreshnessHours(int $companyId): ?int
    {
        $stmt = Database::connection()->prepare(
            'SELECT TIMESTAMPDIFF(HOUR, fetched_at, NOW()) AS hours_ago FROM company_tax_data WHERE company_id = :company_id'
        );
        $stmt->execute(['company_id' => $companyId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int) $result['hours_ago'] : null;
    }

    public function needsRefresh(int $companyId, int $maxAgeHours = 24): bool
    {
        $freshness = $this->getFreshnessHours($companyId);
        return $freshness === null || $freshness > $maxAgeHours;
    }

    private function nullableBool(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true ? 1 : 0;
    }

    private function nullableDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        // Try various date formats
        $formats = ['Y-m-d', 'd/m/Y', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'];
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }
}
