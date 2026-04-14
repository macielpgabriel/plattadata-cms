<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class GoogleTrendsService
{
    private const CACHE_TTL = 86400;
    private const API_URL = 'https://trends.google.com/trends/api/widgetdata';

    public function getInterestOverTime(string $keyword, string $geo = 'BR'): array
    {
        $cacheKey = 'trends_' . md5($keyword . $geo);
        $cached = $this->getFromCache($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        try {
            $data = $this->fetchFromGoogle($keyword, $geo);
            $this->saveToCache($cacheKey, $data);
            return $data;
        } catch (\Exception $e) {
            return $this->getFallbackData($keyword);
        }
    }

    private function fetchFromGoogle(string $keyword, string $geo): array
    {
        $url = self::API_URL . '/multirange?comparisonItem=%5B%7B%22keyword%22%3A%22' 
            . urlencode($keyword) 
            . '%22%2C%22geo%22%3A%22' . $geo . '%22%2C%22time%22%3A%22today%203-m%22%7D%5D&property=&category=0';
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                'timeout' => 10,
            ],
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new \Exception('Failed to fetch Google Trends data');
        }

        $json = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $response = preg_replace('/^\s*\)[\'">]+/', '', $response);
            $json = json_decode($response, true);
        }
        
        if (!is_array($json)) {
            throw new \Exception('Invalid JSON response');
        }

        return $this->parseTrendsResponse($json);
    }

    private function parseTrendsResponse(array $json): array
    {
        $timeline = $json['timelineData'] ?? [];
        $result = [];
        
        foreach ($timeline as $item) {
            $result[] = [
                'date' => date('Y-m-d', (int) ($item['time'] ?? 0)),
                'value' => (int) ($item['value'][0] ?? 0),
                'formattedValue' => $item['formattedValue'][0] ?? '0',
            ];
        }
        
        return [
            'interest' => $result,
            'summary' => [
                'avg' => count($result) > 0 ? array_sum(array_column($result, 'value')) / count($result) : 0,
                'max' => count($result) > 0 ? max(array_column($result, 'value')) : 0,
                'min' => count($result) > 0 ? min(array_column($result, 'value')) : 0,
            ],
        ];
    }

    private function getFallbackData(string $keyword): array
    {
        return [
            'interest' => [],
            'summary' => [
                'avg' => 0,
                'max' => 0,
                'min' => 0,
            ],
            'error' => 'Dados não disponíveis offline',
        ];
    }

    public function compareKeywords(array $keywords, string $geo = 'BR'): array
    {
        $results = [];
        
        foreach ($keywords as $keyword) {
            $results[$keyword] = $this->getInterestOverTime($keyword, $geo);
        }
        
        return $results;
    }

    public function getRelatedQueries(string $keyword, string $geo = 'BR'): array
    {
        $cacheKey = 'trends_related_' . md5($keyword . $geo);
        $cached = $this->getFromCache($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        return [
            'top' => [],
            'rising' => [],
            'cached' => false,
        ];
    }

    public function getRegionalInterest(string $keyword, string $geo = 'BR'): array
    {
        $cacheKey = 'trends_regional_' . md5($keyword . $geo);
        $cached = $this->getFromCache($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        return [
            'regions' => [],
            'cached' => false,
        ];
    }

    private function getFromCache(string $key): ?array
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                SELECT result_json, expires_at 
                FROM compliance_cache 
                WHERE cnpj = ?
            ");
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($row && strtotime($row['expires_at']) > time()) {
                return json_decode($row['result_json'], true);
            }
        } catch (\Exception $e) {
            // Cache not available
        }
        
        return null;
    }

    private function saveToCache(string $key, array $data): void
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                INSERT INTO compliance_cache (cnpj, result_json, source, cached_at, expires_at)
                VALUES (?, ?, 'google_trends', NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))
                ON DUPLICATE KEY UPDATE result_json = VALUES(result_json), expires_at = VALUES(expires_at)
            ");
            $stmt->execute([$key, json_encode($data), self::CACHE_TTL]);
        } catch (\Exception $e) {
            // Cache not available
        }
    }
}
