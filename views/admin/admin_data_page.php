<?php
use App\Database\Connection;
use App\Services\AdminDataService;
use App\Support\AdminSession;
use App\Support\View;

require_once __DIR__ . '/../../config/bootstrap.php';

AdminSession::requireLogin();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$pages = [
    'boreholes' => [
        'title' => 'Boreholes',
        'subtitle' => 'Location and drilling depth records',
        'icon' => 'fa-location-dot',
        'eyebrow' => 'LOCATION DATA',
        'search_placeholder' => 'Search borehole code or location',
    ],
    'soil_layers' => [
        'title' => 'Soil Layers',
        'subtitle' => 'Geotechnical layer descriptions and SPT values',
        'icon' => 'fa-layer-group',
        'eyebrow' => 'SOIL DATA',
        'search_placeholder' => 'Search borehole, soil type, or class',
    ],
    'municipalities' => [
        'title' => 'Municipalities',
        'subtitle' => 'Municipalities and cities with saved borehole records',
        'icon' => 'fa-map-location-dot',
        'eyebrow' => 'LOCATION DATA',
        'search_placeholder' => 'Search municipality or city',
    ],
    'barangays' => [
        'title' => 'Barangays',
        'subtitle' => 'Barangays with saved borehole records',
        'icon' => 'fa-location-crosshairs',
        'eyebrow' => 'LOCATION DATA',
        'search_placeholder' => 'Search barangay or municipality',
    ],
    'soil_reports' => [
        'title' => 'Soil Reports',
        'subtitle' => 'Summary of boreholes, soil layers, and capacities',
        'icon' => 'fa-file-lines',
        'eyebrow' => 'REPORTS',
        'search_placeholder' => 'Search borehole or location',
    ],
    'bearing_capacity' => [
        'title' => 'Bearing Capacity',
        'subtitle' => 'Recorded soil bearing capacity by borehole layer',
        'icon' => 'fa-chart-column',
        'eyebrow' => 'REPORTS',
        'search_placeholder' => 'Search borehole or soil type',
    ],
];

$activePage = $activePage ?? 'boreholes';
$page = $pages[$activePage] ?? $pages['boreholes'];
$rows = [];
$pagination = ['total' => 0, 'page' => 1, 'pages' => 1, 'page_size' => 50, 'search' => '', 'sort' => '', 'dir' => 'desc'];
$databaseError = null;

try {
    $db = Connection::get();

    if (!$db instanceof PDO) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    $pagination = (new AdminDataService($db))->page($activePage, $_GET);
    $rows = $pagination['rows'];
} catch (Throwable $e) {
    error_log('Admin data page (' . $activePage . '): ' . $e->getMessage());
    $databaseError = $e instanceof InvalidArgumentException
        ? $e->getMessage()
        : 'The database query could not be completed. Please retry.';
}

$escape = [View::class, 'escape'];

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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5"></script>
    <script src="../../src/js/toast.js"></script>
    <script src="../../src/js/loading-state.js?v=<?= filemtime(__DIR__ . '/../../src/js/loading-state.js') ?>"></script>
    <script src="../../src/js/admin_feedback.js" defer></script>
    <link rel="stylesheet" href="../../src/css/admin_dashb.css">
    <link rel="stylesheet" href="../../src/css/alerts.css?v=<?= filemtime(__DIR__ . '/../../src/css/alerts.css') ?>">
    <script src="../../src/js/modal-origin.js?v=<?= filemtime(__DIR__ . '/../../src/js/modal-origin.js') ?>"></script>
<link rel="stylesheet" href="../../src/css/admin_simple.css?v=<?= filemtime(__DIR__ . '/../../src/css/admin_simple.css') ?>">
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
                <a href="soil_records.php?new=1" class="public-site">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add Soil Record</span>
                </a>
            </div>
        </header>

        <main class="content">
            <?php if ($databaseError): ?>
                <div hidden data-toast data-icon="error" data-title="Database unavailable"><?= $escape($databaseError) ?></div>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-header">
                    <h3>Saved records</h3>
                    <span><?= number_format($pagination['total']) ?> records</span>
                </div>

                <form class="table-toolbar" method="GET" data-server-search role="search">
                    <div class="table-search-control">
                        <label for="record-search">Search records</label>
                        <div class="table-search-input">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input id="record-search" type="search" name="search" maxlength="100" value="<?= $escape($pagination['search']) ?>" placeholder="<?= $escape($page['search_placeholder']) ?>">
                        </div>
                    </div>
                    <div class="table-toolbar-actions">
                        <label class="table-page-size" for="record-page-size">
                            <span>Rows per page</span>
                            <select id="record-page-size" name="page_size" aria-label="Rows per page"><?php foreach ([10, 25, 50, 100] as $size): ?><option value="<?= $size ?>" <?= $pagination['page_size'] === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select>
                        </label>
                        <?php if ($pagination['search'] !== ''): ?><a class="table-clear-button" href="?<?= $escape(http_build_query(['page_size' => $pagination['page_size']])) ?>">Clear</a><?php endif; ?>
                        <button class="table-search-button" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Search</span></button>
                    </div>
                </form>

                <?php if ($rows): ?>
                    <div class="table-wrap" data-server-paginated>
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
                                    <tr><th>Borehole</th><th>Location</th><th>Boundary</th><th>Depth</th><th>Latitude</th><th>Longitude</th><th>Elevation</th><th>Layers</th></tr>
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
                                            <?php $boundaryStatus = $row['boundary_status'] ?? 'unknown'; ?>
                                            <td><span class="badge<?= $boundaryStatus !== 'inside' ? ' danger' : '' ?>"><?= $boundaryStatus === 'outside' ? 'Review: outside study area' : ($boundaryStatus === 'inside' ? 'Inside study area' : 'Boundary unavailable') ?></span></td>
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
                    <?php if ($pagination['pages'] > 1): ?>
                    <nav class="table-footer" aria-label="Record pages">
                        <span>Page <?= number_format($pagination['page']) ?> of <?= number_format($pagination['pages']) ?></span>
                        <div class="table-pages">
                            <?php $baseQuery = ['search'=>$pagination['search'], 'page_size'=>$pagination['page_size'], 'sort'=>$pagination['sort'], 'dir'=>$pagination['dir']]; ?>
                            <?php if ($pagination['page'] > 1): ?><a href="?<?= $escape(http_build_query($baseQuery + ['page'=>$pagination['page'] - 1])) ?>">Previous</a><?php endif; ?>
                            <?php if ($pagination['page'] < $pagination['pages']): ?><a href="?<?= $escape(http_build_query($baseQuery + ['page'=>$pagination['page'] + 1])) ?>">Next</a><?php endif; ?>
                        </div>
                    </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="system-message"><?= $pagination['search'] !== '' ? 'No records match this search.' : 'No records have been added yet.' ?></div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        const mobileMenu = document.getElementById("mobileMenu");
        const sidebar = document.getElementById("sidebar");

        if (mobileMenu && sidebar) {
            mobileMenu.addEventListener("click", function () {
                sidebar.classList.toggle("open");
            });
        }
    </script>
</body>

</html>
