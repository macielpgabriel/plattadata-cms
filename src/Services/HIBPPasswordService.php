<?php

declare(strict_types=1);

namespace App\Services;

final class HIBPPasswordService
{
    private const API_URL = 'https://api.pwnedpasswords.com/range/';
    private const TIMEOUT = 10;

    public function isPwned(string $password): bool
    {
        if (!config('app.security.check_pwned_passwords', true)) {
            return false;
        }

        $sha1 = strtoupper(sha1($password));
        $prefix = substr($sha1, 0, 5);
        $suffix = substr($sha1, 5);

        $response = $this->queryApi($prefix);
        if ($response === null) {
            return false;
        }

        foreach (explode("\n", $response) as $line) {
            $parts = explode(':', trim($line));
            if (count($parts) === 2 && strtoupper($parts[0]) === $suffix) {
                return true;
            }
        }

        return false;
    }

    private function queryApi(string $prefix): ?string
    {
        $url = self::API_URL . $prefix;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Plattadata-CMS',
                'timeout' => self::TIMEOUT,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return null;
        }

        return $body;
    }
}
