<?php
use App\Database\Connection;
use App\Controllers\InterpolationController;
use App\Models\GeotechnicalRepository;
use App\Models\LocationDirectory;
use App\Services\AdminDataService;
use App\Support\AdminSession;
use App\Support\View;

require_once __DIR__ . '/../../config/bootstrap.php';

AdminSession::requireLogin();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$escape = [View::class, 'escape'];

$databaseAvailable = false;
$databaseError = null;
$successMessage = $_SESSION['record_success'] ?? null;
unset($_SESSION['record_success']);
$_SESSION['record_csrf'] = $_SESSION['record_csrf'] ?? bin2hex(random_bytes(32));
$errorMessage = null;
$recentBoreholes = [];
$recordPagination = ['total'=>0, 'page'=>1, 'pages'=>1, 'page_size'=>50, 'search'=>'', 'sort'=>'created', 'dir'=>'desc'];
$municipalityOptions = [];
$barangayOptions = [];
$formData = [];
$editingId = 0;

/**
 * Publish a fresh surface whenever the underlying borehole data changes.
 * A failed surface generation never removes the last valid published result.
 */
function refreshMapInterpolation(): string
{
    try {
        $database = Connection::get();
        $boreholeCount = (int) $database->query('SELECT COUNT(*) FROM boreholes WHERE archived_at IS NULL')->fetchColumn();
        if ($boreholeCount > Config\InterpolationConfig::SYNCHRONOUS_REGENERATION_MAX_POINTS) {
            return ' The interpolation source was invalidated; regenerate it from GIS Map or the scheduled CLI job.';
        }
        $state = (new InterpolationController())->publication()->regenerate();
        if (($state['status'] ?? '') === 'current') return ' The GIS interpolation has been refreshed.';
        return ' The borehole marker is saved; the GIS surface will update when enough valid bearing-capacity records are available.';
    } catch (Throwable $error) {
        error_log('Interpolation refresh after record save: ' . $error->getMessage());
        return ' The borehole marker is saved. The GIS surface can be refreshed from GIS Map.';
    }
}

try {
    $locationDirectory = (new LocationDirectory())->all();
    $municipalityOptions = array_column($locationDirectory['municipalities'], 'name');
    $barangayOptions = array_map(static fn($row) => [
        'id' => $row['code'], 'name' => $row['name'], 'municipalityName' => $row['municipalityName'],
        'boundaryId' => $row['boundaryId'] ?? null
    ], $locationDirectory['barangays']);
} catch (Throwable $e) { error_log('Entry locations: ' . $e->getMessage()); }

