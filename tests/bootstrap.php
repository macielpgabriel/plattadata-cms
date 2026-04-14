<?php

declare(strict_types=1);

use App\Core\Env;

require dirname(__DIR__) . '/src/Support/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Env::load(dirname(__DIR__) . '/.env');

// Mantem os testes deterministas.
$_ENV['PASSWORD_MIN_LENGTH'] = '10';
$_SERVER['PASSWORD_MIN_LENGTH'] = '10';
