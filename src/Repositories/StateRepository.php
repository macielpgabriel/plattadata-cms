<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class StateRepository
{
    public function findAll(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM states ORDER BY name ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByUf(string $uf): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM states WHERE uf = :uf LIMIT 1'
        );
        $stmt->execute(['uf' => strtoupper($uf)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByIbgeCode(int $ibgeCode): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM states WHERE ibge_code = :ibge_code LIMIT 1'
        );
        $stmt->execute(['ibge_code' => $ibgeCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function count(): int
    {
        $stmt = Database::connection()->query('SELECT COUNT(*) AS total FROM states');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['total'] ?? 0);
    }

    public function upsert(array $data): bool
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO states (uf, name, region, ibge_code, population, gdp, gdp_per_capita, gdp_agri, gdp_industry, gdp_services, gdp_admin, area_km2, capital_city, updated_at)
             VALUES (:uf, :name, :region, :ibge_code, :population, :gdp, :gdp_per_capita, :gdp_agri, :gdp_industry, :gdp_services, :gdp_admin, :area_km2, :capital_city, NOW())
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                region = VALUES(region),
                population = VALUES(population),
                gdp = VALUES(gdp),
                gdp_per_capita = VALUES(gdp_per_capita),
                gdp_agri = VALUES(gdp_agri),
                gdp_industry = VALUES(gdp_industry),
                gdp_services = VALUES(gdp_services),
                gdp_admin = VALUES(gdp_admin),
                area_km2 = VALUES(area_km2),
                capital_city = VALUES(capital_city),
                updated_at = NOW()'
        );

        return $stmt->execute([
            'uf' => strtoupper($data['uf']),
            'name' => $data['name'],
            'region' => $data['region'],
            'ibge_code' => $data['ibge_code'],
            'population' => $data['population'] ?? null,
            'gdp' => $data['gdp'] ?? null,
            'gdp_per_capita' => $data['gdp_per_capita'] ?? null,
            'gdp_agri' => $data['gdp_agri'] ?? null,
            'gdp_industry' => $data['gdp_industry'] ?? null,
            'gdp_services' => $data['gdp_services'] ?? null,
            'gdp_admin' => $data['gdp_admin'] ?? null,
            'area_km2' => $data['area_km2'] ?? null,
            'capital_city' => $data['capital_city'] ?? null,
        ]);
    }

    public function updateStats(string $uf, array $stats): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE states SET
                population = :population,
                gdp = :gdp,
                gdp_per_capita = :gdp_per_capita,
                gdp_agri = :gdp_agri,
                gdp_industry = :gdp_industry,
                gdp_services = :gdp_services,
                gdp_admin = :gdp_admin,
                area_km2 = :area_km2,
                capital_city = :capital_city,
                updated_at = NOW()
             WHERE uf = :uf'
        );

        return $stmt->execute([
            'uf' => strtoupper($uf),
            'population' => $stats['population'] ?? null,
            'gdp' => $stats['gdp'] ?? null,
            'gdp_per_capita' => $stats['gdp_per_capita'] ?? null,
            'gdp_agri' => $stats['gdp_agri'] ?? null,
            'gdp_industry' => $stats['gdp_industry'] ?? null,
            'gdp_services' => $stats['gdp_services'] ?? null,
            'gdp_admin' => $stats['gdp_admin'] ?? null,
            'area_km2' => $stats['area_km2'] ?? null,
            'capital_city' => $stats['capital_city'] ?? null,
        ]);
    }

    public function getCompaniesCountByState(): array
    {
        $stmt = Database::connection()->query(
            'SELECT state AS uf, COUNT(*) AS count
             FROM companies
             WHERE state IS NOT NULL AND is_hidden = 0
             GROUP BY state
             ORDER BY count DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}