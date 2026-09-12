<?php

session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: ../../index.php?login=required');
    exit;
}

require_once __DIR__ . '/../../app/Models/geotechnical_data.php';

$pages = [
    'boreholes' => [
        'title' => 'Boreholes',
        'subtitle' => 'Location and drilling depth records',
        'icon' => 'fa-location-dot',
        'eyebrow' => 'LOCATION DATA',
    ],
    'soil_layers' => [
        'title' => 'Soil Layers',
        'subtitle' => 'Geotechnical layer descriptions and SPT values',
        'icon' => 'fa-layer-group',
        'eyebrow' => 'SOIL DATA',
    ],
    'municipalities' => [
        'title' => 'Municipalities',
        'subtitle' => 'Municipalities and cities with saved borehole records',
        'icon' => 'fa-map-location-dot',
        'eyebrow' => 'LOCATION DATA',
    ],
    'barangays' => [
        'title' => 'Barangays',
        'subtitle' => 'Barangays with saved borehole records',
        'icon' => 'fa-location-crosshairs',
        'eyebrow' => 'LOCATION DATA',
    ],
    'soil_reports' => [
        'title' => 'Soil Reports',
        'subtitle' => 'Summary of boreholes, soil layers, and capacities',
        'icon' => 'fa-file-lines',
        'eyebrow' => 'REPORTS',
    ],
    'bearing_capacity' => [
        'title' => 'Bearing Capacity',
        'subtitle' => 'Recorded soil bearing capacity by borehole layer',
        'icon' => 'fa-chart-column',
        'eyebrow' => 'REPORTS',
    ],
];

$activePage = $activePage ?? 'boreholes';
$page = $pages[$activePage] ?? $pages['boreholes'];
$rows = [];
$databaseError = null;

