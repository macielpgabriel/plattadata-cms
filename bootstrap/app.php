<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Session;
use App\Services\RetentionService;
use App\Services\SecurityHeadersService;
use App\Services\SetupService;

// 1. Carregar Helpers e Autoloader
require dirname(__DIR__) . '/src/Support/helpers.php';

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        $baseDir = dirname(__DIR__) . '/src/';
        if (!str_starts_with($class, $prefix)) return;
        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) require $file;
    });
}

// 2. Carregar Ambiente e Configurar Erros IMEDIATAMENTE
Env::load(dirname(__DIR__) . '/.env');

$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/php_errors.log';

ini_set('log_errors', '1');
ini_set('error_log', $logFile);

if (env('APP_DEBUG', 'false') === 'true') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// 3. Iniciar Sessão o mais cedo possível (evita "headers already sent")
Session::start();

// 4. Exception Handler Global
set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf("[%s] Uncaught Exception: %s in %s:%d", date('c'), $e->getMessage(), $e->getFile(), $e->getLine()));
    if (!headers_sent()) http_response_code(500);

    if (env('APP_DEBUG', 'false') === 'true') {
        echo "<h1>HTTP ERROR 500</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " on line " . $e->getLine() . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        echo "<h1>HTTP ERROR 500</h1><p>Ocorreu um erro interno. Tente novamente mais tarde.</p>";
    }
    exit;
});

set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) return false;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// 5. Outros Serviços
$timezone = (string) config('app.timezone', 'America/Sao_Paulo');
date_default_timezone_set(in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'America/Sao_Paulo');

SecurityHeadersService::apply();
\App\Services\AccessLogService::log($_SERVER['REQUEST_URI'] ?? '/');

// IP Blocklist
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (\App\Services\IpBlocklistService::isBlocked($clientIp)) {
    http_response_code(403);
    echo "Acesso bloqueado.";
    exit;
}

// Cleanup 1%
if (random_int(1, 100) === 1) \App\Services\IpBlocklistService::cleanup();

// Setup e Retenção
$setupLockFile = base_path('storage/.setup_completed');
if (!is_file($setupLockFile)) {
    (new SetupService())->runInitialSetup();
}

$retentionLockFile = base_path('storage/.retention_last_run');
if ((bool) config('app.lgpd.retention.enabled', true)) {
    $lastRun = is_file($retentionLockFile) ? trim((string) @file_get_contents($retentionLockFile)) : '';
    if ($lastRun !== date('Y-m-d')) {
        (new RetentionService())->runDaily();
    }
}

