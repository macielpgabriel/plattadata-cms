<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

final class StockMarketService
{
    use HttpFetchTrait;

    private string $baseUrl = 'https://brapi.dev/api';
    private ?string $apiToken = null;

    public function __construct()
    {
        $this->baseUrl = config('stock_market.brapi_url', 'https://brapi.dev/api');
        $this->apiToken = config('stock_market.brapi_token', null);
        $this->timeout = (int) config('stock_market.timeout', 10);
    }

    public function getQuoteByCnpj(string $cnpj): ?array
    {
        $company = $this->findCompanyByCnpj($cnpj);
        if (!$company) {
            return null;
        }

        $ticker = $this->findTickerByCompanyName($company['legal_name'] ?? $company['trade_name'] ?? '');
        if (!$ticker) {
            return null;
        }

        return $this->getQuote($ticker);
    }

    public function getQuote(string $ticker): ?array
    {
        $url = $this->baseUrl . '/quote/' . rawurlencode($ticker);

        $data = $this->fetchJson($url);
        if (!is_array($data) || empty($data['results'])) {
            return null;
        }

        $result = $data['results'][0] ?? null;
        if (!$result) {
            return null;
        }

        return $this->normalizeQuote($result, $ticker);
    }

    public function searchTicker(string $query): array
    {
        $url = $this->baseUrl . '/quote/list?search=' . rawurlencode($query) . '&limit=10';

        $data = $this->fetchJson($url);
        if (!is_array($data) || empty($data['stocks'])) {
            return [];
        }

        return array_map(fn($s) => [
            'ticker' => $s['stock'] ?? null,
            'name' => $s['name'] ?? null,
            'company_name' => $s['company_name'] ?? ($s['shortName'] ?? null),
            'domain' => $s['domain'] ?? null,
            'sector' => $s['sector'] ?? null,
            'industry' => $s['industry'] ?? null,
        ], $data['stocks']);
    }

    private function findTickerByCompanyName(string $companyName): ?string
    {
        if (empty($companyName)) {
            return null;
        }

        $normalized = $this->normalizeCompanyName($companyName);
        $searchTerms = $this->generateSearchTerms($normalized);

        foreach ($searchTerms as $term) {
            $results = $this->searchTicker($term);
            foreach ($results as $r) {
                if ($this->matches($normalized, $r['company_name'] ?? $r['name'] ?? '')) {
                    return $r['ticker'];
                }
            }
        }

        if (!empty($results = $this->searchTicker($normalized))) {
            return $results[0]['ticker'] ?? null;
        }

        return null;
    }

    private function normalizeCompanyName(string $name): string
    {
        $name = mb_strtolower($name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/\b(sa|sociedade anonim|ltda|limitada|me|epp|eireli)\b/i', '', $name);
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);
        return trim($name);
    }

    private function generateSearchTerms(string $name): array
    {
        $words = explode(' ', $name);
        $terms = [];

        if (count($words) >= 2) {
            $terms[] = $words[0];
            $terms[] = $words[0] . ' ' . $words[1];
        }

        if (count($words) >= 3) {
            $terms[] = $words[0] . ' ' . $words[1] . ' ' . $words[2];
        }

        $terms[] = mb_substr($name, 0, 12);

        return $terms;
    }

    private function matches(string $search, string $companyName): bool
    {
        $search = $this->normalizeCompanyName($search);
        $company = mb_strtolower($companyName);
        $company = $this->normalizeCompanyName($companyName);

        if (empty($search) || empty($company)) {
            return false;
        }

        $searchWords = explode(' ', $search);
        $companyWords = explode(' ', $company);

        $matchCount = 0;
        foreach ($searchWords as $word) {
            if (mb_strlen($word) < 3) {
                continue;
            }
            if (in_array($word, $companyWords, true)) {
                $matchCount++;
            }
        }

        return $matchCount >= min(2, floor(count($searchWords) / 2));
    }

    private function findCompanyByCnpj(string $cnpj): ?array
    {
        try {
            $db = Database::connection();
            $cnpjClean = preg_replace('/\D/', '', $cnpj);
            $stmt = $db->prepare(
                "SELECT id, legal_name, trade_name, cnpj, city, state 
                 FROM companies 
                 WHERE REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = :cnpj 
                 LIMIT 1"
            );
            $stmt->execute(['cnpj' => $cnpjClean]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (Throwable $e) {
            Logger::warning('StockMarket findCompanyByCnpj failed: ' . $e->getMessage());
            return null;
        }
    }

    private function normalizeQuote(array $data, string $ticker): array
    {
        return [
            'ticker' => $data['symbol'] ?? $ticker,
            'price' => $data['regularMarketPrice'] ?? $data['close'] ?? $data['price'] ?? null,
            'change' => $data['regularMarketChange'] ?? $data['change'] ?? null,
            'change_percent' => $data['regularMarketChangePercent'] ?? $data['change_pct'] ?? null,
            'volume' => $data['regularMarketVolume'] ?? $data['volume'] ?? null,
            'market_cap' => $data['marketCap'] ?? $data['market_cap'] ?? null,
            'logo' => $data['logourl'] ?? $data['logo'] ?? null,
            'name' => $data['shortName'] ?? $data['longName'] ?? $data['name'] ?? null,
            'domain' => $data['domain'] ?? null,
            'sector' => $data['sector'] ?? null,
            'industry' => $data['industry'] ?? null,
            'currency' => $data['currency'] ?? 'BRL',
            'price_earnings' => $data['priceEarnings'] ?? null,
            'earnings_per_share' => $data['earningsPerShare'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
}