try {
    $db = sbcis_get_database();

    if (!$db instanceof PDO) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    switch ($activePage) {
        case 'soil_layers':
            $rows = $db->query("
                SELECT
                    sl.layer_number,
                    b.borehole_code,
                    sl.soil_type,
                    sl.soil_classification,
                    sl.soil_description,
                    sl.depth_from_m,
                    sl.depth_to_m,
                    sl.spt_n_value,
                    sl.bearing_capacity_kpa
                FROM soil_layers sl
                INNER JOIN boreholes b ON sl.borehole_id = b.borehole_id
                ORDER BY b.borehole_code ASC, sl.layer_number ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'municipalities':
            $rows = $db->query("
                SELECT
                    m.municipality_name,
                    COUNT(DISTINCT b.borehole_id) AS borehole_count,
                    COUNT(sl.soil_layer_id) AS layer_count
                FROM municipalities m
                LEFT JOIN boreholes b ON m.municipality_id = b.municipality_id
                LEFT JOIN soil_layers sl ON b.borehole_id = sl.borehole_id
                GROUP BY m.municipality_id
                ORDER BY m.municipality_name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'barangays':
            $rows = $db->query("
                SELECT
                    br.barangay_name,
                    m.municipality_name,
                    COUNT(DISTINCT b.borehole_id) AS borehole_count,
                    COUNT(sl.soil_layer_id) AS layer_count
                FROM barangays br
                INNER JOIN municipalities m ON br.municipality_id = m.municipality_id
                LEFT JOIN boreholes b ON br.barangay_id = b.barangay_id
                LEFT JOIN soil_layers sl ON b.borehole_id = sl.borehole_id
                GROUP BY br.barangay_id
                ORDER BY m.municipality_name ASC, br.barangay_name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'soil_reports':
            $rows = $db->query("
                SELECT
                    b.borehole_code,
                    m.municipality_name,
                    br.barangay_name,
                    b.borehole_depth_m,
                    COUNT(sl.soil_layer_id) AS layer_count,
                    MIN(sl.depth_from_m) AS shallowest_layer_m,
                    MAX(sl.depth_to_m) AS deepest_layer_m,
                    MAX(sl.bearing_capacity_kpa) AS highest_capacity_kpa
                FROM boreholes b
                LEFT JOIN municipalities m ON b.municipality_id = m.municipality_id
                LEFT JOIN barangays br ON b.barangay_id = br.barangay_id
                LEFT JOIN soil_layers sl ON b.borehole_id = sl.borehole_id
                GROUP BY b.borehole_id
                ORDER BY b.created_at DESC, b.borehole_id DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'bearing_capacity':
            $rows = $db->query("
                SELECT
                    b.borehole_code,
                    sl.layer_number,
                    sl.soil_type,
                    sl.depth_from_m,
                    sl.depth_to_m,
                    sl.spt_n_value,
                    sl.bearing_capacity_kpa
                FROM soil_layers sl
                INNER JOIN boreholes b ON sl.borehole_id = b.borehole_id
                ORDER BY sl.bearing_capacity_kpa DESC, b.borehole_code ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'boreholes':
        default:
            $rows = sbcis_fetch_recent_boreholes($db, null);
            break;
    }
} catch (Throwable $e) {
    $databaseError = $e->getMessage();
}

$escape = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b3d2e">
    <title><?= $escape($page['title']) ?> | Southern Leyte SBCIS</title>
    <link rel="icon" type="image/png" href="../../src/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../src/css/admin_dashb.css">
<link rel="stylesheet" href="../../src/css/admin_simple.css">
<script src="../../src/js/admin_tables.js" defer></script>
</head>

<body>
    <?php $sidebarPage = $activePage . '.php'; require __DIR__ . '/sidebar.php'; ?>

    <div class="main">
        <header class="topbar">
            <div class="topbar-title">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Open menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-title-icon">
                    <i class="fa-solid <?= $escape($page['icon']) ?>"></i>
                </div>
                <div>
                    <h1><?= $escape($page['title']) ?></h1>
                    <p><?= $escape($page['subtitle']) ?></p>
                </div>
            </div>
            <div class="topbar-actions">
                <a href="data_export.php" class="public-site">Export &amp; backup</a>
                <a href="soil_records.php?new=1" class="public-site">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add Soil Record</span>
                </a>
            </div>
        </header>

        <main class="content">
            <div class="page-heading">
                <div class="eyebrow"><?= $escape($page['eyebrow']) ?></div>
                <h2><?= $escape($page['title']) ?></h2>
                <p><?= $escape($page['subtitle']) ?></p>
            </div>

            <?php if ($databaseError): ?>
                <div class="system-message error">
                    <strong>Database unavailable.</strong><br>
                    <?= $escape($databaseError) ?>
                </div>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-header">
                    <h3><?= $escape($page['title']) ?></h3>
                    <span><?= number_format(count($rows)) ?> records</span>
                </div>

                <?php if ($rows): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <?php if ($activePage === 'soil_layers'): ?>
                                    <tr><th>Borehole</th><th>Layer</th><th>Soil Type</th><th>Class</th><th>Description</th><th>Depth</th><th>SPT N</th><th>Capacity</th></tr>
                                <?php elseif ($activePage === 'municipalities'): ?>
                                    <tr><th>Municipality / City</th><th>Boreholes</th><th>Soil Layers</th></tr>
                                <?php elseif ($activePage === 'barangays'): ?>
                                    <tr><th>Barangay</th><th>Municipality</th><th>Boreholes</th><th>Soil Layers</th></tr>
                                <?php elseif ($activePage === 'soil_reports'): ?>
                                    <tr><th>Borehole</th><th>Location</th><th>Borehole Depth</th><th>Layers</th><th>Layer Range</th><th>Highest Capacity</th></tr>
                                <?php elseif ($activePage === 'bearing_capacity'): ?>
                                    <tr><th>Borehole</th><th>Layer</th><th>Soil Type</th><th>Depth</th><th>SPT N</th><th>Bearing Capacity</th></tr>
                                <?php else: ?>
                                    <tr><th>Borehole</th><th>Location</th><th>Depth</th><th>Latitude</th><th>Longitude</th><th>Elevation</th><th>Layers</th></tr>
                                <?php endif; ?>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <?php if ($activePage === 'soil_layers'): ?>
                                        <tr>
                                            <td><strong><?= $escape($row['borehole_code']) ?></strong></td>
                                            <td><?= number_format((int) $row['layer_number']) ?></td>
                                            <td><?= $escape($row['soil_type']) ?></td>
                                            <td><?= $escape($row['soil_classification'] ?: 'Unclassified') ?></td>
                                            <td><?= $escape($row['soil_description'] ?: 'No description') ?></td>
                                            <td><?= $escape($row['depth_from_m']) ?> - <?= $escape($row['depth_to_m']) ?> m</td>
                                            <td><?= $escape($row['spt_n_value'] ?? 'Not recorded') ?></td>
                                            <td><?= $escape($row['bearing_capacity_kpa'] ?? 'Not recorded') ?><?= $row['bearing_capacity_kpa'] !== null ? ' kPa' : '' ?></td>
                                        </tr>
                                    <?php elseif ($activePage === 'municipalities'): ?>
                                        <tr>
                                            <td><strong><?= $escape($row['municipality_name']) ?></strong></td>
                                            <td><span class="badge"><?= number_format((int) $row['borehole_count']) ?></span></td>
                                            <td><?= number_format((int) $row['layer_count']) ?></td>
                                        </tr>
                                    <?php elseif ($activePage === 'barangays'): ?>
                                        <tr>
                                            <td><strong><?= $escape($row['barangay_name']) ?></strong></td>
                                            <td><?= $escape($row['municipality_name']) ?></td>
                                            <td><span class="badge"><?= number_format((int) $row['borehole_count']) ?></span></td>
                                            <td><?= number_format((int) $row['layer_count']) ?></td>
                                        </tr>
                                    <?php elseif ($activePage === 'soil_reports'): ?>
                                        <tr>
                                            <td><strong><?= $escape($row['borehole_code']) ?></strong></td>
                                            <td><?= $escape(trim(($row['barangay_name'] ?: '') . ', ' . ($row['municipality_name'] ?: ''), ', ') ?: 'Not recorded') ?></td>
                                            <td><?= $escape($row['borehole_depth_m']) ?> m</td>
                                            <td><span class="badge"><?= number_format((int) $row['layer_count']) ?></span></td>
                                            <td><?= $row['shallowest_layer_m'] !== null ? $escape($row['shallowest_layer_m']) . ' - ' . $escape($row['deepest_layer_m']) . ' m' : 'No layers' ?></td>
                                            <td><?= $row['highest_capacity_kpa'] !== null ? $escape($row['highest_capacity_kpa']) . ' kPa' : 'Not recorded' ?></td>
                                        </tr>
                                    <?php elseif ($activePage === 'bearing_capacity'): ?>
                                        <tr>
                                            <td><strong><?= $escape($row['borehole_code']) ?></strong></td>
                                            <td><?= number_format((int) $row['layer_number']) ?></td>
                                            <td><?= $escape($row['soil_type']) ?></td>
                                            <td><?= $escape($row['depth_from_m']) ?> - <?= $escape($row['depth_to_m']) ?> m</td>
                                            <td><?= $escape($row['spt_n_value'] ?? 'Not recorded') ?></td>
                                            <td><?= $row['bearing_capacity_kpa'] !== null ? $escape($row['bearing_capacity_kpa']) . ' kPa' : 'Not recorded' ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td><strong><?= $escape($row['borehole_code']) ?></strong></td>
                                            <td><?= $escape(trim(($row['barangay_name'] ?: '') . ', ' . ($row['municipality_name'] ?: ''), ', ') ?: 'Not recorded') ?></td>
                                            <td><?= $escape($row['borehole_depth_m']) ?> m</td>
                                            <td><?= $escape($row['latitude']) ?></td>
                                            <td><?= $escape($row['longitude']) ?></td>
                                            <td><?= $row['elevation_m'] !== null ? $escape($row['elevation_m']) . ' m' : 'Not recorded' ?></td>
                                            <td><span class="badge"><?= number_format((int) $row['layer_count']) ?></span></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="system-message">No records found for this page yet.</div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        const mobileMenu = document.getElementById("mobileMenu");
        const sidebar = document.getElementById("sidebar");
        const logoutButton = document.getElementById("logoutButton");

        if (mobileMenu && sidebar) {
            mobileMenu.addEventListener("click", function () {
                sidebar.classList.toggle("open");
            });
        }

        if (logoutButton) {
            logoutButton.addEventListener("click", function (event) {
                event.preventDefault();

                Swal.fire({
                    icon: "question",
                    title: "Sign out?",
                    text: "You will be returned to the public site.",
                    showCancelButton: true,
                    confirmButtonText: "Sign out",
                    cancelButtonText: "Stay",
                    confirmButtonColor: "#0b3d2e"
                }).then(function (result) {
                    if (result.isConfirmed) {
                        window.location.href = logoutButton.href;
                    }
                });
            });
        }
    </script>
</body>

</html>
