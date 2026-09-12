<?php
// One compact menu shared by every admin page.
if (($_SESSION['admin_logged_in'] ?? false) !== true) { http_response_code(404); exit; }
$sidebarPage = $sidebarPage ?? basename($_SERVER['SCRIPT_NAME'] ?? '');
$sidebarLinks = [
    'admin_dashboard.php' => ['Dashboard', 'fa-chart-line'],
    'soil_records.php' => ['Soil Records', 'fa-database'],
    'boreholes.php' => ['Boreholes', 'fa-location-dot'],
    'soil_layers.php' => ['Soil Layers', 'fa-layer-group'],
    'municipalities.php' => ['Municipalities', 'fa-map-location-dot'],
    'barangays.php' => ['Barangays', 'fa-location-crosshairs'],
    'admin_gis.php' => ['GIS Map', 'fa-map'],
    'soil_reports.php' => ['Soil Reports', 'fa-file-lines'],
    'bearing_capacity.php' => ['Bearing Capacity', 'fa-chart-column'],
    'data_export.php' => ['Export & Backup', 'fa-download'],
];
?>
<aside class="sidebar admin-sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="../../src/images/logo.png" alt="Southern Leyte seal">
        <div class="brand-text"><strong>SOUTHERN LEYTE</strong><span>SOIL INFORMATION SYSTEM</span></div>
    </div>
    <nav class="sidebar-nav" aria-label="Administration">
        <div class="nav-section-title">WORKSPACE</div>
        <?php foreach ($sidebarLinks as $file => [$label, $icon]): ?>
        <a href="<?= $file ?>" class="nav-item<?= $sidebarPage === $file ? ' active' : '' ?>"<?= $sidebarPage === $file ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid <?= $icon ?>" aria-hidden="true"></i><span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="../../app/Controllers/logout.php" class="logout-link" id="logoutButton"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Sign out</span></a>
    </div>
</aside>