try {
    $db = Connection::get();

    if ($db instanceof PDO) {
        $records = new GeotechnicalRepository($db);
        $databaseAvailable = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['record_csrf'], $_POST['csrf'])) {
                    throw new InvalidArgumentException('Your form session has expired. Please try saving again.');
                }
                $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : 'save';
                $recordId = filter_var($_POST['record_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
                if ($action === 'archive') {
                    if (!$records->archive($recordId, (int)$_SESSION['admin_id'])) throw new InvalidArgumentException('This record no longer exists or is already archived.');
                    $_SESSION['record_success'] = 'The borehole was archived and removed from public data.' . refreshMapInterpolation();
                    header('Location: soil_records.php'); exit;
                }
                if ($action === 'restore') {
                    if (!$records->restore($recordId, (int)$_SESSION['admin_id'])) throw new InvalidArgumentException('This record no longer exists or is already active.');
                    $_SESSION['record_success'] = 'The borehole was restored.' . refreshMapInterpolation();
                    header('Location: soil_records.php'); exit;
                }
                if ($action !== 'save') throw new InvalidArgumentException('Unknown record action.');
                $chosenMunicipality = trim((string)($_POST['municipality_name'] ?? ''));
                $chosenBarangay = trim((string)($_POST['barangay_name'] ?? ''));
                if ($chosenMunicipality !== '' && !in_array($chosenMunicipality, $municipalityOptions, true)) {
                    throw new InvalidArgumentException('Choose a municipality from the location list.');
                }
                if ($chosenBarangay !== '' && !array_filter($barangayOptions, static fn($row) => $row['name'] === $chosenBarangay && $row['municipalityName'] === $chosenMunicipality)) {
                    throw new InvalidArgumentException('Choose a barangay belonging to the selected municipality.');
                }
                if ($recordId) {
                    $records->update($recordId, $_POST, (int)$_SESSION['admin_id']);
                    $_SESSION['record_success'] = 'The record and its soil layers were updated.' . refreshMapInterpolation();
                } else {
                    $records->create($_POST, (int)$_SESSION['admin_id']);
                    $_SESSION['record_success'] = 'Your record has been saved and is now listed below.' . refreshMapInterpolation();
                }
                // Keep the administrator in the records workspace so multiple
                // boreholes can be encoded without navigating back from the map.
                header('Location: soil_records.php'); exit;
            } catch (InvalidArgumentException $e) {
                $errorMessage = $e->getMessage();
                $formData = $_POST;
                $editingId = (int)($_POST['record_id'] ?? 0);
            } catch (Throwable $e) {
                error_log('Save soil record: ' . $e->getMessage());
                $errorMessage = $e instanceof PDOException && $e->getCode() === '23000'
                    ? 'This borehole ID already exists or a value conflicts with saved data. Check the ID and try again.'
                    : 'We could not save this record. Your entries are still here. Please try again.';
                $formData = $_POST;
                $editingId = (int)($_POST['record_id'] ?? 0);
            }
        }

        if (!$errorMessage && isset($_GET['edit'])) {
            $editingId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
            $record = $records->find($editingId);
            if (!$record) {
                $errorMessage = 'The record you selected no longer exists.';
                $editingId = 0;
            } else {
                $formData = [
                    'borehole_code' => $record['borehole_code'], 'borehole_depth_m' => $record['borehole_depth_m'],
                    'latitude' => $record['latitude'], 'longitude' => $record['longitude'], 'elevation_m' => $record['elevation_m'],
                    'record_lock_version' => $record['lock_version'] ?? 1,
                    'municipality_name' => $record['municipality_name'], 'barangay_name' => $record['barangay_name'],
                ];
                foreach (['soil_type','soil_classification','soil_description','depth_from_m','depth_to_m','spt_n_value','bearing_capacity_kpa'] as $field) {
                    $formData[$field] = array_column($record['layers'], $field);
                }
            }
        }

        $recordPagination = (new AdminDataService($db))->page('boreholes', $_GET);
        $recentBoreholes = $recordPagination['rows'];
    }
} catch (Throwable $e) {
    error_log('Soil records: ' . $e->getMessage());
    $databaseError = 'The database is unavailable. Please try again shortly.';
}

