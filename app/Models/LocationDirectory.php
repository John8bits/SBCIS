<?php

declare(strict_types=1);

namespace App\Models;

final class LocationDirectory
{
    public function __construct()
    {
        require_once __DIR__ . '/locations.php';
    }

    public function all(): array
    {
        return sbcis_locations();
    }

    public function normalizeName(string $name): string
    {
        return sbcis_location_key($name);
    }

    public function isValid(array $data): bool
    {
        return sbcis_validate_locations($data);
    }
}
