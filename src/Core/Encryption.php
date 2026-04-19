<?php

declare(strict_types=1);

namespace App\Core;

final class Encryption
{
    private static ?string $key = null;
    private static ?string $method = null;

    private const METHOD = 'aes-256-gcm';

    public static function init(): void
    {
        $key = env('ENCRYPTION_KEY', '');
        
        if (empty($key)) {
            $key = env('APP_KEY', '');
            if (strlen($key) < 32) {
                $key = hash('sha256', $key, true);
            } else {
                $key = substr($key, 0, 32);
            }
        } else {
            $key = substr($key, 0, 32);
        }
        
        self::$key = $key;
        self::$method = self::METHOD;
    }

    public static function encrypt(string $plaintext): string
    {
        if (self::$key === null) {
            self::init();
        }

        if (empty($plaintext)) {
            return '';
        }

        $iv = openssl_random_pseudo_bytes(12);
        $tag = '';
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::$method,
            self::$key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            return $plaintext;
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encrypted): string
    {
        if (self::$key === null) {
            self::init();
        }

        if (empty($encrypted)) {
            return '';
        }

        $data = base64_decode($encrypted, true);
        if ($data === false) {
            return $encrypted;
        }

        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::$method,
            self::$key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext !== false ? $plaintext : $encrypted;
    }

    public static function hash(string $data): string
    {
        return hash('sha256', $data);
    }

    public static function mask(string $value, int $visible = 4): string
    {
        if (empty($value) || strlen($value) <= $visible * 2) {
            return $value;
        }

        $length = strlen($value);
        return substr($value, 0, $visible) . str_repeat('*', $length - $visible * 2) . substr($value, -$visible);
    }
}