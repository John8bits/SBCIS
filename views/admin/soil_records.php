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
$successMessage = null;
$errorMessage = null;
$recentBoreholes = [];
$municipalityOptions = [];
$barangayOptions = [];

$municipalityGeojson = json_decode(
    (string) file_get_contents(__DIR__ . '/../../src/qgis/southern_leyte_municipalities.geojson'),
    true
);

$barangayGeojson = json_decode(
    (string) file_get_contents(__DIR__ . '/../../src/qgis/southern_leyte_barangays.geojson'),
    true
);

foreach (($municipalityGeojson['features'] ?? []) as $feature) {
    $properties = $feature['properties'] ?? [];

    if (!empty($properties['GID_2']) && !empty($properties['NAME_2'])) {
        $municipalityOptions[$properties['GID_2']] = $properties['NAME_2'];
    }
}

foreach (($barangayGeojson['features'] ?? []) as $feature) {
    $properties = $feature['properties'] ?? [];

    if (
        !empty($properties['GID_2']) &&
        !empty($properties['GID_3']) &&
        !empty($properties['NAME_3'])
    ) {
        $barangayOptions[] = [
            'id' => $properties['GID_3'],
            'municipalityId' => $properties['GID_2'],
            'municipalityName' => $properties['NAME_2'] ?? '',
            'name' => $properties['NAME_3'],
        ];
    }
}

asort($municipalityOptions, SORT_NATURAL | SORT_FLAG_CASE);

usort($barangayOptions, static function (array $a, array $b) {
    return [$a['municipalityName'], $a['name']] <=> [$b['municipalityName'], $b['name']];
});

