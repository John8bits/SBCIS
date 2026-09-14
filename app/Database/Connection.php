<?php

declare(strict_types=1);

namespace App\Database;

use Config\DatabaseConfig;
use PDO;

final class Connection
{
    public static function get(): ?PDO
    {
        return DatabaseConfig::connect();
    }
}
