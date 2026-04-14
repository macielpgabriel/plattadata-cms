<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOException;

final class CompanyRepository
{
    public function searchPaginated(string $term, ?string $state, int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $filters = [];
        $params = [];
        $useFullText = false;
        
        $term = trim($term);

        if ($term !== '') {
            $digits = preg_replace('/\D+/', '', $term) ?? '';
            // Se o termo parece um CNPJ ou parte de um (muitos dígitos), usa LIKE
            if (strlen($digits) >= 8) {
                $filters[] = '(cnpj LIKE :term_digits)';
                $params['term_digits'] = '%' . $digits . '%';
            } else {
                // Caso contrário, tenta busca FULLTEXT por nome/cidade
                $useFullText = true;
                $filters[] = 'MATCH(legal_name, trade_name, city) AGAINST(:term IN BOOLEAN MODE)';
                // Adiciona + para garantir que todos os termos sejam considerados ou use sintaxe boolean se preferir
                // Aqui vamos usar uma abordagem simples: se tiver mais de uma palavra, tratamos para o modo boolean
                $params['term'] = $term . '*';
            }
        }

        if ($state !== null && $state !== '') {
            $filters[] = 'state = :state';
            $params['state'] = strtoupper($state);
        }

        $filters[] = 'is_hidden = 0';
        $whereSql = ' WHERE ' . implode(' AND ', $filters);

        // Caching the COUNT result to avoid heavy queries on every pagination click
        $countCacheKey = 'company_search_total_' . md5($whereSql . serialize($params));
        $total = \App\Core\Cache::get($countCacheKey);

        if ($total === null) {
            $countSql = 'SELECT COUNT(*) AS total FROM companies' . $whereSql;
            $countStmt = Database::connection()->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue(':' . $key, $value);
            }
            $countStmt->execute();
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = (int) ($countResult['total'] ?? 0);
            
            // Cache total for 15 minutes
            \App\Core\Cache::set($countCacheKey, $total, 900);
        }

        $orderBy = $useFullText 
            ? 'MATCH(legal_name, trade_name, city) AGAINST(:term IN BOOLEAN MODE) DESC, updated_at DESC'
            : 'updated_at DESC';

        $dataSql = 'SELECT id, cnpj, legal_name, trade_name, city, state, status, source_provider, last_synced_at, updated_at
            FROM companies' . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT :limit OFFSET :offset';
        
        $dataStmt = Database::connection()->prepare($dataSql);

