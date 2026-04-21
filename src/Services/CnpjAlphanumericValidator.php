<?php

declare(strict_types=1);

namespace App\Services;

final class CnpjAlphanumericValidator
{
    private const ALLOWED_CHARS = '0123456789ABCDEFGHJJKLMNPQRSTUVWXYZ';
    
    private const EXCLUDED_LETTERS = ['I', 'O', 'U', 'Q', 'F'];
    
    private const WEIGHTS = [
        1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6,
        6 => 7, 7 => 8, 8 => 9, 9 => 2, 10 => 3,
        11 => 4, 12 => 5, 13 => 6, 14 => 7,
    ];

    public static function isValid(string $cnpj): bool
    {
        $cnpj = self::clean($cnpj);
        
        if (strlen($cnpj) !== 14) {
            return false;
        }
        
        if (!self::hasValidChars($cnpj)) {
            return false;
        }
        
        return self::verifyDigit($cnpj);
    }
    
    public static function clean(string $cnpj): string
    {
        return preg_replace('/[^A-Z0-9]/i', '', $cnpj);
    }
    
    public static function format(string $cnpj): string
    {
        $cnpj = self::clean($cnpj);
        
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }
        
        return substr($cnpj, 0, 2) . '.' . 
               substr($cnpj, 2, 3) . '.' . 
               substr($cnpj, 5, 3) . '/' . 
               substr($cnpj, 8, 4) . '-' . 
               substr($cnpj, 12, 2);
    }
    
    public static function isLegacy(string $cnpj): bool
    {
        $cnpj = self::clean($cnpj);
        
        return ctype_digit($cnpj);
    }
    
    public static function isAlphanumeric(string $cnpj): bool
    {
        $cnpj = self::clean($cnpj);
        
        return !ctype_digit($cnpj);
    }
    
    private static function hasValidChars(string $cnpj): bool
    {
        for ($i = 0; $i < 14; $i++) {
            $char = $cnpj[$i];
            
            if ($i < 12) {
                if (in_array($char, self::EXCLUDED_LETTERS, true)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    private static function toNumericValue(string $char): int
    {
        if (ctype_digit($char)) {
            return (int) $char;
        }
        
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $position = strpos($letters, $char);
        
        if ($position === false) {
            return 0;
        }
        
        return $position + 10;
    }
    
    private static function verifyDigit(string $cnpj): bool
    {
        $sum = 0;
        
        for ($i = 0; $i < 12; $i++) {
            $char = $cnpj[$i];
            $value = self::toNumericValue($char);
            $weight = self::WEIGHTS[$i + 1];
            $sum += $value * $weight;
        }
        
        $remainder = $sum % 11;
        $firstDigit = ($remainder === 0) ? 0 : 11 - $remainder;
        
        if ((int) $cnpj[12] !== $firstDigit) {
            return false;
        }
        
        $sum = 0;
        
        for ($i = 0; $i < 13; $i++) {
            $char = $cnpj[$i];
            $value = self::toNumericValue($char);
            $weight = self::WEIGHTS[$i + 2];
            $sum += $value * $weight;
        }
        
        $remainder = $sum % 11;
        $secondDigit = ($remainder === 0) ? 0 : 11 - $remainder;
        
        return (int) $cnpj[13] === $secondDigit;
    }
    
    public static function validateLegacy(string $cnpj): bool
    {
        $cnpj = self::clean($cnpj);
        
        if (strlen($cnpj) !== 14 || !ctype_digit($cnpj)) {
            return false;
        }
        
        $sum = 0;
        $weights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $cnpj[$i] * $weights[$i];
        }
        
        $remainder = $sum % 11;
        $firstDigit = ($remainder < 2) ? 0 : 11 - $remainder;
        
        if ((int) $cnpj[12] !== $firstDigit) {
            return false;
        }
        
        $sum = 0;
        $weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $cnpj[$i] * $weights[$i];
        }
        
        $remainder = $sum % 11;
        $secondDigit = ($remainder < 2) ? 0 : 11 - $remainder;
        
        return (int) $cnpj[13] === $secondDigit;
    }
    
    public static function validate(string $cnpj): bool
    {
        $cnpj = self::clean($cnpj);
        
        if (strlen($cnpj) !== 14) {
            return false;
        }
        
        if (self::isLegacy($cnpj)) {
            return self::validateLegacy($cnpj);
        }
        
        return self::verifyDigit($cnpj);
    }
}