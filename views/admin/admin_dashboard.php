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


$databaseFile = '../../config/config.php';

$databaseAvailable = false;
$db = null;
$mapRecords = [];
$databaseError = null;

if (file_exists($databaseFile)) {

    try {

        require_once $databaseFile;

        /*
         * Supports projects where the connection is stored in
         * $pdo or $conn.
         */

        if (isset($pdo) && $pdo instanceof PDO) {

            $db = $pdo;

        } elseif (isset($conn) && $conn instanceof PDO) {

            $db = $conn;

        }

        if ($db instanceof PDO) {

            $databaseAvailable = true;

            $db->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            $db->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );


            /*
            |--------------------------------------------------------------------------
            | GET BOREHOLE / SOIL DATA
            |--------------------------------------------------------------------------
            |
            | The view already combines:
            | - boreholes
            | - municipalities
            | - barangays
            | - soil_layers
            |
            */

            $stmt = $db->query("
                SELECT
                    borehole_id,
                    borehole_code,
                    municipality_name,
                    barangay_name,
                    latitude,
                    longitude,
                    elevation_m,
                    borehole_depth_m,
                    soil_layer_id,
                    layer_number,
                    soil_type,
                    soil_classification,
                    soil_description,
                    depth_from_m,
                    depth_to_m,
                    spt_n_value,
                    bearing_capacity_kpa
                FROM v_geotechnical_map_data
                WHERE latitude IS NOT NULL
                  AND longitude IS NOT NULL
                ORDER BY
                    borehole_code ASC,
                    layer_number ASC
            ");

            $mapRecords = $stmt->fetchAll();

        }

    } catch (Throwable $e) {

        $databaseError = $e->getMessage();

    }

}


/*
|--------------------------------------------------------------------------
| ESCAPE HELPER
|--------------------------------------------------------------------------
*/

$escape = static function ($value) {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

};


/*
|--------------------------------------------------------------------------
| GROUP SOIL RECORDS BY BOREHOLE
|--------------------------------------------------------------------------
*/

$boreholes = [];

