<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\BoundaryService;
use App\Services\InterpolationDataService;
use App\Services\InterpolationGeneratorService;
use Config\InterpolationConfig;

$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function fixture(int $id, $value = 12, float $longitude = 1, float $latitude = 1): array {
    return ['borehole_id' => $id, 'soil_layer_id' => $id, 'longitude' => $longitude,
        'latitude' => $latitude, 'depth_from_m' => 0, 'depth_to_m' => 1,
        'bearing_capacity_kpa' => $value, 'spt_n_value' => $value];
}
$geometry = ['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'geometry' => [
    'type' => 'MultiPolygon', 'coordinates' => [
        [[[0,0],[10,0],[10,10],[0,10],[0,0]], [[3,3],[7,3],[7,7],[3,7],[3,3]]],
        [[[12,0],[14,0],[14,2],[12,2],[12,0]]]
    ]
]]]];
$boundary = new BoundaryService($geometry);
check($boundary->contains(1, 1), 'Polygon interior');
check(!$boundary->contains(5, 5), 'Hole must not be treated as bounding box interior');
check(!$boundary->contains(3, 5), 'Hole edge excluded');
check($boundary->contains(0, 1), 'Exterior edge included');
check($boundary->contains(1, 13), 'Separate island included');
check(!$boundary->contains(1, 11), 'Gap between islands excluded');
$insideSurface = ['type'=>'FeatureCollection','features'=>[['type'=>'Feature','geometry'=>[
    'type'=>'Polygon','coordinates'=>[[[1,1],[2,1],[2,2],[1,1]]]
]]]];
$outsideSurface = ['type'=>'FeatureCollection','features'=>[['type'=>'Feature','geometry'=>[
    'type'=>'Polygon','coordinates'=>[[[1,1],[11,1],[1,2],[1,1]]]
]]]];
check($boundary->coversFeatureCollection($insideSurface), 'Clipped surface accepted inside study geometry');
check(!$boundary->coversFeatureCollection($outsideSurface), 'Surface extending outside study geometry rejected');
foreach ([[null,1], [true,1], [91,1], [1,181], ['abc',1], [INF,1]] as $point) {
    check(!BoundaryService::validCoordinates(...$point), 'Invalid worldwide coordinates');
}
$actual = new BoundaryService();
check($actual->contains(10.15, 124.85), 'Real Southern Leyte inland coordinate near Maasin');
check(!$actual->contains(10.3, 12.1), 'Current-style invalid longitude outside Southern Leyte');
check(!$actual->contains(10.0, 124.8), 'Province bounding box alone is insufficient');
$polygon = $geometry;
$polygon['features'][0]['geometry'] = ['type' => 'Polygon', 'coordinates' => $geometry['features'][0]['geometry']['coordinates'][0]];
check((new BoundaryService($polygon))->contains(1,1), 'Polygon geometry supported');
$generatorInput = [
    'status' => 'ready', 'variable' => 'bearing_capacity_kpa', 'unit' => 'kPa',
    'points' => [
        ['coordinates' => [125.1679388, 10.5915529], 'value' => 94.0],
        ['coordinates' => [124.9662788, 10.3881259], 'value' => 57.0],
        ['coordinates' => [125.1229333, 10.2609580], 'value' => 321.0],
        ['coordinates' => [125.0973591, 10.3950472], 'value' => 284.0],
        ['coordinates' => [125.0214166, 10.4003142], 'value' => 247.0],
    ],
];
$generated = (new InterpolationGeneratorService())->generate($generatorInput);
check(!empty($generated['surface']['features']), 'Approved generator returns supported barangay cells');
check(count($generated['surface']['features']) < 498, 'Distant barangays omitted by support-distance policy');
check($generated['validation']['sample_count'] === 5, 'Leave-one-out validates every eligible point');
check($generated['validation']['exact_location_max_error'] === 0.0, 'IDW returns observed values at exact locations');
check($generated['validation']['mae'] >= 0 && $generated['validation']['rmse'] >= 0, 'Cross-validation errors are numeric');
check($generated === (new InterpolationGeneratorService())->generate($generatorInput), 'Generator output deterministic');
$config = new InterpolationConfig(['bearing_capacity_kpa', 'spt_n_value'], 3);
$service = new InterpolationDataService($boundary, $config);
$query = ['variable' => 'bearing_capacity_kpa'];
$build = static fn(array $rows) => $service->build($rows, $query);
check($build([])['status'] === 'no_data', 'Empty dataset');
check($build([fixture(1, null)])['status'] === 'no_data', 'Null excluded');
check($build([fixture(1, null)])['exclusion_reasons']->null_measurement === 1, 'Null reason');
$zero = $build([fixture(1, 0)]);
check($zero['points'][0]['value'] === 0.0, 'Genuine zero preserved');
check($zero['status'] === 'insufficient_data', 'One eligible observation');
check($zero['generation_enabled'] === true, 'Approved bearing-capacity generator available');
$rows = [fixture(1), fixture(2, 20, 2), fixture(3, 30, 8)];
check($build(array_slice($rows, 0, 2))['status'] === 'insufficient_data', 'Two eligible observations');
$ready = $build($rows);
check($ready['status'] === 'ready' && $ready['generation_enabled'], 'Approved variable becomes generation ready');
$deep = fixture(1, 90); $deep['soil_layer_id'] = 99; $deep['depth_from_m'] = 2; $deep['depth_to_m'] = 4;
$depthSelection = $build([fixture(1, 25), $deep]);
check($depthSelection['eligible_count'] === 1 && $depthSelection['points'][0]['value'] === 25.0,
    'Shallowest valid layer selected per borehole');
