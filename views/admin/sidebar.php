<?php
// One compact menu shared by every admin page.
if (($_SESSION['admin_logged_in'] ?? false) !== true) { http_response_code(404); exit; }
$sidebarPage = $sidebarPage ?? basename($_SERVER['SCRIPT_NAME'] ?? '');
$settingsSection = $sidebarPage === 'settings.php' ? ($_GET['section'] ?? 'account') : '';
$settingsOpen = $sidebarPage === 'settings.php';
$isSuperAdmin = App\Support\AdminSession::isSuperAdmin();
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
        <button type="button" class="nav-item nav-item-toggle<?= $settingsOpen ? ' active' : '' ?>" data-settings-toggle
            aria-expanded="<?= $settingsOpen ? 'true' : 'false' ?>" aria-controls="settingsSubmenu">
            <i class="fa-solid fa-gear" aria-hidden="true"></i><span>Settings</span><i class="fa-solid fa-chevron-down nav-chevron" aria-hidden="true"></i>
        </button>
        <div class="nav-submenu<?= $settingsOpen ? ' open' : '' ?>" id="settingsSubmenu">
            <a href="settings.php?section=account" class="nav-subitem<?= $settingsSection === 'account' ? ' active' : '' ?>"<?= $settingsSection === 'account' ? ' aria-current="page"' : '' ?>>Account &amp; Security</a>
            <?php if ($isSuperAdmin): ?>
                <a href="settings.php?section=accounts" class="nav-subitem<?= $settingsSection === 'accounts' ? ' active' : '' ?>"<?= $settingsSection === 'accounts' ? ' aria-current="page"' : '' ?>>Administrator Accounts</a>
                <a href="settings.php?section=audit" class="nav-subitem<?= $settingsSection === 'audit' ? ' active' : '' ?>"<?= $settingsSection === 'audit' ? ' aria-current="page"' : '' ?>>Admin Audit History</a>
                <a href="settings.php?section=system" class="nav-subitem<?= $settingsSection === 'system' ? ' active' : '' ?>"<?= $settingsSection === 'system' ? ' aria-current="page"' : '' ?>>System Settings</a>
            <?php endif; ?>
            <a href="settings.php?section=notifications" class="nav-subitem<?= $settingsSection === 'notifications' ? ' active' : '' ?>"<?= $settingsSection === 'notifications' ? ' aria-current="page"' : '' ?>>Notifications &amp; Alerts</a>
        </div>
    </nav>
    <div class="sidebar-footer">
        <form method="post" action="../../app/Controllers/logout.php" class="logout-form" id="logoutForm">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) ($_SESSION['logout_csrf'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="logout-link" id="logoutButton"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Sign out</span></button>
        </form>
    </div>
</aside>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-settings-toggle]');
    const submenu = document.getElementById('settingsSubmenu');
    if (!toggle || !submenu) return;
    toggle.addEventListener('click', () => {
        const open = submenu.classList.toggle('open');
        toggle.classList.toggle('active', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
});
</script>
