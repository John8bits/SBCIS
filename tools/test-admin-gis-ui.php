<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_role'] = 'admin';

ob_start();
require dirname(__DIR__) . '/views/admin/admin_gis.php';
$html = (string) ob_get_clean();

$checks = [
    'fullscreen control' => 'id="toggleFullscreen"',
    'location panel' => 'id="gisExplorer"',
    'map data panel' => 'id="gisInsights"',
    'accessible area dialog' => 'aria-modal="true"',
    'capacity readout' => 'id="gisCapacityCard"',
    'live interpolation mode' => 'data-mode="admin"',
];

foreach ($checks as $label => $needle) {
    if (!str_contains($html, $needle)) {
        throw new RuntimeException('Missing ' . $label . '.');
    }
}
if (str_contains($html, 'preview=synthetic') || str_contains($html, 'SBCIS_INTERPOLATION_PREVIEW')) {
    throw new RuntimeException('Synthetic preview controls are still present.');
}

echo count($checks) + 1 . " admin GIS render checks passed.\n";