try {
    $db = sbcis_get_database();

    if ($db instanceof PDO) {
        $databaseAvailable = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                sbcis_create_geotechnical_record($db, $_POST);
                $successMessage = 'Soil information saved. It is now available on the admin and public maps.';
                $_POST = [];
            } catch (Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        }

        $recentBoreholes = sbcis_fetch_recent_boreholes($db, 20);
    }
} catch (Throwable $e) {
    $databaseError = $e->getMessage();
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
</head>

<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="../../src/images/logo.png" alt="Southern Leyte SBCIS Logo">
            <div class="brand-text">
                <strong>SOUTHERN LEYTE</strong>
                <span>SOIL INFORMATION SYSTEM</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">Main</div>
            <a href="admin_dashboard.php" class="nav-item">
                <i class="fa-solid fa-chart-line"></i>
                <span>Dashboard</span>
            </a>

            <div class="nav-section-title">Soil &amp; Location Data</div>
            <a href="soil_records.php" class="nav-item active">
                <i class="fa-solid fa-database"></i>
                <span>Soil Records</span>
            </a>
            <a href="boreholes.php" class="nav-item">
                <i class="fa-solid fa-location-dot"></i>
                <span>Boreholes</span>
            </a>
            <a href="soil_layers.php" class="nav-item">
                <i class="fa-solid fa-layer-group"></i>
                <span>Soil Layers</span>
            </a>

            <div class="nav-section-title">Locations</div>
            <a href="municipalities.php" class="nav-item">
                <i class="fa-solid fa-map-location-dot"></i>
                <span>Municipalities</span>
            </a>
            <a href="barangays.php" class="nav-item">
                <i class="fa-solid fa-location-crosshairs"></i>
                <span>Barangays</span>
            </a>

            <div class="nav-section-title">GIS</div>
            <a href="admin_gis.php" class="nav-item">
                <i class="fa-solid fa-map"></i>
                <span>GIS Map</span>
            </a>

            <div class="nav-section-title">Reports</div>
            <a href="soil_reports.php" class="nav-item">
                <i class="fa-solid fa-file-lines"></i>
                <span>Soil Reports</span>
            </a>
            <a href="bearing_capacity.php" class="nav-item">
                <i class="fa-solid fa-chart-column"></i>
                <span>Bearing Capacity</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../../app/Controllers/logout.php" class="logout-link" id="logoutButton">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

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
                <h2>New Borehole and Soil Information</h2>
                <p>Saved records become map markers for administrators and public users.</p>
            </div>

            <?php if ($successMessage): ?>
                <div class="system-message">
                    <strong>Saved.</strong><br>
                    <?= $escape($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage || $databaseError || !$databaseAvailable): ?>
                <div class="system-message error">
                    <strong>Unable to save data.</strong><br>
                    <?= $escape($errorMessage ?: ($databaseError ?: 'Database connection is unavailable.')) ?>
                </div>
            <?php endif; ?>

            <div class="data-layout">
                <form class="form-card" method="POST" action="soil_records.php">
                    <div id="borehole-data" class="section-title" style="margin-top:0">
                        <h3>Borehole / Location Data</h3>
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label for="borehole_code">Borehole ID</label>
                            <input id="borehole_code" name="borehole_code" value="<?= old_value('borehole_code') ?>" required>
                        </div>
                        <div class="field">
                            <label for="borehole_depth_m">Borehole Depth (m)</label>
                            <input id="borehole_depth_m" name="borehole_depth_m" type="number" min="0.01" step="0.01" value="<?= old_value('borehole_depth_m') ?>" required>
                        </div>
                        <div class="field">
                            <label for="latitude">Latitude</label>
                            <input id="latitude" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="<?= old_value('latitude') ?>" required>
                        </div>
                        <div class="field">
                            <label for="longitude">Longitude</label>
                            <input id="longitude" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="<?= old_value('longitude') ?>" required>
                        </div>
                        <div class="field">
                            <label for="elevation_m">Elevation (m)</label>
                            <input id="elevation_m" name="elevation_m" type="number" step="0.01" value="<?= old_value('elevation_m') ?>">
                        </div>
                        <div class="field">
                            <label for="municipality_name">Municipality / City</label>
                            <select id="municipality_name" name="municipality_name">
                                <option value="">All municipalities / cities</option>
                                <?php foreach ($municipalityOptions as $municipalityName): ?>
                                    <option
                                        value="<?= $escape($municipalityName) ?>"
                                        <?= old_raw('municipality_name') === $municipalityName ? 'selected' : '' ?>
                                    >
                                        <?= $escape($municipalityName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field full-span">
                            <label for="barangay_name">Barangay</label>
                            <select id="barangay_name" name="barangay_name" disabled>
                                <option value="">Select a municipality first</option>
                            </select>
                        </div>
                    </div>

                    <div id="soil-layers" class="section-title">
                        <h3>Soil / Geotechnical Data</h3>
                        <button class="icon-action secondary" type="button" id="addLayer" title="Add soil layer" aria-label="Add soil layer">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>

                    <div class="table-wrap">
                        <table class="layer-table">
                            <thead>
                                <tr>
                                    <th>Soil Type</th>
                                    <th>Classification</th>
                                    <th>Description</th>
                                    <th>From (m)</th>
                                    <th>To (m)</th>
                                    <th>SPT N</th>
                                    <th>Capacity (kPa)</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="layerRows">
                                <tr>
                                    <td><input name="soil_type[]" required></td>
                                    <td><input name="soil_classification[]"></td>
                                    <td><textarea name="soil_description[]"></textarea></td>
                                    <td><input name="depth_from_m[]" type="number" min="0" step="0.01" required></td>
                                    <td><input name="depth_to_m[]" type="number" min="0.01" step="0.01" required></td>
                                    <td><input name="spt_n_value[]" type="number" min="0" step="1"></td>
                                    <td><input name="bearing_capacity_kpa[]" type="number" min="0" step="0.01"></td>
                                    <td>
                                        <button class="icon-action danger remove-layer" type="button" title="Remove layer" aria-label="Remove layer">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-actions">
                        <button class="plain-button" type="reset">Clear</button>
                        <button class="submit-button" type="submit" <?= $databaseAvailable ? '' : 'disabled' ?>>
                            Save Soil Information
                        </button>
                    </div>
                </form>

                <section class="panel">
                    <div class="panel-header">
                        <h3>Recent Boreholes</h3>
                        <span><?= number_format(count($recentBoreholes)) ?> shown</span>
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

    <script>
        const locationBarangays =
            <?= json_encode($barangayOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]' ?>;

        const selectedMunicipality =
            <?= json_encode(old_raw('municipality_name'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        const selectedBarangay =
            <?= json_encode(old_raw('barangay_name'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        const sidebar = document.getElementById("sidebar");
        const mobileMenu = document.getElementById("mobileMenu");
        const layerRows = document.getElementById("layerRows");
        const addLayer = document.getElementById("addLayer");
        const logoutButton = document.getElementById("logoutButton");
        const municipalitySelect = document.getElementById("municipality_name");
        const barangaySelect = document.getElementById("barangay_name");

        if (mobileMenu && sidebar) {
            mobileMenu.addEventListener("click", function () {
                sidebar.classList.toggle("open");
            });
        }

        function setBarangayOptions(selectedValue = "") {
            const municipality = municipalitySelect.value;

            barangaySelect.replaceChildren(
                new Option(
                    municipality ? "All barangays" : "Select a municipality first",
                    ""
                )
            );

            barangaySelect.disabled = !municipality;

            if (!municipality) {
                return;
            }

            locationBarangays
                .filter(function (barangay) {
                    return barangay.municipalityName === municipality;
                })
                .sort(function (a, b) {
                    return a.name.localeCompare(b.name);
                })
                .forEach(function (barangay) {
                    barangaySelect.add(
                        new Option(barangay.name, barangay.name)
                    );
                });

            barangaySelect.value = selectedValue;
        }

        municipalitySelect.addEventListener("change", function () {
            setBarangayOptions("");
        });

        if (selectedMunicipality) {
            municipalitySelect.value = selectedMunicipality;
            setBarangayOptions(selectedBarangay);
        } else {
            setBarangayOptions("");
        }

        addLayer.addEventListener("click", function () {
            const row = layerRows.rows[0].cloneNode(true);
            row.querySelectorAll("input, textarea").forEach(function (field) {
                field.value = "";
            });
            layerRows.appendChild(row);
        });

        layerRows.addEventListener("click", function (event) {
            const button = event.target.closest(".remove-layer");

            if (!button) {
                return;
            }

            if (layerRows.rows.length === 1) {
                layerRows.rows[0].querySelectorAll("input, textarea").forEach(function (field) {
                    field.value = "";
                });
                return;
            }

            button.closest("tr").remove();
        });

        document.querySelector(".form-card").addEventListener("reset", function () {
            window.setTimeout(function () {
                setBarangayOptions("");
            }, 0);
        });

        if (logoutButton) {
            logoutButton.addEventListener("click", function (event) {
                event.preventDefault();

                Swal.fire({
                    icon: "question",
                    title: "Sign out?",
                    text: "You will be returned to the public site.",
                    showCancelButton: true,
                    confirmButtonText: "Sign out",
                    cancelButtonText: "Stay",
                    confirmButtonColor: "#0b3d2e"
                }).then(function (result) {
                    if (result.isConfirmed) {
                        window.location.href = logoutButton.href;
                    }
                });
            });
        }
    </script>
</body>

</html>
