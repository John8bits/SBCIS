<?php
use App\Controllers\Admin\DataExportController;
use App\Support\AdminSession;
use App\Support\View;

require_once __DIR__ . '/../../config/bootstrap.php';

AdminSession::requireLogin();
$pageData = (new DataExportController())->index($_GET);
$counts = $pageData['counts'];
$available = $pageData['available'];
$error = $pageData['error'];
$escape = [View::class, 'escape'];
$title = 'Export & Backup';
$subtitle = 'Save a secure copy of all stored records';
$activePage = 'data_export.php';
require __DIR__ . '/overview_shell.php';
?>
<?php if ($error): ?>
    <div hidden data-toast data-icon="error" data-title="Export unavailable"><?= $escape($error) ?></div><?php endif; ?>
<section class="ov-card backup-card">
    <div>
        <h3>Complete data backup</h3>
        <p>All municipalities, barangays, boreholes, and soil layers in one file, including IDs, relationships, and
            dates.</p>
        <p class="ov-note">Choose this to keep a recoverable copy of your data. Restoring the SQL file requires a
            database administrator and an empty database. Account passwords are not included.</p>
    </div>
    <?php if ($available): ?><a class="ov-button" href="?download=backup">Download complete backup
            (.sql)</a><?php endif; ?>
</section>
<section class="ov-card">
    <div class="ov-card-head">
        <div>
            <h3>Spreadsheet downloads</h3>
            <p>Open CSV files in Excel or another spreadsheet app. Each download includes all saved records in that
                section.</p>
        </div>
    </div>
    <div class="ov-table-scroll">
        <table class="ov-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Saved records</th>
                    <th>Download</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (['municipalities' => 'Municipalities', 'barangays' => 'Barangays', 'boreholes' => 'Boreholes', 'soil_layers' => 'Soil layers'] as $key => $label): ?>
                    <tr>
                        <td><strong><?= $label ?></strong></td>
                        <td><?= $available ? number_format($counts[$key]) : 'Unavailable' ?></td>
                        <td><?php if ($available): ?><a class="ov-button secondary" href="?download=<?= $key ?>"
                                    aria-label="Download <?= $label ?> CSV">Download
                                    CSV</a><?php else: ?>Unavailable<?php endif; ?></td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="ov-note">Downloads include saved data only. Save any open form first. CSV files are for viewing and
        sharing; use the complete backup to preserve the database structure and exact values.</p>
</section>
</main>
</div>
</body>

</html>