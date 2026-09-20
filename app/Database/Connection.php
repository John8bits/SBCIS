<?php

declare(strict_types=1);

namespace App\Database;

use Config\DatabaseConfig;
use PDO;

final class Connection
{
    private static ?PDO $connection = null;

    public static function get(): ?PDO
    {
        if (self::$connection === null) self::$connection = DatabaseConfig::connect();
        return self::$connection;
    }
}