function old_value(string $key, string $default = ''): string
{
    global $formData;
    return htmlspecialchars((string) ($formData[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

function old_raw(string $key, string $default = ''): string
{
    global $formData;
    return (string) ($formData[$key] ?? $default);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b3d2e">
    <title>Soil Records | Southern Leyte SBCIS</title>
    <link rel="icon" type="image/png" href="../../src/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5"></script>
    <script src="../../src/js/toast.js"></script>
    <script src="../../src/js/loading-state.js?v=<?= filemtime(__DIR__ . '/../../src/js/loading-state.js') ?>"></script>
    <script src="../../src/js/admin_feedback.js" defer></script>
    <link rel="stylesheet" href="../../src/css/admin_dashb.css">
    <link rel="stylesheet" href="../../src/css/alerts.css?v=<?= filemtime(__DIR__ . '/../../src/css/alerts.css') ?>">
    <script src="../../src/js/modal-origin.js?v=<?= filemtime(__DIR__ . '/../../src/js/modal-origin.js') ?>"></script>
    <style>
        .data-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(320px, .7fr);
            gap: 18px;
            align-items: start;
        }

        .form-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field label {
            color: #35423d;
            font-size: 11px;
            font-weight: 700;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            min-height: 40px;
            border: 1px solid #d5dfda;
            border-radius: 8px;
            padding: 9px 10px;
            color: var(--text);
            background: #fbfcfb;
            font-size: 12px;
            outline: none;
        }

        .field select {
            appearance: auto;
            min-height: 43px;
        }

        .field textarea {
            min-height: 76px;
            resize: vertical;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: #8bbba5;
            box-shadow: 0 0 0 3px rgba(46, 139, 87, .08);
        }

        .full-span {
            grid-column: 1 / -1;
        }

        .section-title {
            margin: 22px 0 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .section-title h3 {
            font-size: 14px;
        }

        .layer-table input,
        .layer-table textarea {
            min-width: 120px;
            width: 100%;
            border: 1px solid #d5dfda;
            border-radius: 7px;
            padding: 8px;
            font-size: 11px;
            background: #fff;
        }

        .layer-table textarea {
            min-width: 180px;
            min-height: 66px;
            resize: vertical;
        }

        .icon-action {
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 8px;
            display: inline-grid;
            place-items: center;
            color: #fff;
            background: var(--green-900);
            cursor: pointer;
        }

        .icon-action.secondary {
            background: #edf6f1;
            color: var(--green-800);
            border: 1px solid #cfe1d7;
        }

        .icon-action.danger {
            background: #fff1f1;
            color: #8b3030;
            border: 1px solid #f1caca;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 18px;
        }

        .submit-button,
        .plain-button {
            min-height: 40px;
            border-radius: 8px;
            padding: 0 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .submit-button {
            border: 0;
            background: var(--green-900);
            color: #fff;
        }

        .plain-button {
            border: 1px solid var(--border);
            background: #fff;
            color: var(--green-800);
        }

        @media (max-width: 1080px) {
            .data-layout,
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
<link rel="stylesheet" href="../../src/css/admin_simple.css?v=<?= filemtime(__DIR__ . '/../../src/css/admin_simple.css') ?>">
<script src="../../src/js/admin_tables.js" defer></script>
</head>

<body>
    <?php $sidebarPage = 'soil_records.php'; require __DIR__ . '/sidebar.php'; ?>

    <div class="main">
        <header class="topbar">
            <div class="topbar-title">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Open menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-title-icon">
                    <i class="fa-solid fa-database"></i>
                </div>
                <div>
                    <h1>Soil Records</h1>
                    <p>Manage boreholes and soil layers</p>
                </div>
            </div>
            <div class="topbar-actions">
                <button type="button" class="submit-button" id="openRecord" <?= $databaseAvailable ? '' : 'disabled' ?>><i class="fa-solid fa-plus" aria-hidden="true"></i> Add borehole</button>
                <a href="admin_gis.php" class="public-site">
                    <i class="fa-solid fa-map-location-dot"></i>
                    <span>Map</span>
                </a>
            </div>
        </header>

        <main class="content">
            <?php if ($successMessage): ?><div hidden data-toast data-icon="success" data-title="Saved"><?= $escape($successMessage) ?></div><?php endif; ?>

            <?php if ($databaseError || !$databaseAvailable): ?>
                <div hidden data-toast data-icon="error" data-title="Database unavailable"><?= $escape($databaseError ?: 'Database connection is unavailable.') ?></div>
            <?php endif; ?>

            <div class="data-layout">
                <dialog class="entry-dialog" id="record-dialog" aria-labelledby="record-title" data-auto-open="<?= $errorMessage || isset($_GET['new']) || $editingId ? 'true' : 'false' ?>">
                <form class="form-card" method="POST" action="soil_records.php" id="record-form">
                    <input type="hidden" name="csrf" value="<?= $escape($_SESSION['record_csrf']) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="record_id" value="<?= $editingId ?: '' ?>">
                    <input type="hidden" name="record_lock_version" value="<?= $editingId ? $escape($formData['record_lock_version'] ?? 1) : '' ?>">
                    <div class="dialog-heading"><div><h2 id="record-title"><?= $editingId ? 'Edit soil record' : 'Add soil record' ?></h2><p><?= $editingId ? 'Update the borehole details and soil layers below.' : 'Enter the borehole location, then add its soil layers.' ?></p></div><button type="button" class="dialog-close" data-close-dialog aria-label="Close form">&times;</button></div>
                    <?php if ($errorMessage): ?><div hidden data-toast data-icon="error" data-title="Unable to save" id="record-error"><?= $escape($errorMessage) ?></div><?php endif; ?>
                    <p class="entry-help">Fields marked * are required. Closing this window keeps your draft until you leave the page.</p>
                    <div class="section-title"><h3>1. Borehole and location</h3></div>
                    <div class="form-grid">
                        <div class="field"><label for="borehole_code">Borehole ID *</label><input id="borehole_code" name="borehole_code" maxlength="50" value="<?= old_value('borehole_code') ?>" required placeholder="e.g. BH-001"></div>
                        <div class="field"><label for="borehole_depth_m">Borehole depth (m) *</label><input id="borehole_depth_m" name="borehole_depth_m" type="number" min="0.01" max="999999.99" step="0.01" value="<?= old_value('borehole_depth_m') ?>" required></div>
                        <div class="field"><label for="municipality_name">Municipality / city</label><select id="municipality_name" name="municipality_name"><option value="">Select municipality (optional)</option><?php foreach ($municipalityOptions as $municipalityName): ?><option value="<?= $escape($municipalityName) ?>" <?= old_raw('municipality_name') === $municipalityName ? 'selected' : '' ?>><?= $escape($municipalityName) ?></option><?php endforeach; ?></select></div>
                        <div class="field"><label for="barangay_name">Barangay</label><select id="barangay_name" name="barangay_name" disabled><option value="">Select a municipality first</option></select></div>
                        <div class="field"><label for="latitude">Latitude *</label><input id="latitude" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="<?= old_value('latitude') ?>" required placeholder="e.g. 10.1335"></div>
                        <div class="field"><label for="longitude">Longitude *</label><input id="longitude" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="<?= old_value('longitude') ?>" required placeholder="e.g. 124.8447"></div>
                        <div class="field"><label for="elevation_m">Elevation (m, optional)</label><input id="elevation_m" name="elevation_m" type="number" min="-999999.99" max="999999.99" step="0.01" value="<?= old_value('elevation_m') ?>"></div>
                    </div>
                    <div class="section-title"><h3>2. Soil layers</h3><button class="plain-button" type="button" id="addLayer">+ Add layer</button></div>
                    <p class="entry-help">Add layers from shallowest to deepest. Depth ranges must not overlap or exceed the borehole depth.</p>
                    <div id="layerEntries">
                    <?php $layerTotal = max(1, count(is_array($formData['soil_type'] ?? null) ? $formData['soil_type'] : [])); ?>
                    <?php for ($i = 0; $i < $layerTotal; $i++): ?>
                        <fieldset class="layer-entry"><legend>Layer <?= $i + 1 ?></legend><div class="form-grid">
                        <?php foreach (['soil_type' => ['Soil type *','text','',100], 'soil_classification' => ['Classification (optional)','text','',100], 'depth_from_m' => ['From depth (m) *','number','0',null], 'depth_to_m' => ['To depth (m) *','number','0.01',null], 'spt_n_value' => ['SPT N-value (optional)','number','0',null], 'bearing_capacity_kpa' => ['Bearing capacity (kPa, optional)','number','0',null]] as $name => [$label,$type,$min,$length]): ?>
                        <div class="field"><label><?= $label ?><input name="<?= $name ?>[]" type="<?= $type ?>" value="<?= $escape(is_scalar($formData[$name][$i] ?? '') ? ($formData[$name][$i] ?? '') : '') ?>" <?= in_array($name,['soil_type','depth_from_m','depth_to_m'],true) ? 'required' : '' ?> <?= $type === 'number' ? 'min="' . $min . '" step="' . ($name === 'spt_n_value' ? '1' : '0.01') . '"' : 'maxlength="' . $length . '"' ?>></label></div>
                        <?php endforeach; ?>
                        <div class="field full-span"><label>Soil description (optional)<textarea name="soil_description[]" maxlength="60000" rows="2"><?= $escape(is_scalar($formData['soil_description'][$i] ?? '') ? ($formData['soil_description'][$i] ?? '') : '') ?></textarea></label></div>
                        </div><button class="layer-remove" type="button">Remove layer</button></fieldset>
                    <?php endfor; ?>
                    </div>
                    <div class="form-actions"><button class="plain-button" type="button" data-close-dialog>Close</button><button class="submit-button" type="submit" <?= $databaseAvailable ? '' : 'disabled' ?>><?= $editingId ? 'Save changes' : 'Save record' ?></button></div>
                </form></dialog>
                <noscript><p class="system-message">Enable JavaScript to open the record entry form.</p></noscript>
                <section class="panel">
                    <div class="panel-header">
                        <h3>Boreholes</h3>
                        <span><?= number_format($recordPagination['total']) ?> total</span>
                    </div>

                    <form class="table-toolbar" method="GET" data-server-search role="search">
                        <input type="hidden" name="status" value="<?= $escape($recordPagination['status']) ?>">
                        <div class="table-search-control">
                            <label for="soil-record-search">Search</label>
                            <div class="table-search-input">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input id="soil-record-search" type="search" name="search" maxlength="100" value="<?= $escape($recordPagination['search']) ?>" placeholder="Borehole or location">
                            </div>
                        </div>
                        <div class="table-toolbar-actions">
                            <a class="table-clear-button table-archive-button" href="?status=<?= $recordPagination['status'] === 'archived' ? 'active' : 'archived' ?>"><?= $recordPagination['status'] === 'archived' ? 'Active boreholes' : 'Archived boreholes' ?></a>
                            <label class="table-page-size" for="soil-record-page-size">
                                <span>Rows</span>
                                <select id="soil-record-page-size" name="page_size" aria-label="Rows per page"><?php foreach ([10,25,50,100] as $size): ?><option value="<?= $size ?>" <?= $recordPagination['page_size'] === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select>
                            </label>
                            <?php if ($recordPagination['search'] !== ''): ?><a class="table-clear-button" href="?<?= $escape(http_build_query(['page_size' => $recordPagination['page_size']])) ?>">Clear</a><?php endif; ?>
                            <button class="table-search-button" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Search</span></button>
                        </div>
                    </form>

                    <?php if ($recentBoreholes): ?>
                        <div class="table-wrap" data-server-paginated>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Borehole</th>
                                        <th>Location</th>
                                        <th>Boundary</th>
                                        <th>Depth</th>
                                        <th>Layers</th>
                                        <th class="record-actions-heading">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentBoreholes as $borehole): ?>
                                        <tr>
                                            <td><strong><?= $escape($borehole['borehole_code']) ?></strong></td>
                                            <td><?= $escape(trim(($borehole['barangay_name'] ?: '') . ' ' . ($borehole['municipality_name'] ?: '')) ?: 'Not recorded') ?></td>
                                            <td><span class="badge<?= $borehole['boundary_status'] === 'outside' || $borehole['domain_warnings'] ? ' danger' : '' ?>"><?= $borehole['boundary_status'] === 'outside' ? 'Review: outside area' : ($borehole['domain_warnings'] ? 'Review: domain threshold' : 'Inside study area') ?></span></td>
                                            <td><?= $escape($borehole['borehole_depth_m']) ?> m</td>
                                            <td><span class="badge"><?= number_format((int) $borehole['layer_count']) ?></span></td>
                                            <td class="record-actions">
                                                <?php if ($recordPagination['status'] === 'active'): ?>
                                                <a class="table-action" href="?edit=<?= (int)$borehole['borehole_id'] ?>" aria-label="Edit <?= $escape($borehole['borehole_code']) ?>">Edit</a>
                                                <form method="POST" action="soil_records.php" class="archive-record-form" data-record-name="<?= $escape($borehole['borehole_code']) ?>">
                                                    <input type="hidden" name="csrf" value="<?= $escape($_SESSION['record_csrf']) ?>">
                                                    <input type="hidden" name="action" value="archive">
                                                    <input type="hidden" name="record_id" value="<?= (int)$borehole['borehole_id'] ?>">
                                                    <button class="table-action danger" type="submit">Archive</button>
                                                </form>
                                                <?php else: ?>
                                                <form method="POST" action="soil_records.php">
                                                    <input type="hidden" name="csrf" value="<?= $escape($_SESSION['record_csrf']) ?>">
                                                    <input type="hidden" name="action" value="restore">
                                                    <input type="hidden" name="record_id" value="<?= (int)$borehole['borehole_id'] ?>">
                                                    <button class="table-action" type="submit">Restore</button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($recordPagination['pages'] > 1): $baseQuery=['search'=>$recordPagination['search'],'page_size'=>$recordPagination['page_size'],'status'=>$recordPagination['status']]; ?>
                        <nav class="table-footer" aria-label="Record pages"><span>Page <?= number_format($recordPagination['page']) ?> of <?= number_format($recordPagination['pages']) ?></span><div class="table-pages">
                            <?php if ($recordPagination['page'] > 1): ?><a href="?<?= $escape(http_build_query($baseQuery + ['page'=>$recordPagination['page']-1])) ?>">Previous</a><?php endif; ?>
                            <?php if ($recordPagination['page'] < $recordPagination['pages']): ?><a href="?<?= $escape(http_build_query($baseQuery + ['page'=>$recordPagination['page']+1])) ?>">Next</a><?php endif; ?>
                        </div></nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="system-message"><?= $recordPagination['search'] !== '' ? 'No boreholes match your search.' : ($recordPagination['status'] === 'archived' ? 'No archived boreholes.' : 'No boreholes yet. Add one to get started.') ?></div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <script type="application/json" id="record-config"><?= json_encode(['barangays' => $barangayOptions, 'selectedBarangay' => old_raw('barangay_name')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
    <script src="../../src/js/soil_records.js" defer></script>
</body></html>
