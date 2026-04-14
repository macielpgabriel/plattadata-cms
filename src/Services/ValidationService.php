<?php

declare(strict_types=1);

namespace App\Services;

final class ValidationService
{
    private function sanitizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function formatDigits(string $value, string $separator, int ...$lengths): string
    {
        $digits = $this->sanitizeDigits($value);
        $result = '';
        $offset = 0;
        foreach ($lengths as $len) {
            if ($offset >= strlen($digits)) break;
            $result .= substr($digits, $offset, $len) . $separator;
            $offset += $len;
        }
        return rtrim($result, $separator);
    }

    public function email(string $email): bool
    {
        $email = trim($email);
        if ($email === '') {
            return false;
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function sanitizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function name(string $name): bool
    {
        $name = trim($name);
        if (strlen($name) < 2) {
            return false;
        }
        return preg_match('/^[\p{L}\s\.\-\']+$/u', $name) === 1;
    }

    public function sanitizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/[^\p{L}\s\.\-\']/u', '', $name);
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    public function cep(string $cep): bool
    {
        $cep = $this->sanitizeDigits($cep);
        return strlen($cep) === 8;
    }

    public function sanitizeCep(string $cep): string
    {
        return $this->sanitizeDigits($cep);
    }

    public function formatCep(string $cep): string
    {
        return $this->formatDigits($cep, '-', 5, 3);
    }

    public function ddd(string $ddd): bool
    {
        $ddd = $this->sanitizeDigits($ddd);
        return strlen($ddd) === 2 && $ddd >= 11 && $ddd <= 99;
    }

    public function sanitizeDdd(string $ddd): string
    {
        return $this->sanitizeDigits($ddd);
    }

    public function phone(string $phone): bool
    {
        $phone = $this->sanitizeDigits($phone);
        return strlen($phone) >= 10 && strlen($phone) <= 11;
    }

    public function sanitizePhone(string $phone): array
    {
        $phone = $this->sanitizeDigits($phone);
        $ddd = strlen($phone) >= 10 ? substr($phone, 0, 2) : null;
        $number = strlen($phone) >= 10 ? substr($phone, 2) : $phone;
        return [
            'ddd' => $ddd,
            'number' => $number,
            'full' => $phone
        ];
    }

    public function url(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }
        return filter_var($url, FILTER_SANITIZE_URL) ?: $url;
    }

    public function cnpj(string $cnpj): bool
    {
        $cnpj = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cnpj) ?? '');
        if (strlen($cnpj) !== 14) {
            return false;
        }
        if (preg_match('/^([A-Z0-9])\1{13}$/', $cnpj)) {
            return false;
        }

        $weightsFirst = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $digit1 = $this->calculateCheckDigit($cnpj, $weightsFirst, 12);

        $weightsSecond = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $digit2 = $this->calculateCheckDigit($cnpj, $weightsSecond, 13);

        return $cnpj[12] === (string)$digit1 && $cnpj[13] === (string)$digit2;
    }

    private function calculateCheckDigit(string $cnpj, array $weights, int $length): int
    {
        $sum = 0;
        for ($i = 0; $i < $length; $i++) {
            $charValue = ord($cnpj[$i]) - 48;
            $sum += $charValue * $weights[$i];
        }
        $remainder = $sum % 11;
        return $remainder < 2 ? 0 : 11 - $remainder;
    }

    public function cpf(string $cpf): bool
    {
        $cpf = $this->sanitizeDigits($cpf);
        if (strlen($cpf) !== 11) {
            return false;
        }
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += (int)$cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int)$cpf[$c] !== $d) {
                return false;
            }
        }
        return true;
    }

    public function sanitizeCpf(string $cpf): string
    {
        return $this->sanitizeDigits($cpf);
    }

    public function formatCpf(string $cpf): string
    {
        return $this->formatDigits($cpf, '.', 3, 3, 3, 2);
    }

    public function cnpjOrCpf(string $document): bool
    {
        return $this->cnpj($document) || $this->cpf($document);
    }

    public function sanitizeDocument(string $document): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $document) ?? '');
    }

    public function positiveInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    public function positiveFloat(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $float = (float) $value;
        return $float > 0 ? $float : null;
    }

    public function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on', 'sim'], true);
        }
        return (bool) $value;
    }

    public function safeString(string $value, int $maxLength = 255): string
    {
        $value = trim($value);
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }
        return $value;
    }

    public function slug(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $text);
        $text = preg_replace('/[\s\-]+/', '-', $text);
        $text = trim($text, '-');
        return $text;
    }

    public function date(string $date, string $format = 'Y-m-d'): ?string
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date ? $date : null;
    }

    public function dateBr(string $date): ?string
    {
        return $this->date($date, 'd/m/Y');
    }

    public function dateIso(string $date): ?string
    {
        return $this->date($date, 'Y-m-d');
    }
}
