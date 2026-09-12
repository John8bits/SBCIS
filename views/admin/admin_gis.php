<?php
session_start(); header('Cache-Control: no-store');
if (($_SESSION['admin_logged_in'] ?? false) !== true) { header('Location: ../../index.php?login=required'); exit; }
require_once __DIR__ . '/../../app/Models/geotechnical_data.php';
$boreholes = []; $recordsAvailable = false;
try { $db = sbcis_get_database(); if ($db) { $boreholes = sbcis_fetch_map_boreholes($db); $recordsAvailable = true; } }
catch (Throwable $e) { error_log('Admin map: ' . $e->getMessage()); }
$escape = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$title = 'GIS Map'; $activePage = 'admin_gis.php';
$gisCssVersion = filemtime(__DIR__ . '/../../src/css/gis.css');
$gisJsVersion = filemtime(__DIR__ . '/../../src/js/gis.js');
$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><link rel="stylesheet" href="../../src/css/gis.css?v=' . $gisCssVersion . '"><script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script defer src="../../src/js/gis.js?v=' . $gisJsVersion . '"></script>';
require __DIR__ . '/overview_shell.php';
?>
<script>window.SBCIS_MAP_BASE = '../../'; window.SBCIS_BOREHOLES = <?= json_encode($boreholes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.SBCIS_RECORDS_AVAILABLE = <?= $recordsAvailable ? 'true' : 'false' ?>;</script>
<div class="admin-map-workspace"><?php require __DIR__ . '/../partials/map_explorer.php'; ?></div>
</main></div></body></html>
