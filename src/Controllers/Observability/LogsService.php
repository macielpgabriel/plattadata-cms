<?php

declare(strict_types=1);

namespace App\Controllers\Observability;

use App\Core\Database;

final class LogsService
{
    public function clearAllLogs(): bool
    {
        $logFiles = [
            'app.log',
            'php_errors.log',
            'setup.log',
        ];

        $storagePath = base_path('storage/logs');
        $success = true;

        foreach ($logFiles as $file) {
            $path = $storagePath . '/' . $file;
            if (is_file($path)) {
                if (@file_put_contents($path, '') === false) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    public function getRecentLogs(int $limit = 100): array
    {
        $path = base_path('storage/logs/app.log');
        
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return [];
        }

        $logs = array_slice($lines, -$limit);
        
        return array_map(function($line) {
            return ['line' => $line];
        }, $logs);
    }

    public function getLogsCount(): array
    {
        $storagePath = base_path('storage/logs');
        $counts = [];

        $files = ['app.log', 'php_errors.log', 'setup.log', 'error.log'];
        
        foreach ($files as $file) {
            $path = $storagePath . '/' . $file;
            $counts[$file] = is_file($path) ? count(file($path)) : 0;
        }

        return $counts;
    }
}