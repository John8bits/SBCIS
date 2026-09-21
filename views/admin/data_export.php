<?php
use App\Controllers\Admin\DataExportController;
use App\Support\AdminSession;
use App\Support\View;

require_once __DIR__ . '/../../config/bootstrap.php';

AdminSession::requireLogin();
$pageData = (new DataExportController())->index($_GET);
$counts = $pageData['counts'];
$directoryCounts = $pageData['directory_counts'];
$available = $pageData['available'];
$error = $pageData['error'];
$escape = [View::class, 'escape'];
$title = 'Export & Backup';
$subtitle = 'Download secure copies of SBCIS records and research data.';
$activePage = 'data_export.php';
$bodyClass = 'export-page';
$exportStyleVersion = filemtime(__DIR__ . '/../../src/css/export-backup.css');
$extraHead = '<link rel="stylesheet" href="../../src/css/export-backup.css?v=' . $exportStyleVersion . '">';
$datasets = [
    'municipalities' => ['label' => 'Municipalities', 'icon' => 'fa-map-location-dot', 'description' => 'Complete Southern Leyte municipality and city reference directory.'],
    'barangays' => ['label' => 'Barangays', 'icon' => 'fa-location-crosshairs', 'description' => 'Complete Southern Leyte barangay directory with parent municipality names.'],
    'boreholes' => ['label' => 'Boreholes', 'icon' => 'fa-location-dot', 'description' => 'Investigation locations, coordinates, depths, and readable location names.'],
    'soil_layers' => ['label' => 'Soil Layers', 'icon' => 'fa-layer-group', 'description' => 'Depth intervals, soil details, SPT values, and bearing-capacity measurements.'],
];
require __DIR__ . '/overview_shell.php';
?>
<?php if ($error): ?><div hidden data-toast data-icon="error" data-title="Export unavailable"><?= $escape($error) ?></div><?php endif; ?>

<section class="export-backup-card" aria-labelledby="database-backup-title">
    <div class="export-backup-icon"><i class="fa-solid fa-database" aria-hidden="true"></i></div>
    <div class="export-backup-copy">
        <span class="export-audience">For developers and technical maintenance</span>
        <h2 id="database-backup-title">Database Backup</h2>
        <p>Create a restorable SQL copy of SBCIS research data. Administrator accounts are intentionally excluded and must be recreated with the protected recovery bootstrap after restore.</p>
        <div class="export-meta" aria-label="Backup details"><span><i class="fa-solid fa-file-code" aria-hidden="true"></i> SQL format</span><span><i class="fa-solid fa-database" aria-hidden="true"></i> Current research records</span><span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Secure account bootstrap required</span></div>
    </div>
    <div class="export-backup-action">
        <?php if ($available): ?><a class="ov-button export-primary-action" href="?download=backup" data-export-download data-download-label="Download SQL Backup"><i class="fa-solid fa-download" aria-hidden="true"></i><span>Download SQL Backup</span></a><?php else: ?><button class="ov-button" type="button" disabled>Database unavailable</button><?php endif; ?>
        <small>Sensitive research data: store encrypted and follow the documented restore/bootstrap procedure.</small>
    </div>
</section>

<section class="export-research" aria-labelledby="research-export-title">
    <div class="export-section-head">
        <div><span class="export-audience">For researchers and authorized administrators</span><h2 id="research-export-title">Research Data Exports</h2><p>Download structured datasets for analysis, documentation, and reporting.</p></div>
        <div class="export-format-note"><i class="fa-solid fa-table" aria-hidden="true"></i><span><strong>UTF-8 CSV</strong><small>Compatible with Excel, Google Sheets, LibreOffice, and analysis tools</small></span></div>
    </div>
    <div class="export-dataset-list">
        <div class="export-dataset-header" aria-hidden="true"><span>Dataset</span><span>Records</span><span>Description</span><span>Download</span></div>
        <?php foreach ($datasets as $key => $dataset): ?>
            <article class="export-dataset-row">
                <div class="export-dataset-name"><span class="export-dataset-icon"><i class="fa-solid <?= $dataset['icon'] ?>" aria-hidden="true"></i></span><div><h3><?= $dataset['label'] ?></h3><small>CSV dataset</small></div></div>
                <div class="export-count"><strong><?= $available ? number_format($counts[$key]['total']) : '—' ?></strong><span>records</span><?php if ($available && isset($counts[$key]['field'])): ?><small><?= number_format($counts[$key]['field']) ?> field · <?= number_format($counts[$key]['sample']) ?> sample</small><?php endif; ?></div>
                <div class="export-description"><p><?= $dataset['description'] ?></p><?php if ($available && isset($directoryCounts[$key])): ?><small>All <?= number_format($directoryCounts[$key]) ?> verified PSGC directory entries are included in this CSV.</small><?php endif; ?><?php if ($key === 'boreholes' || $key === 'soil_layers'): ?><small>The <code>record_source</code> column clearly identifies field and synthetic sample rows.</small><?php endif; ?></div>
                <div class="export-download-cell"><?php if ($available): ?><a class="export-download-button" href="?download=<?= $key ?>" data-export-download data-download-label="CSV"><i class="fa-solid fa-download" aria-hidden="true"></i><span>CSV</span></a><?php else: ?><span class="export-unavailable">Unavailable</span><?php endif; ?></div>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="export-footnote"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Counts come from the same read-only sources used to generate each file. Sample rows remain exportable but are never presented as field observations.</p>
</section>
</main>
</div>
</body>
</html>