foreach ($mapRecords as $record) {

    $boreholeId = $record['borehole_id'];

    if (!isset($boreholes[$boreholeId])) {

        $boreholes[$boreholeId] = [

            'borehole_id' =>
                $record['borehole_id'],

            'borehole_code' =>
                $record['borehole_code'],

            'municipality_name' =>
                $record['municipality_name'],

            'barangay_name' =>
                $record['barangay_name'],

            'latitude' =>
                (float) $record['latitude'],

            'longitude' =>
                (float) $record['longitude'],

            'elevation_m' =>
                $record['elevation_m'],

            'borehole_depth_m' =>
                $record['borehole_depth_m'],

            'layers' => []

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | ADD SOIL LAYER
    |--------------------------------------------------------------------------
    */

    if ($record['soil_layer_id'] !== null) {

        $boreholes[$boreholeId]['layers'][] = [

            'soil_layer_id' =>
                $record['soil_layer_id'],

            'layer_number' =>
                $record['layer_number'],

            'soil_type' =>
                $record['soil_type'],

            'soil_classification' =>
                $record['soil_classification'],

            'soil_description' =>
                $record['soil_description'],

            'depth_from_m' =>
                $record['depth_from_m'],

            'depth_to_m' =>
                $record['depth_to_m'],

            'spt_n_value' =>
                $record['spt_n_value'],

            'bearing_capacity_kpa' =>
                $record['bearing_capacity_kpa']

        ];

    }

}

$boreholes = array_values($boreholes);


/*
|--------------------------------------------------------------------------
| JSON DATA FOR LEAFLET
|--------------------------------------------------------------------------
*/

$mapJson = json_encode(
    $boreholes,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$boreholeCount = count($boreholes);

$layerCount = count($mapRecords);

$municipalityNames = [];

$barangayNames = [];

foreach ($boreholes as $borehole) {

    if (
        !empty($borehole['municipality_name'])
    ) {

        $municipalityNames[
            $borehole['municipality_name']
        ] = true;

    }

    if (
        !empty($borehole['barangay_name'])
    ) {

        $barangayNames[
            $borehole['barangay_name']
        ] = true;

    }

}

$municipalityCount =
    count($municipalityNames);

$barangayCount =
    count($barangayNames);

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#0b3d2e"
    >

    <title>
        Admin GIS Map | Southern Leyte SBCIS
    </title>


    <!-- Favicon -->

    <link
        rel="icon"
        type="image/png"
        href="../../src/images/logo.png"
    >


    <!-- Google Fonts -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- Leaflet -->

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >
    
    <!-- SweetAlert -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../src/css/admin_dashb.css">

</head>

<body>


<!--
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
-->

<aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">

        <img
            src="../../src/images/logo.png"
            alt="Southern Leyte SBCIS Logo"
        >

        <div class="brand-text">

            <strong>
                SOUTHERN LEYTE
            </strong>

            <span>
                SOIL INFORMATION SYSTEM
            </span>

        </div>

    </div>


    <nav class="sidebar-nav">

        <div class="nav-section-title">
            Main
        </div>


        <a
            href="admin_dashboard.php"
            class="nav-item active"
        >

            <i class="fa-solid fa-chart-line"></i>

            <span>
                Dashboard
            </span>

        </a>


        <div class="nav-section-title">
            Soil &amp; Location Data
        </div>


        <a
            href="soil_records.php"
            class="nav-item"
        >

            <i class="fa-solid fa-database"></i>

            <span>
                Soil Records
            </span>

        </a>


        <a
            href="boreholes.php"
            class="nav-item"
        >

            <i class="fa-solid fa-location-dot"></i>

            <span>
                Boreholes
            </span>

        </a>


        <a
            href="soil_layers.php"
            class="nav-item"
        >

            <i class="fa-solid fa-layer-group"></i>

            <span>
                Soil Layers
            </span>

        </a>


        <div class="nav-section-title">
            Locations
        </div>


        <a
            href="municipalities.php"
            class="nav-item"
        >

            <i class="fa-solid fa-map-location-dot"></i>

            <span>
                Municipalities
            </span>

        </a>


        <a
            href="barangays.php"
            class="nav-item"
        >

            <i class="fa-solid fa-location-crosshairs"></i>

            <span>
                Barangays
            </span>

        </a>


        <div class="nav-section-title">
            GIS
        </div>


        <!--
        IMPORTANT:
        This now stays inside the admin portal.
        -->

        <a
            href="admin_gis.php"
            class="nav-item"
        >

            <i class="fa-solid fa-map"></i>

            <span>
                GIS Map
            </span>

        </a>


        <div class="nav-section-title">
            Reports
        </div>


        <a
            href="soil_reports.php"
            class="nav-item"
        >

            <i class="fa-solid fa-file-lines"></i>

            <span>
                Soil Reports
            </span>

        </a>


        <a
            href="bearing_capacity.php"
            class="nav-item"
        >

            <i class="fa-solid fa-chart-column"></i>

            <span>
                Bearing Capacity
            </span>

        </a>

    </nav>


    <div class="sidebar-footer">

        <a
            href="../../app/Controllers/logout.php"
            class="logout-link"
            id="logoutButton"
        >

            <i class="fa-solid fa-right-from-bracket"></i>

            <span>
                Logout
            </span>

        </a>

    </div>

</aside>


<!--
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
-->

<div class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="topbar-title">

            <button
                class="mobile-menu"
                id="mobileMenu"
                type="button"
                aria-label="Open menu"
            >

                <i class="fa-solid fa-bars"></i>

            </button>


            <div class="topbar-title-icon">

                <i class="fa-solid fa-map-location-dot"></i>

            </div>


            <div>

                <h1>
                    GIS Map
                </h1>

                <p>
                    Soil investigation and borehole locations
                </p>

            </div>

        </div>


        <!-- <div class="topbar-actions">

            <a
                href="../../index.php"
                class="public-site"
            >

                <i class="fa-solid fa-arrow-up-right-from-square"></i>

                <span>
                    View Public Site
                </span>

            </a>

        </div> -->

    </header>


    <!-- CONTENT -->

    <main class="content">


        <div class="page-heading">

            <div class="eyebrow">
                ADMINISTRATOR GIS
            </div>

            <h2>
                Southern Leyte Geotechnical Map
            </h2>

            <p>
                View and inspect borehole locations and their
                associated soil layer records. Click a marker
                to view the available geotechnical information.
            </p>

        </div>


        <!-- STATISTICS -->

        <section class="stats">


            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-location-dot"></i>

                </div>

                <div>

                    <div class="stat-value">
                        <?= number_format($boreholeCount) ?>
                    </div>

                    <div class="stat-label">
                        Borehole Locations
                    </div>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-layer-group"></i>

                </div>

                <div>

                    <div class="stat-value">
                        <?= number_format($layerCount) ?>
                    </div>

                    <div class="stat-label">
                        Soil Layer Records
                    </div>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-city"></i>

                </div>

                <div>

                    <div class="stat-value">
                        <?= number_format($municipalityCount) ?>
                    </div>

                    <div class="stat-label">
                        Municipalities / Cities
                    </div>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-location-crosshairs"></i>

                </div>

                <div>

                    <div class="stat-value">
                        <?= number_format($barangayCount) ?>
                    </div>

                    <div class="stat-label">
                        Barangays with Boreholes
                    </div>

                </div>

            </div>


        </section>


        <!--
        |--------------------------------------------------------------------------
        | MAP TOOLBAR
        |--------------------------------------------------------------------------
        -->

        <!-- <section class="gis-toolbar">


            <div class="toolbar-left">


                <div class="search-box">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="search"
                        id="mapSearch"
                        placeholder="Search borehole, municipality, or barangay..."
                        autocomplete="off"
                    >

                </div>
                    

                <select
                    class="filter-select"
                    id="capacityFilter"
                >

                    <option value="all">
                        All bearing capacities
                    </option>

                    <option value="strong">
                        &gt; 300 kPa
                    </option>

                    <option value="good">
                        200–300 kPa
                    </option>

                    <option value="moderate">
                        100–200 kPa
                    </option>

                    <option value="low">
                        50–100 kPa
                    </option>

                    <option value="very-low">
                        &lt; 50 kPa
                    </option>

                    <option value="none">
                        No bearing capacity
                    </option>

                </select>


            </div>


            <button
                type="button"
                class="map-button"
                id="resetMap"
            >

                <i class="fa-solid fa-arrows-to-circle"></i>

                Reset Map

            </button>


        </section> -->


        <!--
        |--------------------------------------------------------------------------
        | MAP
        |--------------------------------------------------------------------------
        -->

        <!-- <section class="map-card">

            <div
                id="adminMap"
                aria-label="Southern Leyte administrative GIS map"
            ></div>

            <div class="map-legend">

                <div class="legend-title">
                    Bearing Capacity
                </div>


                <div class="legend-row">

                    <span class="legend-dot legend-strong"></span>

                    <span>
                        &gt; 300 kPa
                    </span>

                </div>


                <div class="legend-row">

                    <span class="legend-dot legend-good"></span>

                    <span>
                        200–300 kPa
                    </span>

                </div>


                <div class="legend-row">

                    <span class="legend-dot legend-moderate"></span>

                    <span>
                        100–200 kPa
                    </span>

                </div>


                <div class="legend-row">

                    <span class="legend-dot legend-low"></span>

                    <span>
                        50–100 kPa
                    </span>

                </div>


                <div class="legend-row">

                    <span class="legend-dot legend-very-low"></span>

                    <span>
                        &lt; 50 kPa
                    </span>

                </div>


                <div class="legend-row">

                    <span class="legend-dot legend-no-data"></span>

                    <span>
                        No data
                    </span>

                </div>

            </div>

        </section> -->


        <!--
        |--------------------------------------------------------------------------
        | INFORMATION PANELS
        |--------------------------------------------------------------------------
        -->

        <section class="below-map">


            <!-- RECENT BOREHOLES -->

            <div class="panel">

                <div class="panel-header">

                    <h3>
                        Borehole Records
                    </h3>

                    <span>
                        <?= number_format($boreholeCount) ?>
                        locations
                    </span>

                </div>


                <?php if ($boreholes): ?>

                    <div class="table-wrap">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Borehole
                                    </th>

                                    <th>
                                        Municipality
                                    </th>

                                    <th>
                                        Barangay
                                    </th>

                                    <th>
                                        Depth
                                    </th>

                                    <th>
                                        Layers
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                            <?php foreach (
                                array_slice($boreholes, 0, 8)
                                as $borehole
                            ): ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?= $escape(
                                                $borehole['borehole_code']
                                            ) ?>
                                        </strong>

                                    </td>


                                    <td>

                                        <?= $escape(
                                            $borehole['municipality_name']
                                            ?: 'Not recorded'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= $escape(
                                            $borehole['barangay_name']
                                            ?: 'Not recorded'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= $borehole['borehole_depth_m'] !== null
                                            ? $escape(
                                                $borehole['borehole_depth_m']
                                            ) . ' m'
                                            : '—'
                                        ?>

                                    </td>


                                    <td>

                                        <span class="badge">

                                            <?= count(
                                                $borehole['layers']
                                            ) ?>

                                            layers

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="system-message">

                        No borehole records are currently
                        available for the GIS map.

                    </div>

                <?php endif; ?>

            </div>


            <!-- MAP INFORMATION -->

            <div class="panel">

                <div class="panel-header">

                    <h3>
                        Map Information
                    </h3>

                    <span>
                        SBCIS
                    </span>

                </div>


                <div class="info-item">

                    <span>
                        Province
                    </span>

                    <strong>
                        Southern Leyte
                    </strong>

                </div>


                <div class="info-item">

                    <span>
                        Borehole locations
                    </span>

                    <strong>
                        <?= number_format($boreholeCount) ?>
                    </strong>

                </div>


                <div class="info-item">

                    <span>
                        Soil layer records
                    </span>

                    <strong>
                        <?= number_format($layerCount) ?>
                    </strong>

                </div>


                <div class="info-item">

                    <span>
                        Municipalities represented
                    </span>

                    <strong>
                        <?= number_format($municipalityCount) ?>
                    </strong>

                </div>


                <div class="info-item">

                    <span>
                        Barangays represented
                    </span>

                    <strong>
                        <?= number_format($barangayCount) ?>
                    </strong>

                </div>


                <?php if (!$databaseAvailable): ?>

                    <div class="system-message error">

                        <strong>
                            Database unavailable.
                        </strong>

                        <br>

                        The map interface is loaded, but
                        database records could not be retrieved.

                    </div>

                <?php endif; ?>

            </div>


        </section>


    </main>

</div>


<!--
|--------------------------------------------------------------------------
| LEAFLET
|--------------------------------------------------------------------------
-->

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>


<script>

/*
|--------------------------------------------------------------------------
| MAP DATA FROM PHP
|--------------------------------------------------------------------------
*/

const boreholes =
    <?= $mapJson ?: '[]' ?>;

const dashboardMapElement =
    document.getElementById("adminMap");

if (dashboardMapElement) {


/*
|--------------------------------------------------------------------------
| SOUTHERN LEYTE DEFAULT VIEW
|--------------------------------------------------------------------------
*/

const southernLeyteCenter =
    [10.15, 125.10];

const defaultZoom =
    10;


/*
|--------------------------------------------------------------------------
| INITIALIZE MAP
|--------------------------------------------------------------------------
*/

const map =
    L.map("adminMap", {

        zoomControl: true,

        attributionControl: true

    }).setView(
        southernLeyteCenter,
        defaultZoom
    );


/*
|--------------------------------------------------------------------------
| OPENSTREETMAP
|--------------------------------------------------------------------------
*/

L.tileLayer(
    "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
    {
        maxZoom: 19,

        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }
).addTo(map);


/*
|--------------------------------------------------------------------------
| MARKER LAYER
|--------------------------------------------------------------------------
*/

const markerLayer =
    L.layerGroup().addTo(map);


/*
|--------------------------------------------------------------------------
| CURRENT MARKERS
|--------------------------------------------------------------------------
*/

const markerReferences = [];


/*
|--------------------------------------------------------------------------
| CAPACITY COLOR
|--------------------------------------------------------------------------
*/

function getCapacityClass(value) {

    if (
        value === null ||
        value === undefined ||
        value === ""
    ) {

        return "none";

    }


    const capacity =
        Number(value);


    if (capacity > 300) {

        return "strong";

    }

    if (capacity >= 200) {

        return "good";

    }

    if (capacity >= 100) {

        return "moderate";

    }

    if (capacity >= 50) {

        return "low";

    }

    return "very-low";

}


/*
|--------------------------------------------------------------------------
| CAPACITY COLOR FOR LEAFLET
|--------------------------------------------------------------------------
*/

function getCapacityColor(value) {

    const category =
        getCapacityClass(value);


    const colors = {

        strong: "#166534",

        good: "#2e8b57",

        moderate: "#a66a3f",

        low: "#b45309",

        "very-low": "#991b1b",

        none: "#64748b"

    };


    return colors[category];

}


/*
|--------------------------------------------------------------------------
| FORMAT CAPACITY
|--------------------------------------------------------------------------
*/

function formatCapacity(value) {

    if (
        value === null ||
        value === undefined ||
        value === ""
    ) {

        return "Not recorded";

    }


    return Number(value).toLocaleString(
        undefined,
        {
            maximumFractionDigits: 2
        }
    ) + " kPa";

}


/*
|--------------------------------------------------------------------------
| CREATE POPUP
|--------------------------------------------------------------------------
*/

function createPopup(borehole) {

    const municipality =
        borehole.municipality_name
        || "Not recorded";


    const barangay =
        borehole.barangay_name
        || "Not recorded";


    let highestCapacity =
        null;


    borehole.layers.forEach(layer => {

        if (
            layer.bearing_capacity_kpa !== null &&
            layer.bearing_capacity_kpa !== ""
        ) {

            const value =
                Number(
                    layer.bearing_capacity_kpa
                );


            if (
                highestCapacity === null ||
                value > highestCapacity
            ) {

                highestCapacity =
                    value;

            }

        }

    });


    let layersHtml = "";


    if (
        borehole.layers &&
        borehole.layers.length
    ) {

        layersHtml = `

            <div class="popup-layer-title">
                Soil Layers
            </div>

        `;


        borehole.layers.forEach(layer => {

            const capacity =
                formatCapacity(
                    layer.bearing_capacity_kpa
                );


            const spt =
                layer.spt_n_value !== null
                    ? layer.spt_n_value
                    : "Not recorded";


            layersHtml += `

                <div class="popup-layer">

                    <div class="popup-layer-head">

                        <strong>
                            Layer ${escapeHtml(
                                layer.layer_number
                            )}
                        </strong>

                        <span class="capacity">
                            ${escapeHtml(
                                capacity
                            )}
                        </span>

                    </div>

                    <div>
                        ${escapeHtml(
                            layer.soil_type
                            || "Not recorded"
                        )}
                    </div>

                    <div>
                        Depth:
                        ${escapeHtml(
                            layer.depth_from_m
                            ?? "—"
                        )}
                        –
                        ${escapeHtml(
                            layer.depth_to_m
                            ?? "—"
                        )}
                        m
                    </div>

                    <div>
                        SPT N-value:
                        ${escapeHtml(
                            spt
                        )}
                    </div>

                </div>

            `;

        });

    } else {

        layersHtml = `

            <div class="popup-layer-title">
                Soil Layers
            </div>

            <div class="popup-layer">
                No soil layer records.
            </div>

        `;

    }


    return `

        <div class="popup">

            <div class="popup-title">

                ${escapeHtml(
                    borehole.borehole_code
                )}

            </div>


            <div class="popup-location">

                ${escapeHtml(
                    barangay
                )},

                ${escapeHtml(
                    municipality
                )}

            </div>


            <div class="popup-grid">

                <div class="popup-item">

                    <span>
                        Borehole Depth
                    </span>

                    <strong>
                        ${
                            borehole.borehole_depth_m
                            !== null
                            ? escapeHtml(
                                borehole.borehole_depth_m
                            ) + " m"
                            : "—"
                        }
                    </strong>

                </div>


                <div class="popup-item">

                    <span>
                        Elevation
                    </span>

                    <strong>
                        ${
                            borehole.elevation_m
                            !== null
                            ? escapeHtml(
                                borehole.elevation_m
                            ) + " m"
                            : "—"
                        }
                    </strong>

                </div>


                <div class="popup-item">

                    <span>
                        Latitude
                    </span>

                    <strong>
                        ${escapeHtml(
                            borehole.latitude
                        )}
                    </strong>

                </div>


                <div class="popup-item">

                    <span>
                        Longitude
                    </span>

                    <strong>
                        ${escapeHtml(
                            borehole.longitude
                        )}
                    </strong>

                </div>


            </div>


            ${layersHtml}

        </div>

    `;

}


/*
|--------------------------------------------------------------------------
| HTML ESCAPE
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    if (
        value === null ||
        value === undefined
    ) {

        return "";

    }


    return String(value)

        .replaceAll("&", "&amp;")

        .replaceAll("<", "&lt;")

        .replaceAll(">", "&gt;")

        .replaceAll('"', "&quot;")

        .replaceAll("'", "&#039;");

}


/*
|--------------------------------------------------------------------------
| CREATE MARKER
|--------------------------------------------------------------------------
*/

function createMarker(borehole) {

    let capacity = null;


    /*
     * Find the strongest recorded bearing
     * capacity for marker color.
     */

    borehole.layers.forEach(layer => {

        if (
            layer.bearing_capacity_kpa !== null &&
            layer.bearing_capacity_kpa !== ""
        ) {

            const value =
                Number(
                    layer.bearing_capacity_kpa
                );


            if (
                capacity === null ||
                value > capacity
            ) {

                capacity = value;

            }

        }

    });


    const color =
        getCapacityColor(capacity);


    const marker =
        L.circleMarker(
            [
                Number(
                    borehole.latitude
                ),

                Number(
                    borehole.longitude
                )
            ],
            {

                radius: 8,

                fillColor:
                    color,

                color:
                    "#ffffff",

                weight: 2,

                opacity: 1,

                fillOpacity: 0.9

            }
        );


    marker.bindPopup(
        createPopup(borehole),
        {
            maxWidth: 360
        }
    );


    marker.__borehole =
        borehole;


    marker.__capacity =
        capacity;


    markerLayer.addLayer(marker);


    markerReferences.push(marker);

}


/*
|--------------------------------------------------------------------------
| LOAD MARKERS
|--------------------------------------------------------------------------
*/

boreholes.forEach(
    createMarker
);


/*
|--------------------------------------------------------------------------
| FIT MAP TO DATA
|--------------------------------------------------------------------------
*/

function fitToBoreholes() {

    if (!boreholes.length) {

        map.setView(
            southernLeyteCenter,
            defaultZoom
        );

        return;

    }


    const bounds =
        L.latLngBounds([]);


    boreholes.forEach(
        borehole => {

            bounds.extend(
                [
                    Number(
                        borehole.latitude
                    ),

                    Number(
                        borehole.longitude
                    )
                ]
            );

        }
    );


    if (bounds.isValid()) {

        map.fitBounds(
            bounds,
            {
                padding: [40, 40],

                maxZoom: 13
            }
        );

    }

}


fitToBoreholes();


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

const searchInput =
    document.getElementById(
        "mapSearch"
    );


const capacityFilter =
    document.getElementById(
        "capacityFilter"
    );


function applyFilters() {

    const search =
        searchInput.value
            .trim()
            .toLowerCase();


    const selectedCapacity =
        capacityFilter.value;


    markerReferences.forEach(
        marker => {

            const borehole =
                marker.__borehole;


            const municipality =
                (
                    borehole.municipality_name
                    || ""
                ).toLowerCase();


            const barangay =
                (
                    borehole.barangay_name
                    || ""
                ).toLowerCase();


            const code =
                (
                    borehole.borehole_code
                    || ""
                ).toLowerCase();


            const searchMatch =
                !search ||
                code.includes(search) ||
                municipality.includes(search) ||
                barangay.includes(search);


            const capacityMatch =
                selectedCapacity === "all"
                ||
                getCapacityClass(
                    marker.__capacity
                ) === selectedCapacity;


            if (
                searchMatch &&
                capacityMatch
            ) {

                if (
                    !map.hasLayer(marker)
                ) {

                    markerLayer.addLayer(
                        marker
                    );

                }

            } else {

                if (
                    map.hasLayer(marker)
                ) {

                    markerLayer.removeLayer(
                        marker
                    );

                }

            }

        }
    );

}


searchInput.addEventListener(
    "input",
    applyFilters
);


capacityFilter.addEventListener(
    "change",
    applyFilters
);


/*
|--------------------------------------------------------------------------
| RESET MAP
|--------------------------------------------------------------------------
*/

document
    .getElementById("resetMap")
    .addEventListener(
        "click",
        function () {

            searchInput.value =
                "";

            capacityFilter.value =
                "all";


            markerReferences.forEach(
                marker => {

                    if (
                        !map.hasLayer(marker)
                    ) {

                        markerLayer.addLayer(
                            marker
                        );

                    }

                }
            );


            fitToBoreholes();

        }
    );

}


/*
|--------------------------------------------------------------------------
| MOBILE SIDEBAR
|--------------------------------------------------------------------------
*/

const mobileMenu =
    document.getElementById(
        "mobileMenu"
    );


const sidebar =
    document.getElementById(
        "sidebar"
    );


mobileMenu.addEventListener(
    "click",
    function () {

        sidebar.classList.toggle(
            "open"
        );

    }
);


/*
|--------------------------------------------------------------------------
| CLOSE SIDEBAR WHEN NAV ITEM IS CLICKED
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(".nav-item")
    .forEach(
        item => {

            item.addEventListener(
                "click",
                function () {

                    if (
                        window.innerWidth <= 800
                    ) {

                        sidebar.classList.remove(
                            "open"
                        );

                    }

                }
            );

        }
    );


/*
|--------------------------------------------------------------------------
| LOGOUT CONFIRMATION
|--------------------------------------------------------------------------
*/

const logoutButton =
    document.getElementById(
        "logoutButton"
    );


if (logoutButton) {

    logoutButton.addEventListener(
        "click",
        function (event) {

            event.preventDefault();


            Swal.fire({

                toast: false,

                position: "center",

                icon: "question",

                title: "Sign out?",

                text:
                    "You will be returned to the public page.",

                showConfirmButton: true,

                showCancelButton: true,

                confirmButtonText:
                    "Sign out",

                cancelButtonText:
                    "Stay",

                confirmButtonColor:
                    "#0b3d2e"

            }).then(
                result => {

                    if (
                        result.isConfirmed
                    ) {

                        window.location.href =
                            "../../app/Controllers/logout.php";

                    }

                }
            );

        }
    );

}

window.addEventListener(
    "pageshow",
    function (event) {

        if (event.persisted) {
            window.location.reload();
        }

    }
);


/*
|--------------------------------------------------------------------------
| FIX LEAFLET SIZE AFTER PAGE LOAD
|--------------------------------------------------------------------------
*/

setTimeout(
    function () {

        map.invalidateSize();

    },
    250
);

</script>


</body>

</html>
