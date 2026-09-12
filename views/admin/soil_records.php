<?php

session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: ../../index.php?login=required');
    exit;
}

require_once __DIR__ . '/../../app/Models/geotechnical_data.php';

$escape = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$databaseAvailable = false;
$databaseError = null;
$successMessage = $_SESSION['record_success'] ?? null;
unset($_SESSION['record_success']);
$_SESSION['record_csrf'] = $_SESSION['record_csrf'] ?? bin2hex(random_bytes(32));
$errorMessage = null;
$recentBoreholes = [];
$municipalityOptions = [];
$barangayOptions = [];

require_once __DIR__ . '/../../app/Models/locations.php';
try {
    $locationDirectory = sbcis_locations();
    $municipalityOptions = array_column($locationDirectory['municipalities'], 'name');
    $barangayOptions = array_map(static fn($row) => ['id' => $row['code'], 'name' => $row['name'], 'municipalityName' => $row['municipalityName']], $locationDirectory['barangays']);
} catch (Throwable $e) { error_log('Entry locations: ' . $e->getMessage()); }

try {
    $db = sbcis_get_database();

    if ($db instanceof PDO) {
        $databaseAvailable = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['record_csrf'], $_POST['csrf'])) {
                    throw new InvalidArgumentException('Your form session has expired. Please try saving again.');
                }
                $chosenMunicipality = trim((string)($_POST['municipality_name'] ?? ''));
                $chosenBarangay = trim((string)($_POST['barangay_name'] ?? ''));
                if ($chosenMunicipality !== '' && !in_array($chosenMunicipality, $municipalityOptions, true)) {
                    throw new InvalidArgumentException('Choose a municipality from the location list.');
                }
                if ($chosenBarangay !== '' && !array_filter($barangayOptions, static fn($row) => $row['name'] === $chosenBarangay && $row['municipalityName'] === $chosenMunicipality)) {
                    throw new InvalidArgumentException('Choose a barangay belonging to the selected municipality.');
                }
                sbcis_create_geotechnical_record($db, $_POST);
                $_SESSION['record_success'] = 'Your record has been saved and is now listed below.';
                header('Location: soil_records.php'); exit;
            } catch (InvalidArgumentException $e) {
                $errorMessage = $e->getMessage();
            } catch (Throwable $e) {
                error_log('Save soil record: ' . $e->getMessage());
                $errorMessage = $e instanceof PDOException && $e->getCode() === '23000'
                    ? 'This borehole ID already exists or a value conflicts with saved data. Check the ID and try again.'
                    : 'We could not save this record. Your entries are still here. Please try again.';
            }
        }

        $recentBoreholes = sbcis_fetch_recent_boreholes($db, null);
    }
} catch (Throwable $e) {
    error_log('Soil records: ' . $e->getMessage());
    $databaseError = 'The database is unavailable. Please try again shortly.';
}

