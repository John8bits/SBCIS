<?php
session_start();
header('Cache-Control: no-store');
if (($_SESSION['admin_logged_in'] ?? false) !== true) { header('Location: ../../index.php?login=required'); exit; }
require_once __DIR__ . '/../../app/Models/data_export.php';
$escape = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$error = null; $counts = []; $available = false;
try {
    $db = sbcis_get_database();
    if (!$db) throw new RuntimeException('Database unavailable.');
    if (isset($_GET['download'])) {
        $dataset = is_string($_GET['download']) ? $_GET['download'] : '';
        if ($dataset !== 'backup' && !isset(sbcis_export_queries()[$dataset])) throw new InvalidArgumentException('Choose one of the downloads below.');
        $stream = sbcis_prepare_export($db, $dataset);
        session_write_close();
        header('Content-Type: ' . ($dataset === 'backup' ? 'application/sql' : 'text/csv') . '; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sbcis_' . $dataset . '_' . gmdate('Y-m-d_His') . ($dataset === 'backup' ? '.sql' : '.csv') . '"');
        header('X-Content-Type-Options: nosniff');
        fpassthru($stream); fclose($stream); exit;
    }
    foreach (array_keys(sbcis_export_queries()) as $table) $counts[$table] = (int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    $available = true;
} catch (InvalidArgumentException $e) { http_response_code(400); $error = $e->getMessage(); }
catch (Throwable $e) { error_log('Export: ' . $e->getMessage()); http_response_code(503); $error = 'Downloads are unavailable right now. Please check the database connection and try again.'; }
$title = 'Export & Backup'; $subtitle = 'Save a secure copy of all stored records'; $activePage = 'data_export.php'; require __DIR__ . '/overview_shell.php';
?>
<?php if ($error): ?><div hidden data-toast data-icon="error" data-title="Export unavailable"><?= $escape($error) ?></div><?php endif; ?>
<section class="ov-card backup-card"><div><h3>Complete data backup</h3><p>All municipalities, barangays, boreholes, and soil layers in one file, including IDs, relationships, and dates.</p><p class="ov-note">Choose this to keep a recoverable copy of your data. Restoring the SQL file requires a database administrator and an empty database. Account passwords are not included.</p></div>
<?php if ($available): ?><a class="ov-button" href="?download=backup">Download complete backup (.sql)</a><?php endif; ?></section>
<section class="ov-card"><div class="ov-card-head"><div><h3>Spreadsheet downloads</h3><p>Open CSV files in Excel or another spreadsheet app. Each download includes all saved records in that section.</p></div></div><div class="ov-table-scroll"><table class="ov-table"><thead><tr><th>Data</th><th>Saved records</th><th>Download</th></tr></thead><tbody>
<?php foreach (['municipalities' => 'Municipalities', 'barangays' => 'Barangays', 'boreholes' => 'Boreholes', 'soil_layers' => 'Soil layers'] as $key => $label): ?><tr><td><strong><?= $label ?></strong></td><td><?= $available ? number_format($counts[$key]) : 'Unavailable' ?></td><td><?php if ($available): ?><a class="ov-button secondary" href="?download=<?= $key ?>" aria-label="Download <?= $label ?> CSV">Download CSV</a><?php else: ?>Unavailable<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><p class="ov-note">Downloads include saved data only. Save any open form first. CSV files are for viewing and sharing; use the complete backup to preserve the database structure and exact values.</p></section>
</main></div></body></html>
