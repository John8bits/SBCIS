<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Models\LocationDirectory;
use PDO;
use Throwable;

final class HomeController
{
    private LocationDirectory $locations;

    public function __construct(LocationDirectory $locations)
    {
        $this->locations = $locations;
    }

    public function index(): array
    {
        $home = [
            'municipalities' => null,
            'barangays' => null,
            'boreholes' => null,
            'soilLayers' => null,
            'records' => [],
            'databaseAvailable' => false,
        ];

        try {
            $directory = $this->locations->all();
            $home['municipalities'] = count($directory['municipalities']);
            $home['barangays'] = count($directory['barangays']);
            $home['locationSource'] = $directory['status'];
        } catch (Throwable $error) {
            error_log('Homepage locations: ' . $error->getMessage());
        }

        try {
            $database = Connection::get();
            if (!$database) {
                return $home;
            }

            $counts = $database->query('SELECT (SELECT COUNT(*) FROM boreholes) AS boreholes,
                (SELECT COUNT(*) FROM soil_layers) AS soil_layers')->fetch(PDO::FETCH_ASSOC);
            $records = $database->query('SELECT b.borehole_code, m.municipality_name,
                br.barangay_name, sl.layer_number, sl.soil_type, sl.bearing_capacity_kpa
                FROM soil_layers sl
                JOIN boreholes b ON b.borehole_id = sl.borehole_id
                LEFT JOIN municipalities m ON m.municipality_id = b.municipality_id
                LEFT JOIN barangays br ON br.barangay_id = b.barangay_id
                ORDER BY sl.created_at DESC, sl.soil_layer_id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
            $home['boreholes'] = (int) $counts['boreholes'];
            $home['soilLayers'] = (int) $counts['soil_layers'];
            $home['records'] = $records;
            $home['databaseAvailable'] = true;
        } catch (Throwable $error) {
            error_log('Homepage soil data: ' . $error->getMessage());
        }

        return $home;
    }
}