function old_value(string $key, string $default = ''): string
{
    return htmlspecialchars((string) ($_POST[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

function old_raw(string $key, string $default = ''): string
{
    return (string) ($_POST[$key] ?? $default);
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../src/css/admin_dashb.css">
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
<link rel="stylesheet" href="../../src/css/admin_simple.css">
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
                    <p>Add borehole locations and geotechnical layers</p>
                </div>
            </div>
            <div class="topbar-actions">
                <a href="admin_gis.php" class="public-site">
                    <i class="fa-solid fa-map-location-dot"></i>
                    <span>View Admin Map</span>
                </a>
            </div>
        </header>

        <main class="content">
            <div class="page-heading">
                <div class="eyebrow">DATA ENTRY</div>
                <h2>Soil records</h2>
                <p>Browse saved records or add a new borehole and its soil layers.</p>
                <div class="ov-actions"><button type="button" class="submit-button" id="openRecord" <?= $databaseAvailable ? '' : 'disabled' ?>>+ Add record</button><a class="public-site" href="data_export.php">Export &amp; backup</a></div>
            </div>

            <?php if ($successMessage): ?>
                <div class="system-message">
                    <strong>Saved.</strong><br>
                    <?= $escape($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($databaseError || !$databaseAvailable): ?>
                <div class="system-message error">
                    <strong>Unable to save data.</strong><br>
                    <?= $escape($errorMessage ?: ($databaseError ?: 'Database connection is unavailable.')) ?>
                </div>
            <?php endif; ?>

            <div class="data-layout">
                <dialog class="entry-dialog" id="record-dialog" aria-labelledby="record-title" data-auto-open="<?= $errorMessage || isset($_GET['new']) ? 'true' : 'false' ?>">
                <form class="form-card" method="POST" action="soil_records.php" id="record-form">
                    <input type="hidden" name="csrf" value="<?= $escape($_SESSION['record_csrf']) ?>">
                    <div class="dialog-heading"><div><h2 id="record-title">Add soil record</h2><p>Enter the borehole location, then add its soil layers.</p></div><button type="button" class="dialog-close" data-close-dialog aria-label="Close form">&times;</button></div>
                    <?php if ($errorMessage): ?><div class="system-message error" role="alert" tabindex="-1" id="record-error"><?= $escape($errorMessage) ?></div><?php endif; ?>
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
                    <?php $layerTotal = max(1, count(is_array($_POST['soil_type'] ?? null) ? $_POST['soil_type'] : [])); ?>
                    <?php for ($i = 0; $i < $layerTotal; $i++): ?>
                        <fieldset class="layer-entry"><legend>Layer <?= $i + 1 ?></legend><div class="form-grid">
                        <?php foreach (['soil_type' => ['Soil type *','text','',100], 'soil_classification' => ['Classification (optional)','text','',100], 'depth_from_m' => ['From depth (m) *','number','0',null], 'depth_to_m' => ['To depth (m) *','number','0.01',null], 'spt_n_value' => ['SPT N-value (optional)','number','0',null], 'bearing_capacity_kpa' => ['Bearing capacity (kPa, optional)','number','0',null]] as $name => [$label,$type,$min,$length]): ?>
                        <div class="field"><label><?= $label ?><input name="<?= $name ?>[]" type="<?= $type ?>" value="<?= $escape(is_scalar($_POST[$name][$i] ?? '') ? ($_POST[$name][$i] ?? '') : '') ?>" <?= in_array($name,['soil_type','depth_from_m','depth_to_m'],true) ? 'required' : '' ?> <?= $type === 'number' ? 'min="' . $min . '" step="' . ($name === 'spt_n_value' ? '1' : '0.01') . '"' : 'maxlength="' . $length . '"' ?>></label></div>
                        <?php endforeach; ?>
                        <div class="field full-span"><label>Soil description (optional)<textarea name="soil_description[]" maxlength="60000" rows="2"><?= $escape(is_scalar($_POST['soil_description'][$i] ?? '') ? ($_POST['soil_description'][$i] ?? '') : '') ?></textarea></label></div>
                        </div><button class="layer-remove" type="button">Remove layer</button></fieldset>
                    <?php endfor; ?>
                    </div>
                    <div class="form-actions"><button class="plain-button" type="button" data-close-dialog>Close</button><button class="submit-button" type="submit" <?= $databaseAvailable ? '' : 'disabled' ?>>Save record</button></div>
                </form></dialog>
                <noscript><p class="system-message">Enable JavaScript to open the record entry form.</p></noscript>
                <section class="panel">
                    <div class="panel-header">
                        <h3>Saved boreholes</h3>
                        <span><?= number_format(count($recentBoreholes)) ?> records</span>
                    </div>

                    <?php if ($recentBoreholes): ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Borehole</th>
                                        <th>Location</th>
                                        <th>Depth</th>
                                        <th>Layers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentBoreholes as $borehole): ?>
                                        <tr>
                                            <td><strong><?= $escape($borehole['borehole_code']) ?></strong></td>
                                            <td><?= $escape(trim(($borehole['barangay_name'] ?: '') . ' ' . ($borehole['municipality_name'] ?: '')) ?: 'Not recorded') ?></td>
                                            <td><?= $escape($borehole['borehole_depth_m']) ?> m</td>
                                            <td><span class="badge"><?= number_format((int) $borehole['layer_count']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="system-message">No borehole records yet.</div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <script type="application/json" id="record-config"><?= json_encode(['barangays' => $barangayOptions, 'selectedBarangay' => old_raw('barangay_name')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
    <script src="../../src/js/soil_records.js" defer></script>
</body></html>
