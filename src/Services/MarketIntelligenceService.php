<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Logger;
use Throwable;
use SimpleXMLElement;

/**
 * Service to gather market intelligence and reputation data.
 * Currently uses Google News RSS to fetch latest mentions.
 */
final class MarketIntelligenceService
{
    private int $timeout;

    public function __construct()
    {
        $this->timeout = (int) config('app.market_intelligence.timeout', 5);
    }

    /**
     * Get latest news for a company or keyword.
     * 
     * @param string $query The search query (e.g., Company Name)
     * @param int $limit Number of results
     * @return array
     */
    public function getCompanyNews(string $query, int $limit = 5): array
    {
        $cacheKey = "news_" . md5($query) . "_{$limit}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $encodedQuery = urlencode($query);
        $url = "https://news.google.com/rss/search?q={$encodedQuery}&hl=pt-BR&gl=BR&ceid=BR:pt-419";
        
        try {
            $content = $this->fetchUrl($url);
            if (!$content) {
                return [];
            }

            $xml = new SimpleXMLElement($content);
            $items = [];
            $count = 0;

            foreach ($xml->channel->item as $item) {
                if ($count >= $limit) break;

                $items[] = [
                    'title' => (string) $item->title,
                    'link' => (string) $item->link,
                    'pubDate' => (string) $item->pubDate,
                    'source' => (string) $item->source,
                    'description' => (string) $item->description,
                ];
                $count++;
            }

            Cache::set($cacheKey, $items, 43200);
            return $items;
        } catch (Throwable $e) {
            Logger::error("MarketIntelligence Error: " . $e->getMessage());
            return [];
        }
    }

    private function fetchUrl(string $url): string|false
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => "Accept: application/xml, text/xml\r\nUser-Agent: CMS-Empresarial/1.0\r\n"
            ]
        ];

        return @file_get_contents($url, false, stream_context_create($opts));
    }
}
