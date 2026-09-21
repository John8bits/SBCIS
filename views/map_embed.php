<?php
use App\Controllers\MapController;

require_once __DIR__ . '/../config/bootstrap.php';

$mapData = (new MapController())->data('Embedded map');
$boreholes = $mapData['boreholes'];
$recordsAvailable = $mapData['recordsAvailable'];
$catalogueTruncated = $mapData['catalogueTruncated'];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Southern Leyte map</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><link rel="stylesheet" href="../src/css/gis.css?v=<?= filemtime(__DIR__ . '/../src/css/gis.css') ?>"><link rel="stylesheet" href="../src/css/map-theme.css?v=<?= filemtime(__DIR__ . '/../src/css/map-theme.css') ?>">
<script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/d3@7.9.0/dist/d3.min.js"></script>
<script defer src="../src/js/interpolation.js?v=<?= filemtime(__DIR__ . '/../src/js/interpolation.js') ?>"></script>
<script>window.SBCIS_BOREHOLES = <?= json_encode($boreholes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.SBCIS_RECORDS_AVAILABLE = <?= $recordsAvailable ? 'true' : 'false' ?>; window.SBCIS_CATALOGUE_TRUNCATED = <?= $catalogueTruncated ? 'true' : 'false' ?>; window.SBCIS_BOREHOLE_ENDPOINT = '../app/Controllers/boreholes.php';</script><script defer src="../src/js/gis.js?v=<?= filemtime(__DIR__ . '/../src/js/gis.js') ?>"></script></head>
<body class="gis-embedded"><?php require __DIR__ . '/partials/map_explorer.php'; ?></body></html>