        foreach ($params as $key => $value) {
            $dataStmt->bindValue(':' . $key, $value);
        }
        $dataStmt->bindValue(':limit', (int) $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'data' => $dataStmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    public function findByMunicipality(int $ibgeCode, string $city, string $state, int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        // Tenta buscar por código IBGE primeiro (mais preciso)
        $countStmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total FROM companies WHERE municipal_ibge_code = :ibge_code AND is_hidden = 0'
        );
        $countStmt->execute(['ibge_code' => $ibgeCode]);
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        if ($total > 0) {
            $dataStmt = Database::connection()->prepare(
                'SELECT id, cnpj, legal_name, trade_name, phone, city, state, status, source_provider, last_synced_at, updated_at
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

        // Fallback: Busca por nome exato da cidade e UF
        $countStmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total FROM companies WHERE city = :city AND state = :state AND is_hidden = 0'
        );
        $countStmt->execute(['city' => $city, 'state' => strtoupper($state)]);
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $dataStmt = Database::connection()->prepare(
            'SELECT id, cnpj, legal_name, trade_name, phone, city, state, status, source_provider, last_synced_at, updated_at
             FROM companies
             WHERE city = :city AND state = :state AND is_hidden = 0
             ORDER BY updated_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $dataStmt->bindValue(':city', $city, PDO::PARAM_STR);
        $dataStmt->bindValue(':state', strtoupper($state), PDO::PARAM_STR);
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

    public function findByCnpj(string $cnpj, bool $includeHidden = false): ?array
    {
        $sql = 'SELECT * FROM companies WHERE cnpj = :cnpj';
        if (!$includeHidden) {
            $sql .= ' AND is_hidden = 0';
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['cnpj' => $cnpj]);
        $company = $stmt->fetch();

        return $company ?: null;
    }

    public function findEnrichmentByCompanyId(int $companyId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM company_enrichments WHERE company_id = :company_id LIMIT 1');
        $stmt->execute(['company_id' => $companyId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function recent(int $limit = 12): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT cnpj, legal_name, trade_name, city, state, source_provider, last_synced_at, updated_at
             FROM companies WHERE is_hidden = 0 ORDER BY updated_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function sitemapEntries(int $limit = 5000): array
    {
        $stmt = Database::connection()->prepare('SELECT cnpj, updated_at FROM companies WHERE is_hidden = 0 ORDER BY updated_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function upsertFromApi(string $cnpj, array $payload, string $source = 'api', array $sourceContext = []): array
    {
        $connection = Database::connection();
        $existing = $this->findByCnpj($cnpj);
        $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $provider = $this->limit((string) ($sourceContext['provider'] ?? config('app.cnpj.provider', 'unknown')), 64);

        try {
            $connection->beginTransaction();

            if ($existing) {
                $stmt = $connection->prepare(
                    'UPDATE companies SET
                        legal_name = :legal_name,
                        trade_name = :trade_name,
                        email = :email,
                        phone = :phone,
                        city = :city,
                        state = :state,
                        status = :status,
                        opened_at = :opened_at,
                        raw_data = :raw_data,
                        postal_code = :postal_code,
                        district = :district,
                        street = :street,
                        address_number = :address_number,
                        address_complement = :address_complement,
                        municipal_ibge_code = :municipal_ibge_code,
                        cnae_main_code = :cnae_main_code,
                        legal_nature = :legal_nature,
                        company_size = :company_size,
                        simples_opt_in = :simples_opt_in,
                        mei_opt_in = :mei_opt_in,
                        capital_social = :capital_social,
                        source_provider = :source_provider,
                        last_synced_at = NOW(),
                        query_failures = 0,
                        updated_at = NOW(),
                        state_registration = :state_registration,
                        municipal_registration = :municipal_registration,
                        motivo_situacao = :motivo_situacao,
                        data_situacao = :data_situacao
                     WHERE cnpj = :cnpj'
                );
            } else {
                $stmt = $connection->prepare(
                    'INSERT INTO companies (
                        cnpj, legal_name, trade_name, email, phone, city, state, status, opened_at, raw_data,
                        postal_code, district, street, address_number, address_complement, municipal_ibge_code,
                        cnae_main_code, legal_nature, company_size, simples_opt_in, mei_opt_in, capital_social,
                        source_provider, last_synced_at, query_failures, updated_at,
                        state_registration, municipal_registration, motivo_situacao, data_situacao
                    ) VALUES (
                        :cnpj, :legal_name, :trade_name, :email, :phone, :city, :state, :status, :opened_at, :raw_data,
                        :postal_code, :district, :street, :address_number, :address_complement, :municipal_ibge_code,
                        :cnae_main_code, :legal_nature, :company_size, :simples_opt_in, :mei_opt_in, :capital_social,
                        :source_provider, NOW(), 0, NOW(),
                        :state_registration, :municipal_registration, :motivo_situacao, :data_situacao
                    )'
                );
            }

            $stmt->execute([
                'cnpj' => $cnpj,
                'legal_name' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'razao_social',
                    'legal_name',
                    'nome',
                ]), 200)),
                'trade_name' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'nome_fantasia',
                    'trade_name',
                    'fantasia',
                    'estabelecimento.nome_fantasia',
                ]), 200)),
                'email' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'email',
                    'estabelecimento.email',
                ]), 200)),
                'phone' => $this->limit((string) $this->firstPayloadValue($payload, [
                    'telefone',
                    'ddd_telefone_1',
                    'phone',
                    'telefone1',
                    'estabelecimento.telefone1',
                ]), 30),
                'city' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'municipio',
                    'cidade',
                    'city',
                    'estabelecimento.cidade.nome',
                ]), 120)),
                'state' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'uf',
                    'estado',
                    'state',
                    'estabelecimento.estado.sigla',
                ]), 2)),
                'status' => $this->limit((string) $this->firstPayloadValue($payload, [
                    'status',
                    'situacao',
                    'situacao_cadastral',
                    'descricao_situacao_cadastral',
                    'estabelecimento.situacao_cadastral',
                ]), 120),
                'opened_at' => $this->normalizeDate($this->firstPayloadValue($payload, [
                    'data_inicio_atividade',
                    'data_abertura',
                    'opened_at',
                    'abertura',
                    'estabelecimento.data_inicio_atividade',
                ])),
                'raw_data' => $jsonData,
                'postal_code' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'cep',
                    'postal_code',
                    'estabelecimento.cep',
                ]), 8)),
                'district' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'bairro',
                    'district',
                    'estabelecimento.bairro',
                ]), 120)),
                'street' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'logradouro',
                    'street',
                    'estabelecimento.logradouro',
                ]), 180)),
                'address_number' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'numero',
                    'address_number',
                    'estabelecimento.numero',
                ]), 30)),
                'address_complement' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'complemento',
                    'address_complement',
                    'estabelecimento.complemento',
                ]), 120)),
                'municipal_ibge_code' => $this->nullableMunicipalIbgeCode(
                    $payload['codigo_municipio_ibge']
                    ?? $payload['municipal_ibge_code']
                    ?? ($payload['_municipality_details']['id'] ?? null)
                    ?? ($payload['_cep_details']['ibge_code'] ?? ($payload['_cep_details']['ibge'] ?? null))
                    ?? ($payload['estabelecimento']['cidade']['ibge_id'] ?? null)
                ),
                'cnae_main_code' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'cnae_fiscal',
                    'cnae_main_code',
                    'atividade_principal.0.codigo',
                    'atividade_principal.0.code',
                    'estabelecimento.atividade_principal.id',
                ]), 16)),
                'legal_nature' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'natureza_juridica',
                    'legal_nature',
                    'natureza_juridica.descricao',
                ]), 180)),
                'company_size' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'porte',
                    'company_size',
                    'porte.descricao',
                ]), 100)),
                'simples_opt_in' => $this->nullableBool(
                    $payload['opcao_pelo_simples']
                    ?? $payload['simples_opt_in']
                    ?? ($payload['simples']['optante'] ?? null)
                ),
                'mei_opt_in' => $this->nullableBool(
                    $payload['opcao_pelo_mei']
                    ?? $payload['mei_opt_in']
                    ?? ($payload['simples']['simei']['optante'] ?? null)
                ),
                'capital_social' => $this->nullableDecimal($payload['capital_social'] ?? null),
                'source_provider' => $provider,
                'state_registration' => $this->nullable($this->limit($this->extractStateRegistration($payload), 50)),
                'municipal_registration' => $this->nullable($this->limit($this->extractMunicipalRegistration($payload), 50)),
                'motivo_situacao' => $this->nullable($this->limit((string) $this->firstPayloadValue($payload, [
                    'motivo_situacao_cadastral',
                    'motivo_situacao',
                    'estabelecimento.motivo_situacao_cadastral',
                ]), 200)),
                'data_situacao' => $this->normalizeDate($this->firstPayloadValue($payload, [
                    'data_situacao_cadastral',
                    'data_situacao',
                    'estabelecimento.data_situacao_cadastral',
                ])),
            ]);

            $company = $this->findByCnpj($cnpj) ?? [];
            if (!empty($company['id'])) {
                $companyId = (int) $company['id'];
                $this->insertSnapshot($companyId, $source, $payload);
                $this->upsertEnrichment($companyId, $payload, $provider);
                $this->insertSourceAttempts($cnpj, $companyId, $sourceContext['attempts'] ?? []);
            } else {
                $this->insertSourceAttempts($cnpj, null, $sourceContext['attempts'] ?? []);
            }

            $connection->commit();

            return $company;
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            $this->registerQueryFailure($cnpj);
            throw $exception;
        }
    }

    public function getStatsByMunicipality(int $ibgeCode): array
    {
        $db = Database::connection();
        
        // 1. Total por porte (company_size)
        $sizeStmt = $db->prepare(
            'SELECT company_size, COUNT(*) as total 
             FROM companies 
             WHERE municipal_ibge_code = :ibge_code AND is_hidden = 0 
             GROUP BY company_size ORDER BY total DESC'
        );
        $sizeStmt->execute(['ibge_code' => $ibgeCode]);
        $sizes = $sizeStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Total por status
        $statusStmt = $db->prepare(
            'SELECT status, COUNT(*) as total 
             FROM companies 
             WHERE municipal_ibge_code = :ibge_code AND is_hidden = 0 
             GROUP BY status ORDER BY total DESC'
        );
        $statusStmt->execute(['ibge_code' => $ibgeCode]);
        $statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Top 5 CNAEs principais
        $cnaeStmt = $db->prepare(
            'SELECT cnae_main_code, COUNT(*) as total 
             FROM companies 
             WHERE municipal_ibge_code = :ibge_code AND is_hidden = 0 
             GROUP BY cnae_main_code ORDER BY total DESC LIMIT 5'
        );
        $cnaeStmt->execute(['ibge_code' => $ibgeCode]);
        $cnaes = $cnaeStmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Soma de Capital Social
        $capitalStmt = $db->prepare(
            'SELECT SUM(capital_social) as total 
             FROM companies 
             WHERE municipal_ibge_code = :ibge_code AND is_hidden = 0'
        );
        $capitalStmt->execute(['ibge_code' => $ibgeCode]);
        $capital = $capitalStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'sizes' => $sizes,
            'statuses' => $statuses,
            'top_cnaes' => $cnaes,
            'total_capital' => (float) ($capital['total'] ?? 0),
        ];
    }

    public function getSearchVolumeStats(int $days = 7): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as total 
             FROM company_query_logs 
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRecentGlobalSearches(int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT l.*, c.legal_name, c.cnpj, u.name as user_name 
             FROM company_query_logs l
             JOIN companies c ON c.id = l.company_id
             LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.id DESC LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function incrementViews(int $companyId): void
    {
        $stmt = Database::connection()->prepare('UPDATE companies SET views = views + 1 WHERE id = :id');
        $stmt->execute(['id' => $companyId]);
    }

    public function deleteByCnpj(string $cnpj): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM companies WHERE cnpj = :cnpj');
        return $stmt->execute(['cnpj' => $cnpj]);
    }

    public function updateIbgeCode(int $companyId, int $ibgeCode): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE companies SET municipal_ibge_code = :ibge_code, updated_at = NOW() WHERE id = :id'
        );
        return $stmt->execute(['ibge_code' => $ibgeCode, 'id' => $companyId]);
    }

    public function logSearch(int $companyId, int $userId, string $source, ?string $ipAddress): void
    {
        $safeIp = $this->anonymizeIp($ipAddress);

        $stmt = Database::connection()->prepare(
            'INSERT INTO company_query_logs (company_id, user_id, source, ip_address, created_at) VALUES (:company_id, :user_id, :source, :ip_address, NOW())'
        );
        $stmt->execute([
            'company_id' => $companyId,
            'user_id' => $userId,
            'source' => $source,
            'ip_address' => $safeIp,
        ]);
    }

    public function getSnapshots(int $companyId, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, source, raw_data, created_at FROM company_snapshots WHERE company_id = :company_id ORDER BY id DESC LIMIT :limit'
        );
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getQueryHistory(int $companyId, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.source, l.ip_address, l.created_at, u.name AS user_name
            FROM company_query_logs l
            INNER JOIN users u ON u.id = l.user_id
            WHERE l.company_id = :company_id
            ORDER BY l.id DESC
            LIMIT :limit'
        );
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['ip_address'] = $this->anonymizeIp($row['ip_address'] ?? null);
        }

        return $rows;
    }

    private function normalizeDate(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $date = trim((string) $date);

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            return sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date)) {
            return $date;
        }

        return null;
    }

    private function insertSnapshot(int $companyId, string $source, array $payload): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO company_snapshots (company_id, source, raw_data, created_at) VALUES (:company_id, :source, :raw_data, NOW())'
        );
        $stmt->execute([
            'company_id' => $companyId,
            'source' => $source,
            'raw_data' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function upsertEnrichment(int $companyId, array $payload, string $provider): void
    {
        $cepDetails = is_array($payload['_cep_details'] ?? null) ? $payload['_cep_details'] : [];
        $cepResolved = is_array($cepDetails['resolved'] ?? null) ? $cepDetails['resolved'] : [];
        $municipality = is_array($payload['_municipality_details'] ?? null) ? $payload['_municipality_details'] : [];
        $maps = is_array($payload['_map_links'] ?? null) ? $payload['_map_links'] : [];
        $coordinates = is_array($maps['coordinates'] ?? null) ? $maps['coordinates'] : [];

        $lat = (isset($coordinates[0]) && is_numeric($coordinates[0])) ? (float) $coordinates[0] : null;
        $lng = (isset($coordinates[1]) && is_numeric($coordinates[1])) ? (float) $coordinates[1] : null;

        $stmt = Database::connection()->prepare(
            'INSERT INTO company_enrichments (
                company_id, cep_source, ddd, region_name, mesoregion, microregion, geocode_source,
                latitude, longitude, ibge_code, updated_at
            ) VALUES (
                :company_id, :cep_source, :ddd, :region_name, :mesoregion, :microregion, :geocode_source,
                :latitude, :longitude, :ibge_code, NOW()
            )
            ON DUPLICATE KEY UPDATE
                cep_source = VALUES(cep_source),
                ddd = VALUES(ddd),
                region_name = VALUES(region_name),
                mesoregion = VALUES(mesoregion),
                microregion = VALUES(microregion),
                geocode_source = VALUES(geocode_source),
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                ibge_code = VALUES(ibge_code),
                updated_at = NOW()'
        );

        $stmt->execute([
            'company_id' => $companyId,
            'cep_source' => $this->nullable($this->limit((string) ($cepDetails['source'] ?? ''), 32)),
            'ddd' => $this->nullable($this->limit((string) ($cepResolved['ddd'] ?? ''), 8)),
            'region_name' => $this->nullable($this->limit((string) ($municipality['regiao'] ?? ''), 80)),
            'mesoregion' => $this->nullable($this->limit((string) ($municipality['mesorregiao'] ?? ''), 120)),
            'microregion' => $this->nullable($this->limit((string) ($municipality['microrregiao'] ?? ''), 120)),
            'geocode_source' => $lat !== null && $lng !== null ? $provider : null,
            'latitude' => $lat,
            'longitude' => $lng,
            'ibge_code' => $this->nullableMunicipalIbgeCode(
                $payload['codigo_municipio_ibge']
                ?? ($municipality['id'] ?? null)
                ?? ($cepResolved['ibge_code'] ?? ($cepResolved['ibge'] ?? null))
                ?? ($cepDetails['ibge_code'] ?? ($cepDetails['ibge'] ?? null))
            ),
        ]);
    }

    private function insertSourceAttempts(string $cnpj, ?int $companyId, array $attempts): void
    {
        if ($attempts === []) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO company_source_payloads (
                company_id, cnpj, provider, request_url, status_code, succeeded, error_message, response_json, payload_checksum, fetched_at
            ) VALUES (
                :company_id, :cnpj, :provider, :request_url, :status_code, :succeeded, :error_message, :response_json, :payload_checksum, NOW()
            )'
        );

        foreach ($attempts as $attempt) {
            if (!is_array($attempt)) {
                continue;
            }

            $responseJson = null;
            if (isset($attempt['response_json']) && is_array($attempt['response_json'])) {
                $responseJson = json_encode($attempt['response_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $checksum = $responseJson !== null ? hash('sha256', $responseJson) : null;

            $stmt->execute([
                'company_id' => $companyId,
                'cnpj' => $cnpj,
                'provider' => $this->limit((string) ($attempt['provider'] ?? 'unknown'), 64),
                'request_url' => $this->nullable($this->limit((string) ($attempt['url'] ?? ''), 255)),
                'status_code' => (int) ($attempt['status_code'] ?? 0),
                'succeeded' => (int) ($attempt['succeeded'] ?? 0),
                'error_message' => $this->nullable($this->limit((string) ($attempt['error_message'] ?? ''), 255)),
                'response_json' => $responseJson,
                'payload_checksum' => $checksum,
            ]);
        }
    }

    private function registerQueryFailure(string $cnpj): void
    {
        $stmt = Database::connection()->prepare('UPDATE companies SET query_failures = query_failures + 1 WHERE cnpj = :cnpj');
        $stmt->execute(['cnpj' => $cnpj]);
    }

    private function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }


    private function nullableMunicipalIbgeCode(mixed $value): ?int
    {
        $code = $this->nullableInt($value);
        if ($code === null) {
            return null;
        }

        // Código IBGE municipal válido possui 7 dígitos.
        if ($code < 1000000 || $code > 9999999) {
            return null;
        }

        return $code;
    }

    private function nullableBool(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true ? 1 : 0;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        $text = str_replace(['R$', ' '], '', $text);
        $hasComma = str_contains($text, ',');
        $hasDot = str_contains($text, '.');

        if ($hasComma && $hasDot) {
            // Formato BR: 1.234.567,89
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif ($hasComma) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private function limit(string $value, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }

    private function anonymizeIp(?string $ipAddress): ?string
    {
        if (!is_string($ipAddress) || trim($ipAddress) === '') {
            return null;
        }

        $ipAddress = trim($ipAddress);

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ipAddress);
            if (count($parts) === 4) {
                return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
            }
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ipAddress);
            if ($packed !== false) {
                $masked = substr($packed, 0, 6) . str_repeat("\x00", 10);
                $text = @inet_ntop($masked);
                if (is_string($text) && $text !== '') {
                    return $text . '/48';
                }
            }
        }

        return null;
    }

    private function extractStateRegistration(array $payload): string
    {
        if (!empty($payload['inscricoes_estaduais']) && is_array($payload['inscricoes_estaduais'])) {
            foreach ($payload['inscricoes_estaduais'] as $ie) {
                $value = (string) (
                    $ie['inscricao']
                    ?? $ie['inscricao_estadual']
                    ?? $ie['ie']
                    ?? ''
                );
                if ($value !== '' && strtoupper($value) !== 'ISENTO') {
                    return $value;
                }
            }
        }
        if (!empty($payload['inscricao_estadual'])) {
            return (string) $payload['inscricao_estadual'];
        }
        if (!empty($payload['estabelecimento']['inscricoes_estaduais']) && is_array($payload['estabelecimento']['inscricoes_estaduais'])) {
            foreach ($payload['estabelecimento']['inscricoes_estaduais'] as $ie) {
                $value = (string) ($ie['inscricao_estadual'] ?? $ie['ie'] ?? '');
                if ($value !== '' && strtoupper($value) !== 'ISENTO') {
                    return $value;
                }
            }
        }
        return '';
    }

    private function extractMunicipalRegistration(array $payload): string
    {
        if (!empty($payload['inscricao_municipal'])) {
            return (string) $payload['inscricao_municipal'];
        }
        if (!empty($payload['estabelecimento']['inscricao_municipal'])) {
            return (string) $payload['estabelecimento']['inscricao_municipal'];
        }
        return '';
    }

    private function firstPayloadValue(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = $this->payloadValue($payload, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    }

    private function payloadValue(array $payload, string $path): mixed
    {
        if (!str_contains($path, '.')) {
            return $payload[$path] ?? null;
        }

        $parts = explode('.', $path);
        $current = $payload;
        foreach ($parts as $part) {
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
                continue;
            }
            return null;
        }

        return $current;
    }
}
