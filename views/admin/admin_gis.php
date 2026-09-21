<?php
use App\Controllers\MapController;
use App\Support\AdminSession;
use App\Support\View;

require_once __DIR__ . '/../../config/bootstrap.php';

AdminSession::requireLogin();

// Boreholes are embedded in this page as JSON. Never serve a stale page after
// an administrator saves or edits a record.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$mapData = (new MapController())->data('Admin map', true);
$boreholes = $mapData['boreholes'];
$recordsAvailable = $mapData['recordsAvailable'];
$catalogueTruncated = $mapData['catalogueTruncated'];
$escape = [View::class, 'escape'];
$title = 'GIS Map';
$activePage = 'admin_gis.php';
$gisCssVersion = filemtime(__DIR__ . '/../../src/css/gis.css');
$gisJsVersion = filemtime(__DIR__ . '/../../src/js/gis.js');
$interpolationAdmin = true;
$_SESSION['interpolation_csrf'] = $_SESSION['interpolation_csrf'] ?? bin2hex(random_bytes(32));
$interpolationVersion = filemtime(__DIR__ . '/../../src/js/interpolation.js');
$mapThemeVersion = filemtime(__DIR__ . '/../../src/css/map-theme.css');
$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><link rel="stylesheet" href="../../src/css/gis.css?v=' . $gisCssVersion . '"><link rel="stylesheet" href="../../src/css/map-theme.css?v=' . $mapThemeVersion . '"><script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script defer src="https://cdn.jsdelivr.net/npm/d3@7.9.0/dist/d3.min.js"></script><script defer src="../../src/js/interpolation.js?v=' . $interpolationVersion . '"></script><script defer src="../../src/js/gis.js?v=' . $gisJsVersion . '"></script>';
require __DIR__ . '/overview_shell.php';
?>
<script>window.SBCIS_MAP_BASE = '../../'; window.SBCIS_BOREHOLES = <?= json_encode($boreholes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.SBCIS_RECORDS_AVAILABLE = <?= $recordsAvailable ? 'true' : 'false' ?>; window.SBCIS_CATALOGUE_TRUNCATED = <?= $catalogueTruncated ? 'true' : 'false' ?>; window.SBCIS_BOREHOLE_ENDPOINT = '../../app/Controllers/boreholes.php';</script>
<div class="admin-map-workspace"><?php require __DIR__ . '/../partials/map_explorer.php'; ?></div>
</main>
</div>
</body>

</html>
