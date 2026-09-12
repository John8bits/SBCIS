<?php
session_start(); header('Cache-Control: no-store');
if (($_SESSION['admin_logged_in'] ?? false) !== true) { header('Location: ../../index.php?login=required'); exit; }
require_once __DIR__ . '/../../app/Models/locations.php';
$escape = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$kind = ($locationKind ?? '') === 'barangays' ? 'barangays' : 'municipalities';
$title = $kind === 'barangays' ? 'Barangays' : 'Municipalities'; $subtitle = 'Southern Leyte locations from the PSGC directory'; $activePage = $kind . '.php';
$topbarActions = [['href' => 'admin_gis.php', 'label' => 'Open map', 'icon' => 'fa-map-location-dot']];
$directory = null; $rows = []; $error = null;
try { $directory = sbcis_locations(); $rows = $directory[$kind]; } catch (Throwable $e) { error_log($e->getMessage()); $error = 'The location directory is unavailable. Please reload this page.'; }
$extraHead = '<script defer src="../../src/js/admin_tables.js"></script>';
require __DIR__ . '/overview_shell.php';
?>
<?php if ($error): ?><div hidden data-toast data-icon="error" data-title="Locations unavailable"><?= $escape($error) ?></div><?php endif; ?>
<section class="panel"><div class="panel-header"><h3>PSGC directory</h3><span><?= count($rows) ?> locations</span></div><div class="table-wrap"><table><thead><tr><th><?= $kind === 'barangays' ? 'Barangay' : 'Municipality / City' ?></th><?php if ($kind === 'barangays'): ?><th>Municipality / City</th><?php else: ?><th>Barangays</th><?php endif; ?><th>PSGC code</th><th>Map</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><strong><?= $escape($row['name']) ?></strong></td><td><?= $kind === 'barangays' ? $escape($row['municipalityName']) : count(array_filter($directory['barangays'], static fn($b) => $b['municipalityCode'] === $row['code'])) ?></td><td><?= $escape($row['code']) ?></td><td><a class="ov-button secondary" href="admin_gis.php?<?= $kind === 'barangays' ? 'barangay' : 'municipality' ?>=<?= rawurlencode($row['code']) ?>" aria-label="View <?= $escape($row['name']) ?> on map">View map</a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php if ($directory): ?><p class="ov-note">Source: <a href="https://psgc.gitlab.io/api/" target="_blank" rel="noopener">PSGC API</a>. Retrieved <?= $escape(substr($directory['fetchedAt'], 0, 10)) ?><?= $directory['status'] === 'live' ? '.' : ' (saved copy).' ?> Locations without a matching boundary remain searchable.</p><?php endif; ?>
</main></div></body></html>
