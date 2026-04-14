<?php

declare(strict_types=1);

namespace App\Services\Setup;

use PDO;
use PDOException;

final class DatabaseSetupService
{
    private ?string $lastConnectionError = null;

    public function isDatabaseReady(): bool
    {
        return $this->connectApplicationDatabase() !== null;
    }

    public function getLastConnectionError(): ?string
    {
        return $this->lastConnectionError;
    }

    public function connectApplicationDatabase(): ?PDO
    {
        $this->lastConnectionError = null;

        $host = (string) env('DB_HOST', 'localhost');
        $port = (string) env('DB_PORT', '3306');
        $name = (string) env('DB_NAME', '');
        $user = (string) env('DB_USER', '');
        $pass = (string) env('DB_PASS', '');

        if ($name === '' || $user === '') {
            $this->lastConnectionError = 'Conexão da aplicação não configurada: DB_NAME/DB_USER vazios.';
            return null;
        }

        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            $this->lastConnectionError = $exception->getMessage();
            return null;
        }
    }

    public function createDatabaseAndUserIfConfigured(): void
    {
        $autoProvision = filter_var(env('DB_AUTO_PROVISION', 'false'), FILTER_VALIDATE_BOOLEAN);
        if (!$autoProvision) {
            return;
        }

        $rootUser = (string) env('DB_ROOT_USER', '');
        $rootPass = (string) env('DB_ROOT_PASS', '');
        $rootHost = (string) env('DB_ROOT_HOST', env('DB_HOST', 'localhost'));
        $rootPort = (string) env('DB_ROOT_PORT', env('DB_PORT', '3306'));

        if ($rootUser === '') {
            return;
        }

        $dbName = (string) env('DB_NAME', '');
        $dbUser = (string) env('DB_USER', '');
        $dbPass = (string) env('DB_PASS', '');

        if ($dbName === '' || $dbUser === '') {
            return;
        }

        try {
            $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $rootHost, $rootPort);
            $rootPdo = new PDO($dsn, $rootUser, $rootPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $safeDb = $this->quoteIdentifier($dbName);
            $rootPdo->exec('CREATE DATABASE IF NOT EXISTS ' . $safeDb . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            $escapedUser = str_replace("'", "''", $dbUser);
            $escapedPass = str_replace("'", "''", $dbPass);

            $rootPdo->exec("CREATE USER IF NOT EXISTS '" . $escapedUser . "'@'localhost' IDENTIFIED BY '" . $escapedPass . "'");
            $rootPdo->exec("CREATE USER IF NOT EXISTS '" . $escapedUser . "'@'%' IDENTIFIED BY '" . $escapedPass . "'");
            $rootPdo->exec("ALTER USER '" . $escapedUser . "'@'localhost' IDENTIFIED BY '" . $escapedPass . "'");
            $rootPdo->exec("ALTER USER '" . $escapedUser . "'@'%' IDENTIFIED BY '" . $escapedPass . "'");
            $rootPdo->exec('GRANT ALL PRIVILEGES ON ' . $safeDb . ".* TO '" . $escapedUser . "'@'localhost'");
            $rootPdo->exec('GRANT ALL PRIVILEGES ON ' . $safeDb . ".* TO '" . $escapedUser . "'@'%'");
            $rootPdo->exec('FLUSH PRIVILEGES');
        } catch (PDOException $exception) {
        }
    }

    private function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}