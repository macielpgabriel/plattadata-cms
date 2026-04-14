<?php

declare(strict_types=1);

namespace App\Services;

final class WhatsAppService
{
    private const DDD_VALID = [
        11, 12, 13, 14, 15, 16, 17, 18, 19,
        21, 22, 24,
        27, 28,
        31, 32, 33, 34, 35, 37, 38,
        41, 42, 43, 44, 45, 46,
        51, 53, 54, 55,
        61,
        62, 63, 64,
        65, 66,
        67,
        68,
        69,
        71, 73, 74, 75, 77, 79,
        81, 82, 83, 84, 85, 86, 87, 88, 89,
        91, 92, 93, 94, 95, 96, 97, 98, 99,
    ];

    public function formatPhoneNumber(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        
        if ($digits === null || strlen($digits) < 10 || strlen($digits) > 13) {
            return null;
        }

        $ddd = (int) substr($digits, -11, -9);
        
        if (strlen($digits) === 10) {
            if (!in_array($ddd, self::DDD_VALID, true)) {
                return null;
            }
            return '55' . $digits;
        }

        if (strlen($digits) === 11) {
            if ($digits[0] !== '0' || $digits[1] !== '0' || $digits[2] !== '5' || $digits[3] !== '5') {
                return null;
            }
            return '55' . substr($digits, 2);
        }

        if (strlen($digits) === 12) {
            if ($digits[0] !== '0' || $digits[1] !== '0') {
                return null;
            }
            $withoutCountry = substr($digits, 2);
            $dddCheck = (int) substr($withoutCountry, 0, 2);
            if (!in_array($dddCheck, self::DDD_VALID, true)) {
                return null;
            }
            return '55' . $withoutCountry;
        }

        if (substr($digits, 0, 4) === '0055') {
            $withoutCountry = substr($digits, 4);
            $dddCheck = (int) substr($withoutCountry, 0, 2);
            if (!in_array($dddCheck, self::DDD_VALID, true)) {
                return null;
            }
            return '55' . $withoutCountry;
        }

        return null;
    }

    public function validatePhoneNumber(string $phone): bool
    {
        return $this->formatPhoneNumber($phone) !== null;
    }

    public function extractPhoneFromRawData(array $rawData): ?string
    {
        $phoneFields = [
            'telefone',
            'ddd_telefone_1',
            'ddd_telefone_2',
            'telefone_1',
            'telefone_2',
            'phone',
        ];

        foreach ($phoneFields as $field) {
            if (!empty($rawData[$field]) && is_string($rawData[$field])) {
                $phone = trim($rawData[$field]);
                if ($this->validatePhoneNumber($phone)) {
                    return $phone;
                }
            }
        }

        return null;
    }

    public function generateWhatsAppUrl(string $phone, ?string $message = null): ?string
    {
        $formatted = $this->formatPhoneNumber($phone);
        
        if ($formatted === null) {
            return null;
        }

        $url = 'https://wa.me/' . $formatted;
        
        if ($message !== null && trim($message) !== '') {
            $url .= '?text=' . rawurlencode(trim($message));
        }
        
        return $url;
    }

    public function getDefaultMessage(string $companyName, string $cnpj, ?string $appUrl = null): string
    {
        $base = "Olá! Vi os dados da empresa {$companyName} (CNPJ: {$cnpj})";
        
        if ($appUrl !== null) {
            $base .= " no PlattaData";
        }
        
        $base .= " e gostaria de mais informações.";
        
        return $base;
    }

    public function validateAndFormat(string $phone): array
    {
        $formatted = $this->formatPhoneNumber($phone);
        
        if ($formatted === null) {
            return [
                'valid' => false,
                'original' => $phone,
                'formatted' => null,
                'ddd' => null,
                'error' => 'Número de telefone inválido para WhatsApp no Brasil',
            ];
        }

        $ddd = (int) substr($formatted, 4, 2);
        
        return [
            'valid' => true,
            'original' => $phone,
            'formatted' => $formatted,
            'ddd' => $ddd,
            'whatsapp_url' => 'https://wa.me/' . $formatted,
        ];
    }
}
