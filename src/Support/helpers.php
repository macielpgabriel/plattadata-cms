<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

function normalize_cnaes_secundarios(array|string|null $data): array
{
    if (empty($data)) {
        return [];
    }
    
    // If it's already a simple array of CNAEs (not wrapped in an object), return as-is
    if (is_array($data)) {
        $first = reset($data);
        if (is_string($first) || (is_array($first) && (isset($first['codigo']) || isset($first['code']) || isset($first['cnae'])))) {
            return array_slice($data, 0, 15);
        }
    }
    
    if (is_string($data)) {
        $decoded = json_decode($data, true);
        if (is_array($decoded)) {
            $data = $decoded;
        } else {
            return [];
        }
    }
    
    if (!is_array($data)) {
        return [];
    }
    
    // Normalize all possible field names to 'cnaes_secundarios'
    if (isset($data['atividades_secundarias']) && !isset($data['cnaes_secundarios'])) {
        $data['cnaes_secundarios'] = $data['atividades_secundarias'];
    }
    if (isset($data['cnaes']) && !isset($data['cnaes_secundarios'])) {
        $data['cnaes_secundarios'] = $data['cnaes'];
    }
    
    $cnaes = $data['cnaes_secundarios'] ?? [];
    
    if (!is_array($cnaes)) {
        return [];
    }
    
    return array_slice($cnaes, 0, 15);
}

function digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?: '';
}

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__, 2);
    return $path ? $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $base;
}

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

function config(string $key, mixed $default = null): mixed
{
    static $config = [];
    static $cacheLoaded = false;

    // Load cache file if exists and not yet loaded
    if (!$cacheLoaded) {
        $cacheFile = base_path('storage/cache/config.php');
        if (is_file($cacheFile)) {
            $config = require $cacheFile;
            $cacheLoaded = true;
        }
    }

    $segments = explode('.', $key);
    $file = $segments[0];

    // If cache is loaded, use it directly
    if ($cacheLoaded && isset($config[$file])) {
        $value = $config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    // Fallback to loading file directly
    if (!isset($config[$file])) {
        $filePath = base_path('config/' . $file . '.php');
        if (is_file($filePath)) {
            $config[$file] = require $filePath;
        } else {
            return $default;
        }
    }

    $value = $config;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path, int $statusCode = 302)
{
    http_response_code($statusCode);
    header('Location: ' . $path);
    exit;
}

function site_setting(string $key, ?string $default = null): ?string
{
    return \App\Support\SiteSettings::get($key, $default);
}

function view(string $template, array $data = []): void
{
    \App\Core\View::render($template, $data);
}

function mask_email(string $email): string
{
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return $email;
    }

    [$user, $domain] = explode('@', $email);
    $len = strlen($user);
    $show = max(1, (int) floor($len / 2));

    return substr($user, 0, $show) . str_repeat('*', $len - $show) . '@' . $domain;
}

function _minify_css(string $css): string
{
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    $css = preg_replace('/\s*({|}|:|;|,|>|~|\+|!|=)\s*/', '$1', $css);
    $css = preg_replace('/;}/', '}', $css);
    return trim($css);
}

function _minify_js(string $js): string
{
    $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);
    $js = preg_replace('/\/\/.*$/m', '', $js);
    $js = preg_replace('/\s+/', ' ', $js);
    $js = preg_replace('/\s*([{}();,:])\s*/', '$1', $js);
    return trim($js);
}

function asset_v(string $path): string
{
    $fullPath = base_path('public/' . ltrim($path, '/'));
    if (!is_file($fullPath)) {
        return '/' . ltrim($path, '/') . '?v=' . time();
    }

    $version = filemtime($fullPath);
    return '/' . ltrim($path, '/') . '?v=' . $version;
}

function tryParseTimestamp(?string $datetime): int|false
{
    if (!$datetime) {
        return false;
    }
    return strtotime($datetime);
}

function format_date(?string $date, bool $short = false): string
{
    $ts = tryParseTimestamp($date);
    if ($ts === false) {
        return e($date) ?: '-';
    }
    return $short ? date('d/m', $ts) : date('d/m/Y', $ts);
}

function format_datetime(?string $datetime, bool $withSeconds = false): string
{
    $ts = tryParseTimestamp($datetime);
    if ($ts === false) {
        return e($datetime) ?: '-';
    }
    return $withSeconds ? date('d/m/Y H:i:s', $ts) : date('d/m/Y H:i', $ts);
}

function format_time(?string $time): string
{
    $ts = tryParseTimestamp($time);
    if ($ts === false) {
        return e($time) ?: '-';
    }
    return date('H:i', $ts);
}

function format_time_full(?string $time): string
{
    $ts = tryParseTimestamp($time);
    if ($ts === false) {
        return e($time) ?: '-';
    }
    return date('H:i:s', $ts);
}

function format_relative(?string $datetime): string
{
    $ts = tryParseTimestamp($datetime);
    if ($ts === false) {
        return e($datetime) ?: '-';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'agora';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' h';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . ' d';
    }
    return format_date($datetime);
}

function format_money(float|int|string|null $value, string $symbol = 'R$', int $decimals = 2): string
{
    if ($value === null || $value === '')
        return '-';
    return $symbol . ' ' . number_format((float) $value, $decimals, ',', '.');
}

function format_number(float|int|string|null $value, int $decimals = 0): string
{
    if ($value === null || $value === '')
        return '-';
    return number_format((float) $value, $decimals, ',', '.');
}

function slugify(string $text): string
{
    $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function cnpj_with_copy(string $cnpj, bool $formatted = true): string
{
    $plain = preg_replace('/\D/', '', $cnpj);
    $display = $formatted ? format_cnpj($cnpj) : $cnpj;
    $id = 'cnpj_' . substr(md5($plain), 0, 8);
    
    return '<span class="cnpj-copy-wrapper d-inline-flex align-items-center gap-2">' .
        '<span id="' . $id . '">' . e($display) . '</span>' .
        '<button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="copyToClipboard(\'' . $id . '\', \'' . e($plain) . '\', this)" title="Copiar CNPJ">' .
            '<i class="bi bi-clipboard"></i>' .
        '</button>' .
    '</span>';
}

function format_cnpj(string $cnpj): string
{
    $clean = preg_replace('/\D/', '', $cnpj);
    if (strlen($clean) !== 14) {
        return $cnpj;
    }
    return substr($clean, 0, 2) . '.' . substr($clean, 2, 3) . '.' . substr($clean, 5, 3) . '/' . substr($clean, 8, 4) . '-' . substr($clean, 12, 2);
}

function is_api_request(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    
    return str_contains($path, '/api/') || 
           ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' ||
           str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}
