<?php
require_once __DIR__ . '/../app/Models/geotechnical_data.php';
$boreholes = []; $recordsAvailable = false;
try { $db = sbcis_get_database(); if ($db) { $boreholes = sbcis_fetch_map_boreholes($db); $recordsAvailable = true; } }
catch (Throwable $e) { error_log('Embedded map: ' . $e->getMessage()); }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Southern Leyte map</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><link rel="stylesheet" href="../src/css/gis.css">
<script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>window.SBCIS_BOREHOLES = <?= json_encode($boreholes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.SBCIS_RECORDS_AVAILABLE = <?= $recordsAvailable ? 'true' : 'false' ?>;</script><script defer src="../src/js/gis.js"></script></head>
<body class="gis-embedded"><?php require __DIR__ . '/partials/map_explorer.php'; ?></body></html>
