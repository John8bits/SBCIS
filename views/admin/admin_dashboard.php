<?php
session_start();
header('Cache-Control: no-store');
if (($_SESSION['admin_logged_in'] ?? false) !== true) {
    header('Location: ../../index.php?login=required');
    exit;
}
require_once __DIR__ . '/../../app/Models/geotechnical_data.php';
require_once __DIR__ . '/../../app/Models/locations.php';
$_SESSION['record_csrf'] = $_SESSION['record_csrf'] ?? bin2hex(random_bytes(32));
$dashboardMessage = $_SESSION['dashboard_message'] ?? null;
unset($_SESSION['dashboard_message']);
$dashboardError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['record_csrf'], $_POST['csrf']))
            throw new InvalidArgumentException('Your session expired. Reload the dashboard and try again.');
        if (($_POST['action'] ?? '') !== 'delete')
            throw new InvalidArgumentException('Unknown record action.');
        $recordId = filter_var($_POST['record_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
        $deleteDb = sbcis_get_database();
        if (!$deleteDb || !sbcis_delete_geotechnical_record($deleteDb, $recordId))
            throw new InvalidArgumentException('This record no longer exists.');
        $_SESSION['dashboard_message'] = 'The borehole and all of its soil layers were deleted.';
        header('Location: admin_dashboard.php');
        exit;
    } catch (InvalidArgumentException $error) {
        $dashboardError = $error->getMessage();
    } catch (Throwable $error) {
        error_log('Dashboard delete: ' . $error->getMessage());
        $dashboardError = 'The record could not be deleted. Please try again.';
    }
}
$directory = null;
try {
    $directory = sbcis_locations();
} catch (Throwable $error) {
    error_log('Dashboard locations: ' . $error->getMessage());
}
$escape = static function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$available = false;
$stats = [];
$coverage = [];
$soils = [];
$capacities = [];
$recent = [];
$monthStart = new DateTimeImmutable('first day of this month');
$activity = ['labels' => [], 'boreholes' => [], 'layers' => []];
$activityBuckets = [];
for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
    $month = $monthStart->modify('-' . $monthsAgo . ' months');
    $key = $month->format('Y-m');
    $activity['labels'][] = $month->format('M Y');
    $activityBuckets[$key] = ['boreholes' => 0, 'layers' => 0];
}
try {
    $db = sbcis_get_database();
    if (!$db)
        throw new RuntimeException('Database unavailable.');
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
        HAVING value > 0 ORDER BY value DESC, label ASC LIMIT 8')->fetchAll();
    $soils = $db->query("SELECT COALESCE(NULLIF(TRIM(soil_type), ''), 'Not recorded') AS label, COUNT(*) AS value FROM soil_layers GROUP BY label ORDER BY value DESC, label ASC")->fetchAll();
    if (count($soils) > 6) {
        $other = array_sum(array_column(array_slice($soils, 5), 'value'));
        $soils = array_slice($soils, 0, 5);
        $soils[] = ['label' => 'Other soil types', 'value' => $other];
    }
    $capacities = $db->query("SELECT CASE WHEN bearing_capacity_kpa IS NULL THEN 'Not recorded'
        WHEN bearing_capacity_kpa < 50 THEN 'Below 50 kPa' WHEN bearing_capacity_kpa < 100 THEN '50-99 kPa'
        WHEN bearing_capacity_kpa < 200 THEN '100-199 kPa' WHEN bearing_capacity_kpa <= 300 THEN '200-300 kPa'
        ELSE 'Above 300 kPa' END AS label, COUNT(*) AS value,
        MIN(CASE WHEN bearing_capacity_kpa IS NULL THEN 6 WHEN bearing_capacity_kpa < 50 THEN 1
        WHEN bearing_capacity_kpa < 100 THEN 2 WHEN bearing_capacity_kpa < 200 THEN 3 WHEN bearing_capacity_kpa <= 300 THEN 4 ELSE 5 END) AS position
        FROM soil_layers GROUP BY label ORDER BY position")->fetchAll();
    $activityStatement = $db->prepare("SELECT source, DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS value
        FROM (
            SELECT 'boreholes' AS source, created_at FROM boreholes WHERE created_at >= ?
            UNION ALL
            SELECT 'layers' AS source, created_at FROM soil_layers WHERE created_at >= ?
        ) AS monthly_activity
        GROUP BY source, month_key ORDER BY month_key");
    $activityStart = $monthStart->modify('-11 months')->format('Y-m-01 00:00:00');
    $activityStatement->execute([$activityStart, $activityStart]);
    foreach ($activityStatement->fetchAll() as $row) {
        if (isset($activityBuckets[$row['month_key']][$row['source']]))
            $activityBuckets[$row['month_key']][$row['source']] = (int) $row['value'];
    }
    foreach ($activityBuckets as $bucket) {
        $activity['boreholes'][] = $bucket['boreholes'];
        $activity['layers'][] = $bucket['layers'];
    }
    $recent = sbcis_fetch_recent_boreholes($db, 5);
    $available = true;
} catch (Throwable $error) {
    error_log('Dashboard: ' . $error->getMessage());
}
$metric = static function ($key) use ($available, $stats, $directory) {
    if (in_array($key, ['municipalities', 'barangays'], true))
        return $directory ? number_format(count($directory[$key])) : '—';
    return $available ? number_format((int) $stats[$key]) : '—';
};
$rowChart = static fn(array $rows) => ['labels' => array_column($rows, 'label'), 'values' => array_map('intval', array_column($rows, 'value'))];
$chartData = ['coverage' => $rowChart($coverage), 'soils' => $rowChart($soils), 'capacities' => $rowChart($capacities), 'activity' => $activity];
$hasChartValues = static fn(array $values) => $available && array_sum(array_map('intval', $values)) > 0;
$title = 'Dashboard';
$subtitle = 'Soil investigation overview and recent activity';
$activePage = 'admin_dashboard.php';
$topbarActions = [['href' => 'soil_records.php?new=1', 'label' => 'Add record', 'icon' => 'fa-plus', 'primary' => true]];
$extraHead = '<script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script><script defer src="../../src/js/dashboard_charts.js"></script>';
require __DIR__ . '/overview_shell.php';
?>
<script type="application/json"
    id="dashboard-chart-data"><?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php if ($dashboardMessage): ?>
    <div hidden data-toast data-icon="success" data-title="Deleted"><?= $escape($dashboardMessage) ?></div><?php endif; ?>
<?php if ($dashboardError): ?>
    <div hidden data-toast data-icon="error" data-title="Unable to delete"><?= $escape($dashboardError) ?></div>
<?php endif; ?>
<?php if (!$available): ?>
    <div hidden data-toast data-icon="error" data-title="Database unavailable">Statistics and downloads will be available
        when the connection is restored.</div><?php endif; ?>
<section class="ov-stats" aria-label="Location and soil record totals">
    <?php foreach ([['boreholes', 'Total boreholes', 'fa-location-dot', 'Investigation locations'], ['layers', 'Soil layers', 'fa-layer-group', 'Recorded subsurface layers'], ['municipalities', 'Municipalities', 'fa-map-location-dot', 'Locations in the PSGC directory'], ['barangays', 'Barangays', 'fa-location-crosshairs', 'Locations in the PSGC directory']] as [$key, $label, $icon, $caption]): ?>
        <a class="ov-stat" href="<?= $key === 'layers' ? 'soil_layers' : $key ?>.php">
            <div class="ov-stat-label"><?= $label ?><i class="fa-solid <?= $icon ?>" aria-hidden="true"></i></div>
            <div class="ov-stat-value"><?= $metric($key) ?></div><small><?= $caption ?></small>
        </a>
    <?php endforeach; ?>
</section>
<div class="ov-grid">
    <section class="ov-card chart-card">
        <div class="ov-card-head">
            <div>
                <h3>Monthly record activity</h3>
                <p>New boreholes and soil layers saved over time</p>
            </div>
            <div class="chart-range" aria-label="Activity chart period"><button type="button" data-chart-months="6"
                    aria-pressed="false">6M</button><button type="button" data-chart-months="12" class="active"
                    aria-pressed="true">12M</button></div>
        </div><?php if ($hasChartValues(array_merge($activity['boreholes'], $activity['layers']))): ?>
            <div class="chart-canvas-wrap chart-wide"><canvas id="activityChart" role="img"
                    aria-label="Monthly borehole and soil layer activity chart"></canvas></div>
            <p class="chart-hint">Hover for exact totals. Click a line to open its records.</p><?php else: ?>
            <div class="ov-empty">
                <?= $available ? 'No activity recorded in the last 12 months.' : 'Chart unavailable.<br>Reconnect the database to view your data.' ?>
            </div><?php endif; ?>
    </section>
    <section class="ov-card chart-card">
        <div class="ov-card-head">
            <div>
                <h3>Boreholes by municipality</h3>
                <p>Municipalities with the most investigation locations</p>
            </div>
            <div class="chart-summary">
                <strong><?= number_format(array_sum($chartData['coverage']['values'])) ?></strong><span>shown</span>
            </div>
        </div><?php if ($hasChartValues($chartData['coverage']['values'])): ?>
            <div class="chart-canvas-wrap chart-tall"><canvas id="coverageChart" role="img"
                    aria-label="Boreholes by municipality chart"></canvas></div>
            <p class="chart-hint">Click a bar to view matching borehole records.</p><?php else: ?>
            <div class="ov-empty">
                <?= $available ? 'No municipality coverage data yet.' : 'Chart unavailable.<br>Reconnect the database to view your data.' ?>
            </div><?php endif; ?>
        <p class="ov-note"><?= $metric('covered') ?> municipalities have borehole records. Unassigned locations are
            excluded.</p>
    </section>
    <section class="ov-card chart-card">
        <div class="ov-card-head">
            <div>
                <h3>Soil composition</h3>
                <p>Share of layers by recorded soil type</p>
            </div>
            <div class="chart-summary">
                <strong><?= number_format(array_sum($chartData['soils']['values'])) ?></strong><span>layers</span></div>
        </div><?php if ($hasChartValues($chartData['soils']['values'])): ?>
            <div class="chart-canvas-wrap chart-doughnut"><canvas id="soilChart" role="img"
                    aria-label="Soil composition chart"></canvas></div>
            <p class="chart-hint">Select a legend item to compare categories, or click a segment to view its layers.</p>
        <?php else: ?>
            <div class="ov-empty">
                <?= $available ? 'No soil layers recorded yet.' : 'Chart unavailable.<br>Reconnect the database to view your data.' ?>
            </div><?php endif; ?>
    </section>
    <section class="ov-card chart-card">
        <div class="ov-card-head">
            <div>
                <h3>Bearing capacity distribution</h3>
                <p>Layers grouped by recorded bearing capacity</p>
            </div>
            <div class="chart-summary">
                <strong><?= number_format(array_sum($chartData['capacities']['values'])) ?></strong><span>layers</span>
            </div>
        </div><?php if ($hasChartValues($chartData['capacities']['values'])): ?>
            <div class="chart-canvas-wrap"><canvas id="capacityChart" role="img"
                    aria-label="Bearing capacity distribution chart"></canvas></div>
            <p class="chart-hint">Hover for exact totals. Click a bar to open capacity records.</p><?php else: ?>
            <div class="ov-empty">
                <?= $available ? 'No soil layers recorded yet.' : 'Chart unavailable.<br>Reconnect the database to view your data.' ?>
            </div><?php endif; ?>
        <p class="ov-note">Stored measurements only; no design rating is assigned.</p>
    </section>
</div>
<section class="ov-card quality-card">
    <div class="ov-card-head">
        <div>
            <h3>Record completeness</h3>
            <p>Fields that may need additional measurements</p>
        </div><span
            class="ov-status<?= !$available ? ' offline' : '' ?>"><?= $available ? 'Live database' : 'Unavailable' ?></span>
    </div>
    <div class="ov-quality">
        <div>
            <strong><?= $available && $stats['layers'] ? round(100 * $stats['capacity_count'] / $stats['layers']) . '%' : '—' ?></strong><span>Layers
                with bearing capacity</span></div>
        <div>
            <strong><?= $available && $stats['layers'] ? round(100 * $stats['spt_count'] / $stats['layers']) . '%' : '—' ?></strong><span>Layers
                with SPT values</span></div>
        <div><strong><?= $metric('without_layers') ?></strong><span>Boreholes without layers</span></div>
        <div>
            <strong><?= $available && $stats['average_capacity'] !== null ? number_format((float) $stats['average_capacity'], 1) : '—' ?></strong><span>Mean
                recorded capacity &middot; kPa</span></div>
    </div>
    <div class="ov-callout">
        <div><strong>Keep a copy of your records</strong>
            <p>Download all saved data for backup.</p>
        </div><a class="ov-button secondary" href="data_export.php">Download backup</a>
    </div>
</section>
<section class="ov-card">
    <div class="ov-card-head">
        <div>
            <h3>Recently added boreholes</h3>
            <p>The latest five investigation records</p>
        </div><a class="ov-button secondary" href="boreholes.php">View all <span aria-hidden="true">&rarr;</span></a>
    </div>
    <?php if ($available && $recent): ?>
        <div class="ov-table-scroll">
            <table class="ov-table">
                <thead>
                    <tr>
                        <th>Borehole</th>
                        <th>Location</th>
                        <th>Depth</th>
                        <th>Soil layers</th>
                        <th>Coordinates</th>
                        <th class="record-actions-heading">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><strong><?= $escape($row['borehole_code']) ?></strong></td>
                            <td><?= $escape($row['municipality_name'] ?: 'Unassigned') ?><small><?= $escape($row['barangay_name'] ?: 'Barangay not recorded') ?></small>
                            </td>
                            <td><?= $escape($row['borehole_depth_m']) ?> m</td>
                            <td><?= (int) $row['layer_count'] ?></td>
                            <td><?= $escape($row['latitude']) ?>, <?= $escape($row['longitude']) ?></td>
                            <td class="record-actions"><a class="table-action"
                                    href="soil_records.php?edit=<?= (int) $row['borehole_id'] ?>">Edit</a>
                                <form method="POST" class="delete-record-form"
                                    data-record-name="<?= $escape($row['borehole_code']) ?>"><input type="hidden" name="csrf"
                                        value="<?= $escape($_SESSION['record_csrf']) ?>"><input type="hidden" name="action"
                                        value="delete"><input type="hidden" name="record_id"
                                        value="<?= (int) $row['borehole_id'] ?>"><button class="table-action danger"
                                        type="submit">Delete</button></form>
                            </td>
                        </tr><?php endforeach; ?>
                </tbody>
            </table>
        </div><?php else: ?>
        <div class="ov-empty">
            <?= $available ? 'No boreholes yet. Choose Add record to enter your first borehole.' : 'Recent records are unavailable while the database is disconnected.' ?>
        </div><?php endif; ?>
</section>
</main>
</div>
</body>

</html>