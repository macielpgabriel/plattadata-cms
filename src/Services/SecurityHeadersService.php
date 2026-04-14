<?php

declare(strict_types=1);

namespace App\Services;

final class SecurityHeadersService
{
    public static function apply(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), hid=(), identity=(), magnetometer=(), microphone=(), midi=(), payment=(), push=(), serial=(), storage=(), usb=(), vr=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Cross-Origin-Embedder-Policy: unsafe-none');

        $csp = trim((string) config('app.security.headers.csp', ''));
        if ($csp !== '') {
            $nonce = base64_encode(random_bytes(16));
            $_SERVER['CSP_NONCE'] = $nonce;
            $csp = str_replace('{nonce}', $nonce, $csp);
            header('Content-Security-Policy: ' . $csp);
        }

        $hstsEnabled = (bool) config('app.security.headers.hsts', true);
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if ($hstsEnabled && $isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
}
