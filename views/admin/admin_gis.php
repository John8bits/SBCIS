<?php
use App\Controllers\MapController;
use App\Support\AdminSession;
use App\Support\View;

require_once __DIR__ . '/../../config/bootstrap.php';

AdminSession::requireLogin();
$mapData = (new MapController())->data('Admin map');
$boreholes = $mapData['boreholes'];
$recordsAvailable = $mapData['recordsAvailable'];
$escape = [View::class, 'escape'];
$title = 'GIS Map';
$activePage = 'admin_gis.php';
$gisCssVersion = filemtime(__DIR__ . '/../../src/css/gis.css');
$gisJsVersion = filemtime(__DIR__ . '/../../src/js/gis.js');
$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><link rel="stylesheet" href="../../src/css/gis.css?v=' . $gisCssVersion . '"><script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script defer src="../../src/js/gis.js?v=' . $gisJsVersion . '"></script>';
require __DIR__ . '/overview_shell.php';
?>
<script>window.SBCIS_MAP_BASE = '../../'; window.SBCIS_BOREHOLES = <?= json_encode($boreholes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.SBCIS_RECORDS_AVAILABLE = <?= $recordsAvailable ? 'true' : 'false' ?>;</script>
<div class="admin-map-workspace"><?php require __DIR__ . '/../partials/map_explorer.php'; ?></div>
</main>
</div>
</body>

</html>
