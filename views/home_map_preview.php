<?php

use App\Controllers\MapController;

require_once __DIR__ . '/../config/bootstrap.php';

// Bootstrap denies framing by default. This public preview is intentionally
// embedded by our homepage, so permit that same origin only. Keep every other
// CSP directive and the framing policy of all other pages unchanged.
header('X-Frame-Options: SAMEORIGIN');
foreach (headers_list() as $responseHeader) {
    if (stripos($responseHeader, 'Content-Security-Policy:') === 0) {
        header(str_replace("frame-ancestors 'none'", "frame-ancestors 'self'", $responseHeader));
    }
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Use the public catalogue and its boundary/archive rules, including viewport
// loading when the initial catalogue is truncated. No admin mode is enabled.
$mapData = (new MapController())->data('Homepage map preview');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Southern Leyte map preview</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="../src/css/gis.css?v=<?= filemtime(__DIR__ . '/../src/css/gis.css') ?>">
  <link rel="stylesheet" href="../src/css/map-theme.css?v=<?= filemtime(__DIR__ . '/../src/css/map-theme.css') ?>">
  <link rel="stylesheet" href="../src/css/home-map-preview.css?v=<?= filemtime(__DIR__ . '/../src/css/home-map-preview.css') ?>">
  <script>
    window.SBCIS_BOREHOLES = <?= json_encode($mapData['boreholes'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.SBCIS_RECORDS_AVAILABLE = <?= $mapData['recordsAvailable'] ? 'true' : 'false' ?>;
    window.SBCIS_CATALOGUE_TRUNCATED = <?= $mapData['catalogueTruncated'] ? 'true' : 'false' ?>;
    window.SBCIS_BOREHOLE_ENDPOINT = '../app/Controllers/boreholes.php';
  </script>
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/d3@7.9.0/dist/d3.min.js"></script>
  <script defer src="../src/js/interpolation.js?v=<?= filemtime(__DIR__ . '/../src/js/interpolation.js') ?>"></script>
  <script defer src="../src/js/gis.js?v=<?= filemtime(__DIR__ . '/../src/js/gis.js') ?>"></script>
</head>
<body class="gis-embedded homepage-map-preview">
  <?php require __DIR__ . '/partials/map_explorer.php'; ?>
</body>
</html>
