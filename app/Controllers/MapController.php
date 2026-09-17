<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Models\GeotechnicalRepository;
use App\Services\BoundaryService;
use Throwable;

final class MapController
{
    public function data(string $logContext = 'Map'): array
    {
        try {
            $database = Connection::get();
            if (!$database) {
                return ['boreholes' => [], 'recordsAvailable' => false];
            }

            $repository = new GeotechnicalRepository($database);

            $boundary = new BoundaryService();
            $boreholes = array_values(array_filter(
                $repository->mapBoreholes(),
                static fn(array $borehole): bool =>
                    $boundary->contains($borehole['latitude'] ?? null, $borehole['longitude'] ?? null)
            ));

            return [
                'boreholes' => $boreholes,
                'recordsAvailable' => true,
            ];
        } catch (Throwable $error) {
            error_log($logContext . ': ' . $error->getMessage());

            return ['boreholes' => [], 'recordsAvailable' => false];
        }
    }
}
