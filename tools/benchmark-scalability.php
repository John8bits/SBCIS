<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/app/Models/geotechnical_data.php';

$count = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT);
if (!in_array($count, [100, 1000, 5000, 10000], true)) {
    fwrite(STDERR, "Usage: php tools/benchmark-scalability.php 100|1000|5000|10000\n");
    exit(2);
}

function elapsed(float $start): float { return round((microtime(true) - $start) * 1000, 2); }
$rows = [];
for ($index = 0; $index < $count; $index++) {
    $x = $index % 100;
    $y = intdiv($index, 100);
    $rows[] = [
        'borehole_id'=>$index + 1, 'borehole_code'=>sprintf('LOAD-%05d', $index + 1),
        'soil_layer_id'=>$index + 1, 'soil_type'=>'clay', 'soil_description'=>null,
        'longitude'=>124.83 + $x * 0.0003, 'latitude'=>10.13 + $y * 0.000003,
        'depth_from_m'=>0, 'depth_to_m'=>10,
        'bearing_capacity_kpa'=>50 + ($index % 300), 'spt_n_value'=>$index % 50,
    ];
}
$fixtureBoundary = new App\Services\BoundaryService(['type'=>'FeatureCollection','features'=>[[
    'type'=>'Feature','geometry'=>['type'=>'Polygon','coordinates'=>[[[124.7,10.0],[125.4,10.0],[125.4,10.8],[124.7,10.8],[124.7,10.0]]]]
]]]);
$start = microtime(true);
$input = (new App\Services\InterpolationDataService($fixtureBoundary, new Config\InterpolationConfig()))
    ->build($rows, ['scope'=>'province','variable'=>'bearing_capacity_kpa']);
$prepareMs = elapsed($start);
$start = microtime(true);
$surface = (new App\Services\InterpolationGeneratorService())->generate($input);
$interpolationMs = elapsed($start);

$db = App\Database\Connection::get();
$db->exec('CREATE TEMPORARY TABLE municipalities (municipality_id INT UNSIGNED PRIMARY KEY, municipality_name VARCHAR(100) NOT NULL UNIQUE)');
$db->exec('CREATE TEMPORARY TABLE barangays (barangay_id INT UNSIGNED PRIMARY KEY, municipality_id INT UNSIGNED NOT NULL, barangay_name VARCHAR(100) NOT NULL, INDEX(municipality_id))');
$db->exec('CREATE TEMPORARY TABLE boreholes (borehole_id INT UNSIGNED PRIMARY KEY, borehole_code VARCHAR(50) NOT NULL UNIQUE, municipality_id INT UNSIGNED NULL, barangay_id INT UNSIGNED NULL, borehole_depth_m DECIMAL(8,2) NOT NULL, latitude DECIMAL(10,7) NOT NULL, longitude DECIMAL(10,7) NOT NULL, elevation_m DECIMAL(8,2) NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, lock_version INT UNSIGNED NOT NULL DEFAULT 1, INDEX(created_at,borehole_id))');
$db->exec('CREATE TEMPORARY TABLE soil_layers (soil_layer_id INT UNSIGNED PRIMARY KEY, borehole_id INT UNSIGNED NOT NULL, layer_number INT UNSIGNED NOT NULL, soil_type VARCHAR(100) NOT NULL, soil_classification VARCHAR(100) NULL, soil_description TEXT NULL, depth_from_m DECIMAL(8,2) NOT NULL, depth_to_m DECIMAL(8,2) NOT NULL, spt_n_value INT UNSIGNED NULL, bearing_capacity_kpa DECIMAL(10,2) NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, UNIQUE(borehole_id,layer_number), INDEX(created_at,soil_layer_id))');
$db->exec("INSERT INTO municipalities (municipality_id,municipality_name) VALUES (1,'Load Municipality')");
$db->exec("INSERT INTO barangays (barangay_id,municipality_id,barangay_name) VALUES (1,1,'Load Barangay')");
$borehole = $db->prepare('INSERT INTO boreholes (borehole_id,borehole_code,municipality_id,barangay_id,borehole_depth_m,latitude,longitude,created_at) VALUES (?,?,1,1,10,?,?,?)');
$layer = $db->prepare('INSERT INTO soil_layers (soil_layer_id,borehole_id,layer_number,soil_type,depth_from_m,depth_to_m,spt_n_value,bearing_capacity_kpa,created_at) VALUES (?,?,1,\'clay\',0,10,?,?,?)');
$db->beginTransaction();
foreach ($rows as $row) {
    $created = sprintf('2026-01-%02d 00:00:00', ($row['borehole_id'] % 28) + 1);
    $borehole->execute([$row['borehole_id'],$row['borehole_code'],$row['latitude'],$row['longitude'],$created]);
    $layer->execute([$row['soil_layer_id'],$row['borehole_id'],$row['spt_n_value'],$row['bearing_capacity_kpa'],$created]);
}
$db->commit();
$start = microtime(true);
$page = (new App\Services\AdminDataService($db))->page('boreholes', ['page'=>50,'page_size'=>50,'search'=>'LOAD-']);
$paginationMs = elapsed($start);
$start = microtime(true);
$mapRows = sbcis_fetch_map_boreholes($db);
$mapQueryMs = elapsed($start);
$mapJsonBytes = strlen(json_encode($mapRows, JSON_THROW_ON_ERROR));

echo json_encode([
    'records'=>$count, 'eligible'=>$input['eligible_count'],
    'data_prepare_ms'=>$prepareMs, 'interpolation_ms'=>$interpolationMs,
    'surface_cells'=>count($surface['surface']['features']),
    'pagination_query_ms'=>$paginationMs, 'page_rows'=>count($page['rows']),
    'map_query_ms'=>$mapQueryMs, 'map_payload_bytes'=>$mapJsonBytes,
    'peak_memory_mb'=>round(memory_get_peak_usage(true) / 1048576, 2),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
