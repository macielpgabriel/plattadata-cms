<?php

declare(strict_types=1);

namespace App\Services;

final class OpenCnpjService
{
    private const BASE_URL = 'https://api.opencnpj.org';

    public function fetchCompanyData(string $cnpj): ?array
    {
        $cnpjDigits = preg_replace('/[^0-9]/', '', $cnpj);
        
        if (strlen($cnpjDigits) !== 14) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: Plattadata/1.0\r\n"
            ]
        ]);

        $url = self::BASE_URL . '/v1/cnpj/' . $cnpjDigits;
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        
        if (empty($data['data'])) {
            return null;
        }

        return $data['data'];
    }

    public function enrichFromOpenCnpj(array $company): array
    {
        if (empty($company['cnpj'])) {
            return $company;
        }

        $data = $this->fetchCompanyData($company['cnpj']);
        
        if (!$data) {
            return $company;
        }

        if (empty($company['phone']) && !empty($data['telefones'])) {
            $phones = $data['telefones'];
            if (is_array($phones) && count($phones) > 0) {
                $firstPhone = is_array($phones[0]) ? $phones[0] : ['numero' => $phones[0]];
                $company['phone'] = $firstPhone['numero'] ?? '';
            }
        }

        if (empty($company['email']) && !empty($data['email'])) {
            $company['email'] = $data['email'];
        }

        if (empty($company['website']) && !empty($data['urls'])) {
            $urls = $data['urls'];
            if (is_array($urls) && count($urls) > 0) {
                $company['website'] = is_string($urls[0]) ? $urls[0] : ($urls[0]['url'] ?? '');
            }
        }

        return $company;
    }
}