<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

final class DddService
{
    use HttpFetchTrait;

    private string $baseUrl;
    private int $cacheTtlDays;

    /** @var array<int|string, string> Map of DDD to State */
    private const DDD_STATE_MAP = [
        '11' => 'SP', '12' => 'SP', '13' => 'SP', '14' => 'SP', '15' => 'SP', '16' => 'SP', '17' => 'SP', '18' => 'SP', '19' => 'SP',
        '21' => 'RJ', '22' => 'RJ', '24' => 'RJ',
        '27' => 'ES', '28' => 'ES',
        '31' => 'MG', '32' => 'MG', '33' => 'MG', '34' => 'MG', '35' => 'MG', '37' => 'MG', '38' => 'MG',
        '41' => 'PR', '42' => 'PR', '43' => 'PR', '44' => 'PR', '45' => 'PR', '46' => 'PR',
        '47' => 'SC', '48' => 'SC', '49' => 'SC',
        '51' => 'RS', '53' => 'RS', '54' => 'RS', '55' => 'RS',
        '61' => 'DF',
        '62' => 'GO', '64' => 'GO', '65' => 'MT', '66' => 'MT', '67' => 'MS',
        '68' => 'AC', '69' => 'RO',
        '71' => 'BA', '73' => 'BA', '74' => 'BA', '75' => 'BA', '77' => 'BA',
        '79' => 'SE',
        '81' => 'PE', '87' => 'PE', '82' => 'AL', '83' => 'PB', '84' => 'RN', '85' => 'CE', '88' => 'CE', '86' => 'PI', '89' => 'PI',
        '91' => 'PA', '93' => 'PA', '94' => 'PA', '92' => 'AM', '97' => 'AM', '95' => 'RR', '96' => 'AP', '98' => 'MA', '99' => 'MA',
    ];

    public function __construct()
    {
        $this->baseUrl = (string) config('app.ddd.base_url', 'https://brasilapi.com.br/api/ddd/v1');
        $this->timeout = (int) config('app.ddd.timeout', 5);
        $this->cacheTtlDays = (int) config('ddd.cache_ttl_days', 30);
    }

    private function needsRefresh(?string $cachedAt, int $ttlDays): bool
    {
        if ($cachedAt === null) return true;
        
        $cached = new \DateTime($cachedAt);
        $now = new \DateTime();
        $diff = $now->diff($cached);
        
        return $diff->days >= $ttlDays;
    }

    private function getFromCache(string $ddd): ?array
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "SELECT result_json, cached_at FROM ddd_cache WHERE ddd = ? AND expires_at > NOW() LIMIT 1"
            );
            $stmt->execute([$ddd]);
            $row = $stmt->fetch();
            
            if ($row) {
                $result = json_decode($row['result_json'], true);
                $result['from_cache'] = true;
                $result['cached_at'] = $row['cached_at'];
                return $result;
            }
        } catch (Throwable $e) {
            Logger::warning('DDD cache read failed: ' . $e->getMessage());
        }
        
        return null;
    }

    private function saveToCache(string $ddd, array $result): void
    {
        try {
            $db = Database::connection();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->cacheTtlDays} days"));
            
            $stmt = $db->prepare(
                "INSERT INTO ddd_cache (ddd, result_json, expires_at) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE result_json = VALUES(result_json), cached_at = NOW(), expires_at = VALUES(expires_at)"
            );
            $stmt->execute([$ddd, json_encode($result), $expiresAt]);
        } catch (Throwable $e) {
            Logger::warning('DDD cache save failed: ' . $e->getMessage());
        }
    }

    /**
     * Fetch DDD information with smart cache.
     * Checks database first, then API if stale.
     *
     * @param string $ddd DDD code (2 digits)
     * @return array|null DDD info with cities or null on failure
     */
    public function fetchDddInfo(string $ddd, bool $forceRefresh = false): ?array
    {
        $ddd = $this->sanitize($ddd);

        if (!$this->validate($ddd)) {
            return null;
        }

        if (!$forceRefresh) {
            $cached = $this->getFromCache($ddd);
            if ($cached !== null && !$this->needsRefresh($cached['cached_at'] ?? null, $this->cacheTtlDays)) {
                return $cached;
            }
        }

        $url = $this->baseUrl . '/' . $ddd;
        $data = $this->fetchJson($url);

        if (!is_array($data)) {
            $cached = $this->getFromCache($ddd);
            if ($cached !== null) {
                $cached['source'] = 'database_fallback';
                return $cached;
            }
            return [
                'ddd' => $ddd,
                'state' => $this->getStateByDdd($ddd),
                'cities' => [],
                'source' => 'Local cache',
                'fetched_at' => date('Y-m-d H:i:s'),
            ];
        }

        $cities = [];
        if (isset($data['cities']) && is_array($data['cities'])) {
            foreach ($data['cities'] as $city) {
                if (is_string($city)) {
                    $cities[] = $city;
                } elseif (is_array($city) && isset($city['nome'])) {
                    $cities[] = $city['nome'];
                }
            }
        }

        $result = [
            'ddd' => $ddd,
            'state' => $data['state'] ?? $this->getStateByDdd($ddd),
            'cities' => $cities,
            'city_count' => count($cities),
            'source' => 'BrasilAPI',
            'fetched_at' => date('Y-m-d H:i:s'),
        ];

        $this->saveToCache($ddd, $result);

        return $result;
    }

    /**
     * Validate if a DDD is valid for a given state.
     */
    public function validateDddForState(string $ddd, string $uf): bool
    {
        $ddd = $this->sanitize($ddd);
        $uf = strtoupper($uf);

        if (!$this->validate($ddd)) {
            return false;
        }

        $state = $this->getStateByDdd($ddd);
        return $state === $uf;
    }

    /**
     * Get state by DDD code.
     */
    public function getStateByDdd(string $ddd): string
    {
        $ddd = $this->sanitize($ddd);
        return self::DDD_STATE_MAP[$ddd] ?? '';
    }

    /**
     * Check if DDD is valid.
     */
    public function validate(string $ddd): bool
    {
        $ddd = $this->sanitize($ddd);
        return isset(self::DDD_STATE_MAP[$ddd]);
    }

    /**
     * Get all DDDs for a state.
     */
    public function getDddsByState(string $uf): array
    {
        $uf = strtoupper($uf);
        $ddds = [];

        foreach (self::DDD_STATE_MAP as $ddd => $state) {
            if ($state === $uf) {
                $ddds[] = $ddd;
            }
        }

        return $ddds;
    }

    /**
     * Get all valid DDD codes.
     */
    public function getAllDdds(): array
    {
        return array_keys(self::DDD_STATE_MAP);
    }

    /**
     * Extract DDD from a phone number.
     */
    public function extractDddFromPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        // Remove non-digits
        $digits = preg_replace('/\D+/', '', $phone);

        // Brazilian phone numbers: 10 or 11 digits
        // Format: DD + 8 or 9 digits
        // DDD is always the first 2 digits
        if (strlen($digits) >= 10) {
            $ddd = substr($digits, 0, 2);
            if ($this->validate($ddd)) {
                return $ddd;
            }
        }

        // If phone already starts with 2-digit DDD
        if (strlen($digits) === 2 && $this->validate($digits)) {
            return $digits;
        }

        return null;
    }

    /**
     * Sanitize DDD code.
     */
    public function sanitize(string $ddd): string
    {
        return preg_replace('/\D+/', '', $ddd) ?? '';
    }

    /**
     * Format DDD for display.
     */
    public function format(string $ddd): string
    {
        $ddd = $this->sanitize($ddd);
        return '(' . $ddd . ')';
    }
}
