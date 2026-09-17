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
        <p class="ov-note">Each SQL file includes a source row-count manifest and restore-safe foreign-key settings.
            Restore it into an empty database; it never deletes or overwrites existing tables. Administrator accounts
            and password hashes are intentionally not included.</p>
    </div>
    <?php if ($available): ?><a class="ov-button" href="?download=backup">Download complete backup
            (.sql)</a><?php endif; ?>
</section>
<section class="ov-card">
    <div class="ov-card-head">
        <div>
            <h3>Spreadsheet downloads</h3>
            <p>Open CSV files in Excel or another spreadsheet app. “Rows in download” is the exact number of rows
                included in that file.</p>
        </div>
    </div>
    <div class="ov-table-scroll">
        <table class="ov-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Rows in download</th>
                    <th>Record provenance</th>
                    <th>Download</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (['municipalities' => 'Municipalities', 'barangays' => 'Barangays', 'boreholes' => 'Boreholes', 'soil_layers' => 'Soil layers'] as $key => $label): ?>
                    <tr>
                        <td><strong><?= $label ?></strong></td>
                        <td><?= $available ? number_format($counts[$key]['total']) : 'Unavailable' ?></td>
                        <td><?php if (!$available): ?>Unavailable<?php elseif (isset($counts[$key]['field'])): ?><span class="backup-provenance"><strong><?= number_format($counts[$key]['field']) ?></strong> field / <strong><?= number_format($counts[$key]['sample']) ?></strong> sample</span><?php else: ?>Location directory rows<?php endif; ?></td>
                        <td><?php if ($available): ?><a class="ov-button secondary" href="?download=<?= $key ?>"
                                    aria-label="Download <?= $label ?> CSV">Download
                                    CSV</a><?php else: ?>Unavailable<?php endif; ?></td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="ov-note">Sample rows are included in the CSV and SQL backup so the backup exactly matches the current
        database. They are identified separately from field records. Save any open form first.</p>
</section>
</main>
</div>
</body>

</html>
