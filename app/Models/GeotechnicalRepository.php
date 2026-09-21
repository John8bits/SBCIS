<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Services\InterpolationResultStore;
use App\Services\AuditLogger;
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

    public function mapBoreholesWindow(array $bbox, int $limit = 2001, string $search = ''): array
    {
        return sbcis_fetch_map_boreholes_window($this->database, $bbox, $limit, $search);
    }

    public function recentBoreholes(?int $limit = 50): array
    {
        return sbcis_fetch_recent_boreholes($this->database, $limit);
    }

    public function find(int $boreholeId): ?array
    {
        return sbcis_fetch_geotechnical_record($this->database, $boreholeId);
    }

    public function create(array $input, ?int $adminId = null): int
    {
        $id = sbcis_create_geotechnical_record($this->database, $input);
        if ($adminId !== null) AuditLogger::record($this->database, 'create', 'borehole', $id,
            ['borehole_code'=>(string)($input['borehole_code'] ?? '')], $adminId);
        $this->invalidateInterpolation();
        return $id;
    }

    public function update(int $boreholeId, array $input, ?int $adminId = null): void
    {
        $before = sbcis_fetch_geotechnical_record($this->database, $boreholeId);
        sbcis_update_geotechnical_record($this->database, $boreholeId, $input);
        if ($adminId !== null) AuditLogger::record($this->database, 'update', 'borehole', $boreholeId, [
            'previous_lock_version'=>(int)($before['lock_version'] ?? 0),
            'new_lock_version'=>(int)($before['lock_version'] ?? 0) + 1,
            'borehole_code'=>(string)($input['borehole_code'] ?? ''),
        ], $adminId);
        $this->invalidateInterpolation();
    }

    public function delete(int $boreholeId): bool
    {
        $deleted = sbcis_delete_geotechnical_record($this->database, $boreholeId);
        if ($deleted) $this->invalidateInterpolation();
        return $deleted;
    }

    public function archive(int $boreholeId, int $adminId): bool
    {
        $record = sbcis_fetch_geotechnical_record($this->database, $boreholeId);
        $archived = sbcis_archive_geotechnical_record($this->database, $boreholeId, $adminId);
        if ($archived) {
            AuditLogger::record($this->database, 'archive', 'borehole', $boreholeId,
                ['borehole_code'=>$record['borehole_code'] ?? null], $adminId);
            $this->invalidateInterpolation();
        }
        return $archived;
    }

    public function restore(int $boreholeId, int $adminId): bool
    {
        $record = sbcis_fetch_geotechnical_record($this->database, $boreholeId, true);
        $restored = sbcis_restore_geotechnical_record($this->database, $boreholeId);
        if ($restored) {
            AuditLogger::record($this->database, 'restore', 'borehole', $boreholeId,
                ['borehole_code'=>$record['borehole_code'] ?? null], $adminId);
            $this->invalidateInterpolation();
        }
        return $restored;
    }

    private function invalidateInterpolation(): void
    {
        try {
            $revision = (new InterpolationRepository($this->database))->revision();
            (new InterpolationResultStore())->markOutdated($revision);
        } catch (Throwable $error) {
            // CRUD is already committed. Source-hash checks still prevent stale publication.
            error_log('Interpolation invalidation: ' . $error->getMessage());
        }
    }
}
