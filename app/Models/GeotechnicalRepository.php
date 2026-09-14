<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class GeotechnicalRepository
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
        require_once __DIR__ . '/geotechnical_data.php';
    }

    public function mapBoreholes(): array
    {
        return sbcis_fetch_map_boreholes($this->database);
    }

    public function recentBoreholes(?int $limit = 50): array
    {
        return sbcis_fetch_recent_boreholes($this->database, $limit);
    }

    public function find(int $boreholeId): ?array
    {
        return sbcis_fetch_geotechnical_record($this->database, $boreholeId);
    }

    public function create(array $input): int
    {
        return sbcis_create_geotechnical_record($this->database, $input);
    }

    public function update(int $boreholeId, array $input): void
    {
        sbcis_update_geotechnical_record($this->database, $boreholeId, $input);
    }

    public function delete(int $boreholeId): bool
    {
        return sbcis_delete_geotechnical_record($this->database, $boreholeId);
    }
}
