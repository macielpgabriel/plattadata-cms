<?php

declare(strict_types=1);

namespace App\Services\Cnpj;

use App\Core\Logger;

final class CnpjValidationService
{
    public function sanitize(string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj);
    }

    public function validate(string $cnpj): bool
    {
        $cnpj = $this->sanitize($cnpj);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        if (preg_match('/^(\d)\1*$/', $cnpj)) {
            return false;
        }

        $digit1 = $this->calculateCheckDigit($cnpj, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], 12);
        $digit2 = $this->calculateCheckDigit($cnpj, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], 13);

        return $cnpj[12] == $digit1 && $cnpj[13] == $digit2;
    }

    private function calculateCheckDigit(string $cnpj, array $weights, int $length): int
    {
        $sum = 0;
        for ($i = 0; $i < $length; $i++) {
            $sum += (int) $cnpj[$i] * $weights[$i];
        }
        
        $remainder = $sum % 11;
        return $remainder < 2 ? 0 : 11 - $remainder;
    }

    public function format(string $cnpj): string
    {
        $cnpj = $this->sanitize($cnpj);
        
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }

        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . 
               substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }
}