<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Models\GeotechnicalRepository;
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

            return [
                'boreholes' => $repository->mapBoreholes(),
                'recordsAvailable' => true,
            ];
        } catch (Throwable $error) {
            error_log($logContext . ': ' . $error->getMessage());

            return ['boreholes' => [], 'recordsAvailable' => false];
        }
    }
}
