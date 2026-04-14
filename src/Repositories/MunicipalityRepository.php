<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class MunicipalityRepository
{
    public function findByIbgeCode(int $ibgeCode): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM municipalities WHERE ibge_code = :ibge_code LIMIT 1'
        );
        $stmt->execute(['ibge_code' => $ibgeCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findBySlug(string $slug, string $uf): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM municipalities WHERE slug = :slug AND state_uf = :uf LIMIT 1'
        );
        $stmt->execute(['slug' => $slug, 'uf' => strtoupper($uf)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function updateSlug(int $ibgeCode, string $slug): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE municipalities SET slug = :slug WHERE ibge_code = :ibge_code'
        );
        return $stmt->execute(['slug' => $slug, 'ibge_code' => $ibgeCode]);
    }

    public function findByNameAndState(string $name, string $uf): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM municipalities WHERE name = :name AND state_uf = :uf LIMIT 1'
        );
        $stmt->execute(['name' => $name, 'uf' => strtoupper($uf)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByState(string $uf, int $page = 1, int $perPage = 50, ?string $search = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        
        $whereClause = 'WHERE state_uf = :uf';
        $params = ['uf' => strtoupper($uf)];
        
        if ($search && trim($search) !== '') {
            $whereClause .= ' AND name LIKE :search';
            $params['search'] = '%' . trim($search) . '%';
        }

        $countStmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS total FROM municipalities {$whereClause}"
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $dataStmt = Database::connection()->prepare(
            "SELECT * FROM municipalities {$whereClause} ORDER BY name ASC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $dataStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $dataStmt->bindValue(':limit', (int) $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'data' => $dataStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function search(string $term, int $page = 1, int $perPage = 50): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $searchTerm = '%' . $term . '%';

        $countStmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total FROM municipalities WHERE name LIKE :term'
        );
        $countStmt->execute(['term' => $searchTerm]);
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $dataStmt = Database::connection()->prepare(
            'SELECT m.*, s.name AS state_name, s.region
             FROM municipalities m
             INNER JOIN states s ON s.uf = m.state_uf
             WHERE m.name LIKE :term
             ORDER BY m.name ASC
             LIMIT :limit OFFSET :offset'
        );
        $dataStmt->bindValue(':term', $searchTerm, PDO::PARAM_STR);
        $dataStmt->bindValue(':limit', (int) $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'data' => $dataStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function upsert(array $data): bool
    {
        return $this->upsertMany([$data]);
    }

    public function upsertMany(array $items): bool
    {
        if (empty($items)) {
            return true;
        }

        $sql = 'INSERT INTO municipalities (
                ibge_code, name, slug, state_uf, mesoregion, microregion,
                population, gdp, gdp_per_capita, gdp_agri, gdp_industry, gdp_services, gdp_admin,
                vehicle_fleet, business_units, area_km2, ddd, updated_at
            ) VALUES ';

        $placeholders = [];
        $values = [];
        $i = 0;

        foreach ($items as $data) {
            $stateUf = strtoupper((string) ($data['state_uf'] ?? ''));
            if ($stateUf === '') {
                continue;
            }

            $slug = $data['slug'] ?? (isset($data['name']) ? slugify($data['name']) : null);
            
            $placeholders[] = "(
                :ibge_code$i, :name$i, :slug$i, :state_uf$i, :mesoregion$i, :microregion$i,
                :population$i, :gdp$i, :gdp_per_capita$i, :gdp_agri$i, :gdp_industry$i, :gdp_services$i, :gdp_admin$i,
                :vehicle_fleet$i, :business_units$i, :area_km2$i, :ddd$i, NOW()
            )";

            $values["ibge_code$i"] = $data['ibge_code'];
            $values["name$i"] = $data['name'];
            $values["slug$i"] = $slug;
            $values["state_uf$i"] = $stateUf;
            $values["mesoregion$i"] = $data['mesoregion'] ?? null;
            $values["microregion$i"] = $data['microregion'] ?? null;
            $values["population$i"] = $data['population'] ?? null;
            $values["gdp$i"] = $data['gdp'] ?? null;
            $values["gdp_per_capita$i"] = $data['gdp_per_capita'] ?? $data['gdpPerCapita'] ?? null;
            $values["gdp_agri$i"] = $data['gdp_agri'] ?? null;
            $values["gdp_industry$i"] = $data['gdp_industry'] ?? null;
            $values["gdp_services$i"] = $data['gdp_services'] ?? null;
            $values["gdp_admin$i"] = $data['gdp_admin'] ?? null;
            $values["vehicle_fleet$i"] = $data['vehicle_fleet'] ?? null;
            $values["business_units$i"] = $data['business_units'] ?? null;
            $values["area_km2$i"] = $data['area_km2'] ?? null;
            $values["ddd$i"] = $data['ddd'] ?? null;
            $i++;
        }

        if (empty($placeholders)) {
            return false;
        }

        $sql .= implode(', ', $placeholders);
        $sql .= ' ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                slug = IFNULL(VALUES(slug), slug),
                mesoregion = IFNULL(VALUES(mesoregion), mesoregion),
                microregion = IFNULL(VALUES(microregion), microregion),
                population = VALUES(population),
                gdp = VALUES(gdp),
                gdp_per_capita = VALUES(gdp_per_capita),
                gdp_agri = VALUES(gdp_agri),
                gdp_industry = VALUES(gdp_industry),
                gdp_services = VALUES(gdp_services),
                gdp_admin = VALUES(gdp_admin),
                vehicle_fleet = VALUES(vehicle_fleet),
                business_units = VALUES(business_units),
                area_km2 = VALUES(area_km2),
                ddd = VALUES(ddd),
                updated_at = NOW()';

        $stmt = Database::connection()->prepare($sql);
        return $stmt->execute($values);
    }

    public function updateWeather(int $ibgeCode, array $weatherData): bool
    {
        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $stmt = Database::connection()->prepare(
            'UPDATE municipalities SET
                weather_data = :weather_data,
                weather_updated_at = :updated_at,
                updated_at = :updated_at2
             WHERE ibge_code = :ibge_code'
        );

        return $stmt->execute([
            'ibge_code' => $ibgeCode,
            'weather_data' => json_encode($weatherData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_at2' => date('Y-m-d H:i:s'),
        ]);
    }

    public function count(): int
    {
        $stmt = Database::connection()->query('SELECT COUNT(*) AS total FROM municipalities');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['total'] ?? 0);
    }

    public function countByState(string $uf): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total FROM municipalities WHERE state_uf = :uf'
        );
        $stmt->execute(['uf' => strtoupper($uf)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['total'] ?? 0);
    }

    public function getCompaniesCountByMunicipality(): array
    {
        $stmt = Database::connection()->query(
            'SELECT municipal_ibge_code AS ibge_code, COUNT(*) AS count
             FROM companies
             WHERE municipal_ibge_code IS NOT NULL AND is_hidden = 0
             GROUP BY municipal_ibge_code
             ORDER BY count DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCompaniesByMunicipality(int $ibgeCode, int $page = 1, int $perPage = 15): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        // Busca por código IBGE
        $countStmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total FROM companies WHERE municipal_ibge_code = :ibge_code AND is_hidden = 0'
        );
        $countStmt->execute(['ibge_code' => $ibgeCode]);
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        if ($total > 0) {
            $dataStmt = Database::connection()->prepare(
                'SELECT id, cnpj, legal_name, trade_name, city, state, status, source_provider, last_synced_at, updated_at
                 FROM companies
                 WHERE municipal_ibge_code = :ibge_code AND is_hidden = 0
                 ORDER BY updated_at DESC
                 LIMIT :limit OFFSET :offset'
            );
            $dataStmt->bindValue(':ibge_code', $ibgeCode, PDO::PARAM_INT);
            $dataStmt->bindValue(':limit', (int) $perPage, PDO::PARAM_INT);
            $dataStmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
            $dataStmt->execute();

            return [
                'data' => $dataStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ];
        }

        // Fallback: Busca por nome da cidade e UF (caso o código IBGE ainda não esteja sincronizado no cache de empresas)
        $muni = $this->findByIbgeCode($ibgeCode);
        if (!$muni) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'last_page' => 1];
        }

        $countStmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total FROM companies WHERE city = :city AND state = :uf AND is_hidden = 0'
        );
        $countStmt->execute(['city' => $muni['name'], 'uf' => $muni['state_uf']]);
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $dataStmt = Database::connection()->prepare(
            'SELECT id, cnpj, legal_name, trade_name, city, state, status, source_provider, last_synced_at, updated_at
             FROM companies
             WHERE city = :city AND state = :uf AND is_hidden = 0
             ORDER BY updated_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $dataStmt->bindValue(':city', $muni['name'], PDO::PARAM_STR);
        $dataStmt->bindValue(':uf', $muni['state_uf'], PDO::PARAM_STR);
        $dataStmt->bindValue(':limit', (int) $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'data' => $dataStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function findAllWithoutSlug(int $limit = 500): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ibge_code, name FROM municipalities WHERE slug IS NULL OR slug = "" LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateDdd(int $ibgeCode, string $ddd): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE municipalities SET ddd = :ddd, updated_at = NOW() WHERE ibge_code = :ibge_code'
        );
        return $stmt->execute(['ddd' => $ddd, 'ibge_code' => $ibgeCode]);
    }

    public function incrementViews(int $ibgeCode): void
    {
        $stmt = Database::connection()->prepare('UPDATE municipalities SET views = views + 1 WHERE ibge_code = :ibge_code');
        $stmt->execute(['ibge_code' => $ibgeCode]);
    }

    public function updateStats(int $ibgeCode, array $stats): bool
    {
        $setClauses = [];
        $params = ['ibge_code' => $ibgeCode];

        if (array_key_exists('mesoregion', $stats)) {
            $setClauses[] = 'mesoregion = COALESCE(:mesoregion, mesoregion)';
            $params['mesoregion'] = $stats['mesoregion'];
        }
        if (array_key_exists('microregion', $stats)) {
            $setClauses[] = 'microregion = COALESCE(:microregion, microregion)';
            $params['microregion'] = $stats['microregion'];
        }
        if (array_key_exists('population', $stats)) {
            $setClauses[] = 'population = :population';
            $params['population'] = $stats['population'];
        }
        if (array_key_exists('gdp', $stats)) {
            $setClauses[] = 'gdp = :gdp';
            $params['gdp'] = $stats['gdp'];
        }
        if (array_key_exists('gdp_per_capita', $stats)) {
            $setClauses[] = 'gdp_per_capita = :gdp_per_capita';
            $params['gdp_per_capita'] = $stats['gdp_per_capita'];
        }
        if (array_key_exists('gdp_agri', $stats)) {
            $setClauses[] = 'gdp_agri = :gdp_agri';
            $params['gdp_agri'] = $stats['gdp_agri'];
        }
        if (array_key_exists('gdp_industry', $stats)) {
            $setClauses[] = 'gdp_industry = :gdp_industry';
            $params['gdp_industry'] = $stats['gdp_industry'];
        }
        if (array_key_exists('gdp_services', $stats)) {
            $setClauses[] = 'gdp_services = :gdp_services';
            $params['gdp_services'] = $stats['gdp_services'];
        }
        if (array_key_exists('gdp_admin', $stats)) {
            $setClauses[] = 'gdp_admin = :gdp_admin';
            $params['gdp_admin'] = $stats['gdp_admin'];
        }
        if (array_key_exists('vehicle_fleet', $stats)) {
            $setClauses[] = 'vehicle_fleet = :vehicle_fleet';
            $params['vehicle_fleet'] = $stats['vehicle_fleet'];
        }
        if (array_key_exists('business_units', $stats)) {
            $setClauses[] = 'business_units = :business_units';
            $params['business_units'] = $stats['business_units'];
        }
        if (array_key_exists('area_km2', $stats)) {
            $setClauses[] = 'area_km2 = :area_km2';
            $params['area_km2'] = $stats['area_km2'];
        }
        if (array_key_exists('ddd', $stats)) {
            $setClauses[] = 'ddd = COALESCE(:ddd, ddd)';
            $params['ddd'] = $stats['ddd'];
        }

        if (empty($setClauses)) {
            return false;
        }

        $setClauses[] = 'updated_at = NOW()';

        $sql = 'UPDATE municipalities SET ' . implode(', ', $setClauses) . ' WHERE ibge_code = :ibge_code';
        $stmt = Database::connection()->prepare($sql);

        return $stmt->execute($params);
    }

    public function updateField(int $ibgeCode, string $field, mixed $value): bool
    {
        $allowedFields = [
            'population', 'gdp', 'gdp_per_capita', 'vehicle_fleet',
            'business_units', 'population_male', 'population_female',
            'population_male_percent', 'population_female_percent'
        ];
        
        if (!in_array($field, $allowedFields)) {
            return false;
        }
        
        $stmt = Database::connection()->prepare(
            "UPDATE municipalities SET {$field} = :value, updated_at = NOW() WHERE ibge_code = :ibge_code"
        );
        return $stmt->execute(['value' => $value, 'ibge_code' => $ibgeCode]);
    }

    public function findActiveInCache(int $limit = 5000): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT m.ibge_code, m.name, m.slug, m.state_uf
             FROM municipalities m
             INNER JOIN companies c ON c.municipal_ibge_code = m.ibge_code
             WHERE c.is_hidden = 0
             ORDER BY m.name ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findAllWithData(int $limit = 1000): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM municipalities 
             WHERE population IS NOT NULL OR gdp IS NOT NULL OR weather_data IS NOT NULL
             ORDER BY population DESC 
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findRecentByMunicipality(int $ibgeCode, int $limit = 5): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT cnpj, legal_name, trade_name, city, state, status, opened_at, updated_at
             FROM companies
             WHERE municipal_ibge_code = :ibge_code AND is_hidden = 0
             ORDER BY opened_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':ibge_code', $ibgeCode, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAllForWeatherSync(int $limit = 100, int $offset = 0): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ibge_code, name, state_uf, weather_updated_at 
             FROM municipalities 
             ORDER BY name ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Updates a single column for multiple municipalities in bulk.
     * 
     * @param array $updates Array map of [ibge_code => value]
     * @param string $column Column name to update (must be validated/safe)
     * @return int Number of updated records
     */
    public function updateStatsBatch(array $updates, string $column): int
    {
        if (empty($updates)) {
            return 0;
        }

        $allowedColumns = ['population', 'gdp', 'gdp_per_capita', 'area', 'density', 'weather_data', 'economic_data'];
        if (!in_array($column, $allowedColumns, true)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("UPDATE municipalities SET {$column} = :value, updated_at = NOW() WHERE ibge_code = :ibge_code");
            $count = 0;

            foreach ($updates as $ibgeCode => $value) {
                // Skip invalid values but don't fail the whole batch
                if ($value === null || $value === '' || $value === '...') continue;
                
                $stmt->execute([
                    'ibge_code' => (int) $ibgeCode,
                    'value' => $value
                ]);
                $count++;
            }

            $pdo->commit();
            return $count;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
