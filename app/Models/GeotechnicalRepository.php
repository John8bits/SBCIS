<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Services\InterpolationResultStore;
use Throwable;

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
        $id = sbcis_create_geotechnical_record($this->database, $input);
        $this->invalidateInterpolation();
        return $id;
    }

    public function update(int $boreholeId, array $input): void
    {
        sbcis_update_geotechnical_record($this->database, $boreholeId, $input);
        $this->invalidateInterpolation();
    }

    public function delete(int $boreholeId): bool
    {
        $deleted = sbcis_delete_geotechnical_record($this->database, $boreholeId);
        if ($deleted) $this->invalidateInterpolation();
        return $deleted;
    }

    private function invalidateInterpolation(): void
    {
        try {
            (new InterpolationResultStore())->markOutdated();
        } catch (Throwable $error) {
            // CRUD is already committed. Source-hash checks still prevent stale publication.
            error_log('Interpolation invalidation: ' . $error->getMessage());
        }
    }
}
