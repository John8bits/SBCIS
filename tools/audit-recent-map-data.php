<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/bootstrap.php';

$db = App\Database\Connection::get();
$rows = $db->query("SELECT b.borehole_id, b.borehole_code, b.created_at,
    m.municipality_name, br.barangay_name, b.latitude, b.longitude,
    GROUP_CONCAT(CONCAT_WS(' | ', sl.soil_type, sl.soil_description, sl.bearing_capacity_kpa)
        ORDER BY sl.layer_number SEPARATOR ' || ') AS layers
    FROM boreholes b
    LEFT JOIN municipalities m ON m.municipality_id = b.municipality_id
    LEFT JOIN barangays br ON br.barangay_id = b.barangay_id
    LEFT JOIN soil_layers sl ON sl.borehole_id = b.borehole_id
    GROUP BY b.borehole_id
    ORDER BY b.created_at DESC, b.borehole_id DESC
    LIMIT 15")->fetchAll();
$boundary = new App\Services\BoundaryService();
foreach ($rows as $row) {
    $probe = $row;
    $probe['layers'] = [['soil_type' => $row['layers'], 'soil_description' => $row['layers']]];
    $row['inside_southern_leyte'] = $boundary->contains($row['latitude'], $row['longitude']);
    $row['sample_provenance'] = Config\InterpolationConfig::isNonFieldRecord($probe);
    echo json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
