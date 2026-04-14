#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

require dirname(__DIR__) . '/src/Support/helpers.php';

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}

$startTime = microtime(true);
$limit = (int) ($argv[1] ?? 50);
$offset = (int) ($argv[2] ?? 0);

echo "=== Weather Sync Worker ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "Limit: $limit, Offset: $offset\n\n";

$cptecService = new App\Services\CptecService();
$municipalityRepo = new App\Repositories\MunicipalityRepository();

$total = 0;
$success = 0;
$failed = 0;

try {
    $municipalities = $municipalityRepo->getAllForWeatherSync($limit, $offset);
    $total = count($municipalities);

    echo "Processing $total municipalities...\n";

    foreach ($municipalities as $muni) {
        $ibgeCode = (int) $muni['ibge_code'];
        $name = $muni['name'] ?? 'Unknown';
        $state = $muni['state_uf'] ?? '';

        echo "  [$ibgeCode] $name/$state... ";

        try {
            $weather = $cptecService->getWeather($ibgeCode, true);

            if ($weather !== null && (!empty($weather['current']) || !empty($weather['forecast']))) {
                $success++;
                echo "OK";
                if (!empty($weather['current']['temp'])) {
                    echo " ({$weather['current']['temp']}°C)";
                } elseif (!empty($weather['current']['max_temp'])) {
                    echo " ({$weather['current']['max_temp']}°C)";
                }
            } else {
                $failed++;
                echo "NO DATA";
            }
        } catch (Exception $e) {
            $failed++;
            echo "ERROR: " . $e->getMessage();
        }

        echo "\n";

        usleep(100000);
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "\n=== Summary ===\n";
    echo "Total: $total | Success: $success | Failed: $failed\n";
    echo "Elapsed: {$elapsed}s\n";

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}