check($depthSelection['exclusion_reasons']->deeper_layer_not_selected === 1, 'Deeper-layer decision audited');
$duplicates = $build([fixture(1), fixture(2)]);
check($duplicates['eligible_count'] === 0 && $duplicates['excluded_count'] === 2, 'All duplicate observations excluded');
check($duplicates['exclusion_reasons']->duplicate_coordinates === 2, 'Duplicate diagnostics');
$outside = $build([fixture(1, 3, 15), fixture(2, 5, 15.5)]);
check($outside['outside_borehole_count'] === 2 && $outside['eligible_count'] === 0 &&
    $outside['exclusion_reasons']->outside_study_boundary === 2,
    'World-valid coordinates outside the study polygon are quarantined');
$demo = fixture(4); $demo['borehole_code'] = 'SYNTH-DEMO-004';
$demoResult = $build([$demo]);
check($demoResult['eligible_count'] === 0 && $demoResult['non_field_borehole_count'] === 1 &&
    $demoResult['exclusion_reasons']->non_field_record === 1,
    'Non-field code excluded from interpolation');
$renamedDemo = fixture(5); $renamedDemo['borehole_code'] = 'BH-005'; $renamedDemo['soil_type'] = 'SYNTHETIC SAMPLE';
check($build([$renamedDemo])['eligible_count'] === 0 && Config\InterpolationConfig::isNonFieldRecord($renamedDemo),
    'Renamed non-field provenance remains identified and excluded');
check($build([fixture(1, -1)])['exclusion_reasons']->invalid_measurement === 1, 'Negative invalid measurement');
$noLayer = fixture(1); $noLayer['soil_layer_id'] = null;
check($build([$noLayer])['exclusion_reasons']->no_observation === 1, 'Borehole without layer');
$badDepth = fixture(1); $badDepth['depth_to_m'] = 0;
check($build([$badDepth])['exclusion_reasons']->invalid_depth_interval === 1, 'Invalid depth interval');
check($ready['version'] === $build(array_reverse($rows))['version'], 'Stable snapshot ordering');
$edited = $rows; $edited[0]['bearing_capacity_kpa'] = 22;
check($ready['version'] !== $build($edited)['version'], 'Measurement edit invalidates');
check($ready['version'] !== $build(array_slice($rows, 1))['version'], 'Deletion invalidates');
$edited = $rows; $edited[0]['longitude'] = 1.1;
check($ready['version'] !== $build($edited)['version'], 'Coordinate edit invalidates');
check($ready['version'] !== $build([...$rows, fixture(4, 7, 9)])['version'], 'Insertion invalidates');
check($ready['version'] !== $service->build($rows, ['variable'=>'spt_n_value'])['version'], 'Variable change invalidates');
$differentConfig = new InterpolationDataService($boundary, new InterpolationConfig(['bearing_capacity_kpa','spt_n_value'], 4));
check($ready['version'] !== $differentConfig->build($rows, $query)['version'], 'Technical parameter change invalidates');
$changedBoundary = $geometry;
$changedBoundary['features'][0]['geometry']['coordinates'][1][0][1][0] = 15;
$changedService = new InterpolationDataService(new BoundaryService($changedBoundary), $config);
check($ready['version'] !== $changedService->build($rows, $query)['version'], 'Boundary geometry change invalidates');
$invalidCoordinate = fixture(1); $invalidCoordinate['latitude'] = null;
check($build([$invalidCoordinate])['exclusion_reasons']->invalid_coordinates === 1, 'Null coordinates excluded');
check($build([fixture(1, '')])['exclusion_reasons']->null_measurement === 1, 'Empty value not zero');
check($build([fixture(1, 'abc')])['exclusion_reasons']->invalid_measurement === 1, 'Nonnumeric value not zero');
check($build([fixture(1, '0.00')])['points'][0]['value'] === 0.0, 'PDO decimal zero preserved');
$numericStrings = $rows;
foreach ($numericStrings as &$row) foreach ($row as &$value) $value = (string) $value;
unset($row, $value);
check($ready['version'] === $build($numericStrings)['version'], 'Numeric PDO representation stable');
$default = new InterpolationDataService($boundary, new InterpolationConfig());
check($default->build($rows, [])['points'] === [], 'Unapproved variables never published');
check($default->build($rows, [])['status'] === 'pending_configuration', 'Pending variable explicit');
foreach ([['scope'=>'municipality'], ['variable'=>'spt_n_value'], ['variable'=>['x']], ['sql'=>'x']] as $invalid) {
    try { $default->build([], $invalid); throw new RuntimeException('Invalid query accepted'); }
    catch (InvalidArgumentException $expected) { $checks++; }
}
echo "$checks interpolation PHP checks passed (in-memory fixtures; no database mutations).\n";
