<?php

declare(strict_types=1);

namespace Config;

use PDO;

final class DatabaseConfig
{
    private const HOST = 'localhost';
    private const DATABASE = 'sbcdb';
    private const USERNAME = 'root';
    private const PASSWORD = '';

    public static function connect(): PDO
    {
        $dataSourceName = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            self::HOST,
            self::DATABASE
        );

        return new PDO(
            $dataSourceName,
            self::USERNAME,
            self::PASSWORD,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
