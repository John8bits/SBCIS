<?php
use App\Controllers\MapController;
use App\Services\InterpolationPreviewService;
use App\Support\AdminSession;
use App\Support\View;

require_once __DIR__ . '/../../config/bootstrap.php';

AdminSession::requireLogin();
$previewMode = $_GET['preview'] ?? '';
if (!is_string($previewMode) || !in_array($previewMode, ['', 'synthetic'], true)) {
    http_response_code(400);
    exit('Unsupported preview mode.');
}
$interpolationPreviewActive = $previewMode === 'synthetic';
$interpolationPreviewResponse = null;
if ($interpolationPreviewActive) {
    $previewData = (new InterpolationPreviewService())->build();
    $boreholes = $previewData['boreholes'];
    $interpolationPreviewResponse = $previewData['response'];
    $recordsAvailable = true;
} else {
    $mapData = (new MapController())->data('Admin map');
    $boreholes = $mapData['boreholes'];
    $recordsAvailable = $mapData['recordsAvailable'];
}
$escape = [View::class, 'escape'];
$title = 'GIS Map';
$activePage = 'admin_gis.php';
$gisCssVersion = filemtime(__DIR__ . '/../../src/css/gis.css');
$gisJsVersion = filemtime(__DIR__ . '/../../src/js/gis.js');
$interpolationAdmin = true;
$_SESSION['interpolation_csrf'] = $_SESSION['interpolation_csrf'] ?? bin2hex(random_bytes(32));
$interpolationVersion = filemtime(__DIR__ . '/../../src/js/interpolation.js');
$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><link rel="stylesheet" href="../../src/css/gis.css?v=' . $gisCssVersion . '"><script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script defer src="../../src/js/interpolation.js?v=' . $interpolationVersion . '"></script><script defer src="../../src/js/gis.js?v=' . $gisJsVersion . '"></script>';
require __DIR__ . '/overview_shell.php';
?>
<script>window.SBCIS_MAP_BASE = '../../'; window.SBCIS_BOREHOLES = <?= json_encode($boreholes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.SBCIS_RECORDS_AVAILABLE = <?= $recordsAvailable ? 'true' : 'false' ?>; window.SBCIS_INTERPOLATION_PREVIEW = <?= json_encode($interpolationPreviewResponse, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<div class="admin-map-workspace"><?php require __DIR__ . '/../partials/map_explorer.php'; ?></div>
</main>
</div>
</body>

</html>
