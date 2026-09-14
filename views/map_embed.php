<?php
use App\Controllers\MapController;

require_once __DIR__ . '/../config/bootstrap.php';

$mapData = (new MapController())->data('Embedded map');
$boreholes = $mapData['boreholes'];
$recordsAvailable = $mapData['recordsAvailable'];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Southern Leyte map</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><link rel="stylesheet" href="../src/css/gis.css?v=<?= filemtime(__DIR__ . '/../src/css/gis.css') ?>">
<script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>window.SBCIS_BOREHOLES = <?= json_encode($boreholes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.SBCIS_RECORDS_AVAILABLE = <?= $recordsAvailable ? 'true' : 'false' ?>;</script><script defer src="../src/js/gis.js?v=<?= filemtime(__DIR__ . '/../src/js/gis.js') ?>"></script></head>
<body class="gis-embedded"><?php require __DIR__ . '/partials/map_explorer.php'; ?></body></html>
