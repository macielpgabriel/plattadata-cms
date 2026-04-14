<?php

declare(strict_types=1);

namespace App\Services;

trait HttpFetchTrait
{
    protected int $timeout = 5;

    protected function fetchJson(string $url): ?array
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => "Accept: application/json\r\nUser-Agent: CMS-Empresarial/1.0\r\n"
            ]
        ];

        $content = @file_get_contents($url, false, stream_context_create($opts));

        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function fetchJsonFast(string $url): ?array
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 3,
                'header' => "Accept: application/json\r\nUser-Agent: CMS-Empresarial/1.0\r\n"
            ]
        ];

        $content = @file_get_contents($url, false, stream_context_create($opts));

        if ($content === false) {
            return null;
        }

        return json_decode($content, true);
    }

    private function fetchJsonWithRetry(string $url, int $maxRetries = 2): ?array
    {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'timeout' => $this->timeout,
                    'header' => "Accept: application/json\r\nUser-Agent: CMS-Empresarial/1.0\r\n"
                ]
            ];

            $content = @file_get_contents($url, false, stream_context_create($opts));

            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            $attempt++;
            if ($attempt < $maxRetries) {
                usleep(100000);
            }
        }

        return null;
    }

    protected function getCacheExpiry(int $ttlDays): string
    {
        return date('Y-m-d H:i:s', strtotime("+{$ttlDays} days"));
    }

    protected function now(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }
}