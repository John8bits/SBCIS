<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/Models/locations.php';
$checks = 0;
function locationCheck(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
$snapshot = json_decode(file_get_contents(__DIR__ . '/../database/locations_snapshot.json'), true);
locationCheck(sbcis_validate_locations($snapshot), 'Bundled API response must be valid');
$invalid = $snapshot; $invalid['barangays'][0]['municipalityCode'] = '999999999';
locationCheck(!sbcis_validate_locations($invalid), 'Reject barangay outside parent directory');
$invalid = $snapshot; $invalid['municipalities'][0]['provinceCode'] = '000000000';
locationCheck(!sbcis_validate_locations($invalid), 'Reject wrong province');
$invalid = $snapshot; $invalid['barangays'][] = $invalid['barangays'][0];
locationCheck(!sbcis_validate_locations($invalid), 'Reject duplicate codes');
locationCheck(!sbcis_validate_locations([]), 'Reject empty API payload');
locationCheck(sbcis_location_key('City of Maasin') === sbcis_location_key('Maasin'), 'City naming alias');
locationCheck(sbcis_location_key('Iniguihan Pob.') === sbcis_location_key('Iniguihan Poblacion'), 'Poblacion abbreviation');
$directory = sbcis_locations();
locationCheck(count($directory['municipalities']) === count($snapshot['municipalities']), 'Municipality list complete');
locationCheck(count($directory['barangays']) === count($snapshot['barangays']), 'Barangay list complete');
$features = json_decode(file_get_contents(__DIR__ . '/../src/qgis/southern_leyte_barangays.geojson'), true)['features'];
$byBoundary = []; foreach ($features as $feature) $byBoundary[$feature['properties']['GID_3']] = $feature;
$parents = array_column($directory['municipalities'], null, 'code');
$matched = [];
foreach ($directory['barangays'] as $row) {
    if (!$row['boundaryId']) continue;
    locationCheck(isset($byBoundary[$row['boundaryId']]), 'Mapped geometry exists');
    locationCheck($byBoundary[$row['boundaryId']]['properties']['GID_2'] === $parents[$row['municipalityCode']]['boundaryId'], 'Barangay stays inside the correct municipality');
    locationCheck(!isset($matched[$row['boundaryId']]), 'No two API locations share one boundary');
    $matched[$row['boundaryId']] = true;
}
echo "$checks location checks passed; " . count($matched) . ' barangay boundaries matched; ' . (count($directory['barangays']) - count($matched)) . " explicitly unavailable.\n";
