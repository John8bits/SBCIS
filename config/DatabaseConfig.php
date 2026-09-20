<?php

declare(strict_types=1);

namespace Config;

use PDO;

final class DatabaseConfig
{
    public static function connect(): PDO
    {
        $localFile = __DIR__ . '/database.local.php';
        $local = is_file($localFile) ? require $localFile : [];
        $setting = static function (string $environment, string $localKey, string $default) use ($local): string {
            $value = getenv($environment);
            if (is_string($value) && $value !== '') return $value;
            return is_string($local[$localKey] ?? null) ? $local[$localKey] : $default;
        };
        $host = $setting('SBCIS_DB_HOST', 'host', 'localhost');
        $database = $setting('SBCIS_DB_NAME', 'database', 'sbcdb');
        $username = $setting('SBCIS_DB_USER', 'username', 'root');
        $password = getenv('SBCIS_DB_PASSWORD');
        if ($password === false) $password = is_string($local['password'] ?? null) ? $local['password'] : '';
        $dataSourceName = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $host,
            $database
        );

        return new PDO(
            $dataSourceName,
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
