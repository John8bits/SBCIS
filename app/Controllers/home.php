<?php

// Read-only data for the public homepage. Unknown counts stay null, not zero.
$home = ['municipalities' => null, 'barangays' => null, 'boreholes' => null,
    'soilLayers' => null, 'records' => [], 'databaseAvailable' => false];

require_once __DIR__ . '/../Models/locations.php';
try {
    $directory = sbcis_locations();
    $home['municipalities'] = count($directory['municipalities']);
    $home['barangays'] = count($directory['barangays']);
    $home['locationSource'] = $directory['status'];
} catch (Throwable $error) { error_log('Homepage locations: ' . $error->getMessage()); }

try {
    require __DIR__ . '/../../config/config.php';
    $counts = $pdo->query('SELECT (SELECT COUNT(*) FROM boreholes) AS boreholes,
        (SELECT COUNT(*) FROM soil_layers) AS soil_layers')->fetch(PDO::FETCH_ASSOC);
    $records = $pdo->query('SELECT b.borehole_code, m.municipality_name,
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
} catch (PDOException $error) {
    error_log('Homepage soil data: ' . $error->getMessage());
}

return $home;
