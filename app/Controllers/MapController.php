<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Models\GeotechnicalRepository;
use App\Models\InterpolationRepository;
use App\Services\BoundaryService;
use App\Services\InterpolationDataService;
use Config\InterpolationConfig;
use Throwable;

final class MapController
{
    public function data(string $logContext = 'Map'): array
    {
        $database = null;
        $ownsTransaction = false;

        try {
            $database = Connection::get();
            if (!$database) {
                return ['boreholes' => [], 'recordsAvailable' => false];
            }

            // Keep interpolation eligibility and the marker summary on one
            // repeatable-read snapshot so concurrent edits cannot mix versions.
            if (!$database->inTransaction()) {
                $database->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                $database->beginTransaction();
                $ownsTransaction = true;
            }

            $repository = new GeotechnicalRepository($database);

            $boundary = new BoundaryService();
            $config = new InterpolationConfig();
            $input = (new InterpolationDataService($boundary, $config))->build(
                (new InterpolationRepository($database))->snapshot(),
                $config->systemQuery()
            );
            $eligibleIds = array_fill_keys(array_map(
                static fn(array $point): string => (string) $point['borehole_id'],
                $input['points']
            ), true);
            $boreholes = array_values(array_filter(
                $repository->mapBoreholes(),
                static fn(array $borehole): bool =>
                    isset($eligibleIds[(string) ($borehole['borehole_id'] ?? '')])
            ));

            if ($ownsTransaction) {
                $database->commit();
            }

            return [
                'boreholes' => $boreholes,
                'recordsAvailable' => true,
            ];
        } catch (Throwable $error) {
            if ($ownsTransaction && $database !== null && $database->inTransaction()) {
                $database->rollBack();
            }
            error_log($logContext . ': ' . $error->getMessage());

            return ['boreholes' => [], 'recordsAvailable' => false];
        }
    }
}
