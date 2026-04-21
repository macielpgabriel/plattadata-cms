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
\App\Services\SecurityService::applyRequestProtections();
\App\Services\AccessLogService::log($_SERVER['REQUEST_URI'] ?? '/');

// Setup e Retenção
$setupLockFile = base_path('storage/.setup_completed');
$setupService = new SetupService();
if (!is_file($setupLockFile) || !$setupService->hasCriticalTables()) {
    $setupService->runInitialSetup();
}

$retentionLockFile = base_path('storage/.retention_last_run');
if ((bool) config('app.lgpd.retention.enabled', true)) {
    $lastRun = is_file($retentionLockFile) ? trim((string) @file_get_contents($retentionLockFile)) : '';
    if ($lastRun !== date('Y-m-d')) {
        (new RetentionService())->runDaily();
    }
}

// IP Blocklist
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (\App\Services\IpBlocklistService::isBlocked($clientIp)) {
    $blockedList = \App\Services\IpBlocklistService::getBlockedList();
    $blocked = array_filter($blockedList, fn($item) => $item['ip'] === $clientIp);
    $blocked = array_values($blocked)[0] ?? null;

    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    $expiresAt = $blocked['expires_at'] ?? null;
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Bloqueado</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #1a1a2e; color: #eee; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { text-align: center; padding: 2rem; max-width: 500px; }
        h1 { color: #e74c3c; margin-bottom: 1rem; }
        .timer { font-size: 2.5rem; font-weight: bold; color: #f39c12; margin: 1rem 0; }
        .message { color: #aaa; line-height: 1.6; }
        .refresh-btn { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 1rem; margin-top: 1rem; }
        .refresh-btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Acesso Bloqueado</h1>
        <?php if ($blocked && $blocked['reason']): ?>
            <p class="message"><?= htmlspecialchars($blocked['reason']) ?></p>
        <?php endif; ?>
        <?php if ($expiresAt): ?>
            <p class="message">Tente novamente em:</p>
            <div class="timer" data-expires="<?= $expiresAt ?>">--:--</div>
            <button class="refresh-btn" onclick="location.reload()">Atualizar</button>
            <script>
                function updateTimer() {
                    const el = document.querySelector('.timer');
                    const expires = new Date(el.dataset.expires).getTime();
                    const now = Date.now();
                    const diff = Math.max(0, Math.floor((expires - now) / 1000));
                    const mins = Math.floor(diff / 60);
                    const secs = diff % 60;
                    el.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
                    if (diff <= 0) location.reload();
                }
                setInterval(updateTimer, 1000);
                updateTimer();
            </script>
        <?php else: ?>
            <p class="message">Seu acesso foi bloqueado permanentemente.</p>
        <?php endif; ?>
    </div>
</body>
</html>
    <?php
    exit;
}

// Cleanup 1%
if (random_int(1, 100) === 1) \App\Services\IpBlocklistService::cleanup();
