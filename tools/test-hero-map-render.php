<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ob_start();
require dirname(__DIR__) . '/index.php';
$html = (string) ob_get_clean();

foreach ([
    'shared preview frame' => 'src="views/home_map_preview.php"',
    'homepage controls bridge' => 'src/js/hero-map.js',
    'range legend' => 'id="heroInterpolationLegend"',
    'hero reset' => 'id="heroMapReset"',
] as $label => $needle) {
    if (!str_contains($html, $needle)) throw new RuntimeException('Missing ' . $label . '.');
}

$checks = 4;
foreach (array_merge(Config\InterpolationConfig::bearingCapacityClasses(), [Config\InterpolationConfig::UNKNOWN_BEARING_CAPACITY]) as $band) {
    if (!str_contains($html, 'background-color:' . $band['color'])) throw new RuntimeException('Homepage legend palette differs from public map.');
    $checks++;
}
if (!str_contains($html, 'href="views/gis.php">Open full map')) throw new RuntimeException('Full map link missing.');
$checks++;
ob_start();
require dirname(__DIR__) . '/views/home_map_preview.php';
$preview = (string) ob_get_clean();
foreach ([
    'public GIS runtime' => '../src/js/gis.js',
    'shared interpolation and classification' => '../src/js/interpolation.js',
    'preview-only presentation' => '../src/css/home-map-preview.css',
    'public mode' => 'data-mode="public"',
    'live public records' => 'window.SBCIS_BOREHOLES',
    'viewport endpoint' => "'../app/Controllers/boreholes.php'",
    'shared popup/legend palette' => 'window.SBCIS_BEARING_CAPACITY_CLASSES',
    'shared zoom/reset controls' => 'id="quickResetMap"',
] as $label => $needle) {
    if (!str_contains($preview, $needle)) throw new RuntimeException('Missing ' . $label . '.');
    $checks++;
}
if (str_contains($preview, 'data-mode="admin"') || str_contains($preview, 'hero-map.js')) {
    throw new RuntimeException('Preview has administrator mode or a second map runtime.');
}
$checks++;
echo $checks . " homepage preview render checks passed.\n";
