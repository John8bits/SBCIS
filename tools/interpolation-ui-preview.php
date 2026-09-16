<?php

declare(strict_types=1);

use App\Services\InterpolationPreviewService;

require_once dirname(__DIR__) . '/config/bootstrap.php';

$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoteAddress, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
    http_response_code(404);
    exit('Not found');
}

try {
    $previewData = (new InterpolationPreviewService())->build();
} catch (Throwable $error) {
    error_log('Interpolation UI preview: ' . $error->getMessage());
    http_response_code(503);
    exit('The synthetic UI-preview fixture is unavailable. Import database/sample_interpolation_50.sql into a demo database first.');
}

$demoBoreholes = $previewData['boreholes'];
$preview = $previewData['response'];
$interpolationPreviewActive = true;
$jsonOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title>Interpolation UI Preview | SBCIS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="../src/css/gis.css?v=<?= filemtime(dirname(__DIR__) . '/src/css/gis.css') ?>">
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    window.SBCIS_MAP_BASE = '../';
    window.SBCIS_RECORDS_AVAILABLE = true;
    window.SBCIS_BOREHOLES = <?= json_encode($demoBoreholes, $jsonOptions) ?>;
    window.SBCIS_INTERPOLATION_PREVIEW = <?= json_encode($preview, $jsonOptions) ?>;
  </script>
  <script defer src="../src/js/interpolation.js?v=<?= filemtime(dirname(__DIR__) . '/src/js/interpolation.js') ?>"></script>
  <script defer src="../src/js/gis.js?v=<?= filemtime(dirname(__DIR__) . '/src/js/gis.js') ?>"></script>
</head>
<body class="gis-embedded gis-demo-preview">
  <?php require dirname(__DIR__) . '/views/partials/map_explorer.php'; ?>
</body>
</html>
