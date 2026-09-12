<?php
session_start();
header('Cache-Control: no-store');
if (($_SESSION['admin_logged_in'] ?? false) !== true) { header('Location: ../../index.php?login=required'); exit; }
require_once __DIR__ . '/../../app/Models/geotechnical_data.php';
require_once __DIR__ . '/../../app/Models/locations.php';
$directory = null;
try { $directory = sbcis_locations(); } catch (Throwable $error) { error_log('Dashboard locations: ' . $error->getMessage()); }
$escape = static function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$available = false; $stats = []; $coverage = []; $soils = []; $capacities = []; $recent = [];
try {
    $db = sbcis_get_database();
    if (!$db) throw new RuntimeException('Database unavailable.');
    $stats = $db->query('SELECT
        (SELECT COUNT(*) FROM boreholes) AS boreholes,
        (SELECT COUNT(*) FROM soil_layers) AS layers,
        (SELECT COUNT(*) FROM municipalities) AS municipalities,
        (SELECT COUNT(*) FROM barangays) AS barangays,
        (SELECT COUNT(DISTINCT municipality_id) FROM boreholes) AS covered,
        (SELECT COUNT(*) FROM soil_layers WHERE bearing_capacity_kpa IS NOT NULL) AS capacity_count,
        (SELECT AVG(bearing_capacity_kpa) FROM soil_layers) AS average_capacity,
        (SELECT COUNT(*) FROM soil_layers WHERE spt_n_value IS NOT NULL) AS spt_count,
        (SELECT COUNT(*) FROM boreholes b WHERE NOT EXISTS (SELECT 1 FROM soil_layers s WHERE s.borehole_id = b.borehole_id)) AS without_layers')->fetch();
    $coverage = $db->query('SELECT m.municipality_name AS label, COUNT(b.borehole_id) AS value FROM municipalities m
        LEFT JOIN boreholes b ON b.municipality_id = m.municipality_id GROUP BY m.municipality_id, m.municipality_name
        ORDER BY value DESC, label ASC LIMIT 6')->fetchAll();
    $soils = $db->query("SELECT COALESCE(NULLIF(TRIM(soil_type), ''), 'Not recorded') AS label, COUNT(*) AS value FROM soil_layers GROUP BY label ORDER BY value DESC, label ASC")->fetchAll();
    if (count($soils) > 6) { $other = array_sum(array_column(array_slice($soils, 5), 'value')); $soils = array_slice($soils, 0, 5); $soils[] = ['label' => 'Other soil types', 'value' => $other]; }
    $capacities = $db->query("SELECT CASE WHEN bearing_capacity_kpa IS NULL THEN 'Not recorded'
        WHEN bearing_capacity_kpa < 50 THEN 'Below 50 kPa' WHEN bearing_capacity_kpa < 100 THEN '50–<100 kPa'
        WHEN bearing_capacity_kpa < 200 THEN '100–<200 kPa' WHEN bearing_capacity_kpa <= 300 THEN '200–300 kPa'
        ELSE 'Above 300 kPa' END AS label, COUNT(*) AS value,
        MIN(CASE WHEN bearing_capacity_kpa IS NULL THEN 6 WHEN bearing_capacity_kpa < 50 THEN 1
        WHEN bearing_capacity_kpa < 100 THEN 2 WHEN bearing_capacity_kpa < 200 THEN 3 WHEN bearing_capacity_kpa <= 300 THEN 4 ELSE 5 END) AS position
        FROM soil_layers GROUP BY label ORDER BY position")->fetchAll();
    $recent = sbcis_fetch_recent_boreholes($db, 5); $available = true;
} catch (Throwable $error) { error_log('Dashboard: ' . $error->getMessage()); }
$metric = static function ($key) use ($available, $stats, $directory) {
    if (in_array($key, ['municipalities', 'barangays'], true)) return $directory ? number_format(count($directory[$key])) : '—';
    return $available ? number_format((int)$stats[$key]) : '—';
};
$bars = static function (array $rows, string $class) use ($escape, $available) {
    if (!$available || !$rows || !array_sum(array_column($rows, 'value'))) { echo '<div class="ov-empty">' . ($available ? 'No records yet.<br>Add soil records to populate this chart.' : 'Chart unavailable.<br>Reconnect the database to view your data.') . '</div>'; return; }
    $max = max(array_column($rows, 'value')); echo '<div class="ov-bars ' . $class . '">';
    foreach ($rows as $row) echo '<div><div class="ov-bar-label"><span>' . $escape($row['label']) . '</span><strong>' . number_format((int)$row['value']) . '</strong></div><div class="ov-track" aria-hidden="true"><div class="ov-fill" style="width:' . round(100 * $row['value'] / $max, 2) . '%"></div></div></div>';
    echo '</div>';
};
$title = 'Dashboard'; $activePage = 'admin_dashboard.php';
$extraHead = '<link rel="stylesheet" href="../../src/css/map_frames.css">';
require __DIR__ . '/overview_shell.php';
?>
<div class="ov-heading"><div><h2>Dashboard overview</h2><p>A summary of your saved soil and location records.</p></div><div class="ov-actions"><a class="ov-button secondary" href="soil_records.php?new=1">Add record</a><a class="ov-button" href="data_export.php">Export &amp; backup</a></div></div>
<?php if (!$available): ?><div class="ov-alert" role="status"><strong>Database unavailable.</strong> Statistics and downloads will be available when the connection is restored.</div><?php endif; ?>
<section class="ov-stats" aria-label="Location and soil record totals">
<?php foreach ([['boreholes','Total boreholes','fa-location-dot','Investigation locations'],['layers','Soil layers','fa-layer-group','Recorded subsurface layers'],['municipalities','Municipalities','fa-map-location-dot','Locations in the PSGC directory'],['barangays','Barangays','fa-location-crosshairs','Locations in the PSGC directory']] as [$key,$label,$icon,$caption]): ?>
<a class="ov-stat" href="<?= $key === 'layers' ? 'soil_layers' : $key ?>.php"><div class="ov-stat-label"><?= $label ?><i class="fa-solid <?= $icon ?>" aria-hidden="true"></i></div><div class="ov-stat-value"><?= $metric($key) ?></div><small><?= $caption ?></small></a>
<?php endforeach; ?></section>
<div class="ov-grid">
<section class="ov-card"><div class="ov-card-head"><div><h3>Boreholes by municipality</h3><p>Top 6 municipalities by number of boreholes</p></div><span class="ov-tag">COVERAGE</span></div><?php $bars($coverage, ''); ?><p class="ov-note"><?= $metric('covered') ?> municipalities have borehole records. Unassigned locations are excluded from this chart.</p></section>
<section class="ov-card"><div class="ov-card-head"><div><h3>Soil composition</h3><p>Number of layers by recorded soil type</p></div><span class="ov-tag">SOIL PROFILE</span></div><?php $bars($soils, 'soil'); ?></section>
<section class="ov-card"><div class="ov-card-head"><div><h3>Bearing capacity distribution</h3><p>Number of layers in each recorded capacity range</p></div><span class="ov-tag">kPa</span></div><?php $bars($capacities, 'capacity'); ?><p class="ov-note">Descriptive ranges of stored measurements; no design rating is assigned.</p></section>
<section class="ov-card"><div class="ov-card-head"><div><h3>Record completeness</h3><p>See where additional measurements are needed</p></div><span class="ov-status<?= !$available ? ' offline' : '' ?>"><?= $available ? 'Live database' : 'Unavailable' ?></span></div>
<div class="ov-quality"><div><strong><?= $available && $stats['layers'] ? round(100 * $stats['capacity_count'] / $stats['layers']) . '%' : '—' ?></strong><span>Layers with bearing capacity</span></div><div><strong><?= $available && $stats['layers'] ? round(100 * $stats['spt_count'] / $stats['layers']) . '%' : '—' ?></strong><span>Layers with SPT values</span></div><div><strong><?= $metric('without_layers') ?></strong><span>Boreholes without layers</span></div><div><strong><?= $available && $stats['average_capacity'] !== null ? number_format((float)$stats['average_capacity'], 1) : '—' ?></strong><span>Mean recorded capacity · kPa</span></div></div>
<div class="ov-callout"><div><strong>Keep a copy of your records</strong><p>Download all saved data for backup.</p></div><a class="ov-button secondary" href="data_export.php">Download backup</a></div></section>
</div>
<section class="ov-card"><div class="ov-card-head"><div><h3>Recently added boreholes</h3><p>The latest five investigation records</p></div><a class="ov-button secondary" href="boreholes.php">View all →</a></div>
<?php if ($available && $recent): ?><div class="ov-table-scroll"><table class="ov-table"><thead><tr><th>Borehole</th><th>Location</th><th>Depth</th><th>Soil layers</th><th>Coordinates</th></tr></thead><tbody>
<?php foreach ($recent as $row): ?><tr><td><strong><?= $escape($row['borehole_code']) ?></strong></td><td><?= $escape($row['municipality_name'] ?: 'Unassigned') ?><small><?= $escape($row['barangay_name'] ?: 'Barangay not recorded') ?></small></td><td><?= $escape($row['borehole_depth_m']) ?> m</td><td><?= (int)$row['layer_count'] ?></td><td><?= $escape($row['latitude']) ?>, <?= $escape($row['longitude']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php else: ?><div class="ov-empty"><?= $available ? 'No boreholes yet. Choose Add record to enter your first borehole.' : 'Recent records are unavailable while the database is disconnected.' ?></div><?php endif; ?></section>
<section class="ov-card dashboard-map"><div class="ov-card-head"><div><h3>Explore Southern Leyte</h3><p>Search municipalities and barangays, then view their boundaries and saved soil records.</p></div><a class="ov-button secondary" href="admin_gis.php">Open full map</a></div><iframe class="shared-map-frame" src="../map_embed.php" title="Southern Leyte municipality and barangay search" loading="lazy"></iframe></section>
</main></div></body></html>
