<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * Service to handle address-related logic, such as CEP lookups.
 * Uses BrasilAPI and ViaCEP as fallback with 30-day DB cache.
 */
final class AddressService
{
    use HttpFetchTrait;

    private int $cacheTtlDays;

    public function __construct()
    {
        $this->timeout = (int) config('address.timeout', 5);
        $this->cacheTtlDays = (int) config('address.cache_ttl_days', 30);
    }

    /**
     * Find address by CEP with caching.
     */
    public function findByCep(string $cep): ?array
    {
        $cep = preg_replace('/\D+/', '', $cep) ?? '';
        if (strlen($cep) !== 8) {
            return null;
        }

        $cached = $this->getFromCache($cep);
        if ($cached !== null) {
            $cached['from_cache'] = true;
            return $cached;
        }

        $result = $this->fetchFromApis($cep);
        if ($result !== null) {
            $this->saveToCache($cep, $result);
        }

        return $result;
    }

    private function fetchFromApis(string $cep): ?array
    {
        // Try BrasilAPI first (excellent v2 support)
        try {
            $data = $this->fetchJson("https://brasilapi.com.br/api/cep/v2/{$cep}");
            if ($data && !isset($data['type'])) {
                $normalized = $this->normalizeBrasilApi($data);

                // Se vier sem código IBGE, tenta complementar via ViaCEP.
                if (empty($normalized['ibge_code'])) {
                    $viaCep = $this->fetchJson("https://viacep.com.br/ws/{$cep}/json/");
                    if ($viaCep && !isset($viaCep['erro'])) {
                        $via = $this->normalizeViaCep($viaCep);
                        $normalized['ibge_code'] = $normalized['ibge_code'] ?? ($via['ibge_code'] ?? null);
                        $normalized['ibge'] = $normalized['ibge'] ?? ($via['ibge'] ?? null);
                        $normalized['ddd'] = $normalized['ddd'] ?? ($via['ddd'] ?? null);
                    }
                }

                return $normalized;
            }
        } catch (Throwable $e) {
            Logger::warning('Address lookup failed (original): ' . $e->getMessage());
        }

        // Fallback to ViaCEP
        try {
            $data = $this->fetchJson("https://viacep.com.br/ws/{$cep}/json/");
            if ($data && !isset($data['erro'])) {
                return $this->normalizeViaCep($data);
            }
        } catch (Throwable $e) {
            Logger::warning('Address lookup failed (viacep): ' . $e->getMessage());
        }

        return null;
    }

    private function getFromCache(string $cep): ?array
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "SELECT result_json FROM address_cache WHERE cep = ? AND expires_at > NOW() LIMIT 1"
            );
            $stmt->execute([$cep]);
            $row = $stmt->fetch();
            
            if ($row) {
                return json_decode($row['result_json'], true);
            }
        } catch (Throwable $e) {
            Logger::warning('Address cache read failed: ' . $e->getMessage());
        }
        
        return null;
    }

    private function saveToCache(string $cep, array $result): void
    {
        try {
            $db = Database::connection();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->cacheTtlDays} days"));
            
            $stmt = $db->prepare(
                "INSERT INTO address_cache (cep, result_json, expires_at) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE result_json = VALUES(result_json), cached_at = NOW(), expires_at = VALUES(expires_at)"
            );
            $stmt->execute([$cep, json_encode($result), $expiresAt]);
        } catch (Throwable $e) {
            Logger::warning('Address cache save failed: ' . $e->getMessage());
        }
    }

    public function invalidateCache(string $cep): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("DELETE FROM address_cache WHERE cep = ?");
            $stmt->execute([preg_replace('/\D+/', '', $cep)]);
            return true;
        } catch (Throwable $e) {
            Logger::warning('Address cache invalidate failed: ' . $e->getMessage());
            return false;
        }
    }

    private function normalizeBrasilApi(array $d): array
    {
        return $this->normalizeAddress('BrasilAPI', [
            'cep' => $d['cep'] ?? '',
            'state' => $d['state'] ?? '',
            'city' => $d['city'] ?? '',
            'neighborhood' => $d['neighborhood'] ?? '',
            'street' => $d['street'] ?? '',
            'service' => $d['service'] ?? '',
            'ibge_code' => $d['ibge_code'] ?? null,
            'ddd' => null,
        ], $d['location']['coordinates'] ?? null);
    }

    private function normalizeViaCep(array $d): array
    {
        return $this->normalizeAddress('ViaCEP', [
            'cep' => preg_replace('/\D+/', '', $d['cep'] ?? ''),
            'state' => $d['uf'] ?? '',
            'city' => $d['localidade'] ?? '',
            'neighborhood' => $d['bairro'] ?? '',
            'street' => $d['logradouro'] ?? '',
            'service' => 'viacep',
            'ibge_code' => $d['ibge'] ?? null,
            'ddd' => $d['ddd'] ?? null,
        ]);
    }

    private function normalizeAddress(string $source, array $data, ?array $coordinates = null): array
    {
        return [
            'source' => $source,
            'cep' => $data['cep'],
            'state' => $data['state'],
            'city' => $data['city'],
            'neighborhood' => $data['neighborhood'],
            'street' => $data['street'],
            'service' => $data['service'],
            'ibge_code' => $data['ibge_code'],
            'ibge' => $data['ibge_code'],
            'coordinates' => $coordinates,
            'ddd' => $data['ddd'],
        ];
    }
}
