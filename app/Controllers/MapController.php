<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Models\GeotechnicalRepository;
use App\Services\BoundaryService;
use Throwable;

final class MapController
{
    public function data(string $logContext = 'Map', bool $includeOutsideBoundary = false): array
    {
        $database = null;
        $ownsTransaction = false;

        try {
            $database = Connection::get();
            if (!$database) {
                return ['boreholes' => [], 'recordsAvailable' => false, 'catalogueTruncated' => false];
            }

            if (!$database->inTransaction()) {
                $database->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                $database->beginTransaction();
                $ownsTransaction = true;
            }

            $repository = new GeotechnicalRepository($database);

            $boundary = new BoundaryService();
            $window = $repository->mapBoreholesWindow([-180.0, -90.0, 180.0, 90.0], 2001);
            $truncated = count($window) > 2000;
            if ($truncated) $window = array_slice($window, 0, 2000);
            $boreholes = [];
            foreach ($window as $borehole) {
                $inside = $boundary->contains($borehole['latitude'] ?? null, $borehole['longitude'] ?? null);
                $borehole['boundary_status'] = $inside ? 'inside' : 'outside';
                if ($inside || $includeOutsideBoundary) $boreholes[] = $borehole;
            }

            if ($ownsTransaction) {
                $database->commit();
            }

            return [
                'boreholes' => $boreholes,
                'recordsAvailable' => true,
                'catalogueTruncated' => $truncated,
            ];
        } catch (Throwable $error) {
            if ($ownsTransaction && $database !== null && $database->inTransaction()) {
                $database->rollBack();
            }
            error_log($logContext . ': ' . $error->getMessage());

            return ['boreholes' => [], 'recordsAvailable' => false, 'catalogueTruncated' => false];
        }
    }
}
