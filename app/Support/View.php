<?php

declare(strict_types=1);

namespace App\Support;

final class View
{
    public static function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
