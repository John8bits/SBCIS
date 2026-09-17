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
    'hero map data' => 'window.SBCIS_HERO_BOREHOLES',
    'interpolation runtime' => 'src/js/interpolation.js',
    'range legend' => 'id="heroInterpolationLegend"',
    'hero reset' => 'id="heroMapReset"',
] as $label => $needle) {
    if (!str_contains($html, $needle)) throw new RuntimeException('Missing ' . $label . '.');
}

echo "4 hero map render checks passed.\n";
