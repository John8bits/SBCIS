<?php
use App\Controllers\Admin\LocationDirectoryController;
use App\Models\LocationDirectory;
use App\Support\AdminSession;
use App\Support\View;

require_once __DIR__ . '/../../config/bootstrap.php';

AdminSession::requireLogin();
$pageData = (new LocationDirectoryController(new LocationDirectory()))->index($locationKind ?? '');
$kind = $pageData['kind'];
$directory = $pageData['directory'];
$rows = $pageData['rows'];
$error = $pageData['error'];
$escape = [View::class, 'escape'];
$title = $kind === 'barangays' ? 'Barangays' : 'Municipalities'; $subtitle = 'Southern Leyte locations from the PSGC directory'; $activePage = $kind . '.php';
$topbarActions = [['href' => 'admin_gis.php', 'label' => 'Open map', 'icon' => 'fa-map-location-dot']];
$extraHead = '<script defer src="../../src/js/admin_tables.js"></script>';
require __DIR__ . '/overview_shell.php';
?>
<?php if ($error): ?><div hidden data-toast data-icon="error" data-title="Locations unavailable"><?= $escape($error) ?></div><?php endif; ?>
<section class="panel location-directory-panel">
    <div class="panel-header">
        <h3>PSGC directory</h3>
        <span><?= number_format(count($rows)) ?> locations</span>
    </div>
    <div class="table-toolbar" data-local-table-toolbar role="search">
        <div class="table-search-control">
            <label for="location-directory-search">Search <?= $kind === 'barangays' ? 'barangays' : 'municipalities' ?></label>
            <div class="table-search-input">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input id="location-directory-search" data-table-search type="search" maxlength="100"
                    placeholder="<?= $kind === 'barangays' ? 'Barangay or municipality name' : 'Municipality or city name' ?>"
                    autocomplete="off">
            </div>
        </div>
        <div class="table-toolbar-actions">
            <label class="table-page-size" for="location-directory-page-size">
                <span>Rows per page</span>
                <select id="location-directory-page-size" data-table-page-size aria-label="Rows per page">
                    <?php foreach ([10, 25, 50, 100] as $size): ?><option value="<?= $size ?>" <?= $size === 25 ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?>
                </select>
            </label>
            <button class="table-clear-button" data-table-clear type="button" hidden>Clear</button>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th><?= $kind === 'barangays' ? 'Barangay' : 'Municipality / City' ?></th><?php if ($kind === 'barangays'): ?><th>Municipality / City</th><?php else: ?><th>Barangays</th><?php endif; ?><th>PSGC code</th><th>Map</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr><td><strong><?= $escape($row['name']) ?></strong></td><td><?= $kind === 'barangays' ? $escape($row['municipalityName']) : count(array_filter($directory['barangays'], static fn($b) => $b['municipalityCode'] === $row['code'])) ?></td><td><?= $escape($row['code']) ?></td><td><a class="ov-button secondary" href="admin_gis.php?<?= $kind === 'barangays' ? 'barangay' : 'municipality' ?>=<?= rawurlencode($row['code']) ?>" aria-label="View <?= $escape($row['name']) ?> on map">View map</a></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($directory): ?><p class="ov-note">Source: <a href="https://psgc.gitlab.io/api/" target="_blank" rel="noopener">PSGC API</a>. Retrieved <?= $escape(substr($directory['fetchedAt'], 0, 10)) ?><?= $directory['status'] === 'live' ? '.' : ' (saved copy).' ?> Locations without a matching boundary remain searchable.</p><?php endif; ?>
</main></div></body></html>
