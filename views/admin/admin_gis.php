<?php

/*
|--------------------------------------------------------------------------
| ADMIN SESSION SECURITY
|--------------------------------------------------------------------------
*/

session_start();

/*
 * Prevent the browser from showing a cached admin page
 * after logout or when navigating backward.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');


/*
|--------------------------------------------------------------------------
| REQUIRE ADMIN LOGIN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: ../../index.php?login=required');
    exit;
}

require_once __DIR__ . '/../../app/Models/geotechnical_data.php';

$boreholes = [];
$databaseError = null;

try {
    $db = sbcis_get_database();

    if ($db instanceof PDO) {
        $boreholes = sbcis_fetch_map_boreholes($db);
    }
} catch (Throwable $e) {
    $databaseError = $e->getMessage();
}

$mapJson = json_encode(
    $boreholes,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="theme-color" content="#0b3d2e">

    <title>
        GIS Map | Southern Leyte SBCIS
    </title>


    <!-- Favicon -->

    <link rel="icon" type="image/png" href="../../src/images/logo.png">


    <!-- Google Fonts -->

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">


    <!-- Font Awesome -->

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <!-- Leaflet -->

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">


    <style>
        /*
        |--------------------------------------------------------------------------
        | ROOT
        |--------------------------------------------------------------------------
        */

        :root {

            --green-950: #06291f;
            --green-900: #0b3d2e;
            --green-800: #10553e;
            --green-700: #176b4d;
            --green-600: #2e8b57;

            --blue-600: #176b87;

            --bg: #f4f7f5;
            --card: #ffffff;

            --border: #e3e9e5;

            --text: #1c2924;
            --muted: #71807a;

            --sidebar-width: 260px;

        }


        /*
        |--------------------------------------------------------------------------
        | RESET
        |--------------------------------------------------------------------------
        */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        html,
        body {

            width: 100%;
            min-height: 100%;

        }


        body {

            font-family:
                "Inter",
                Arial,
                sans-serif;

            background:
                var(--bg);

            color:
                var(--text);

            overflow-x: hidden;

        }


        a {

            color: inherit;
            text-decoration: none;

        }


        button,
        input,
        select {

            font: inherit;

        }


        /*
        |--------------------------------------------------------------------------
        | SIDEBAR
        |--------------------------------------------------------------------------
        */

        .sidebar {

            position: fixed;

            top: 0;
            left: 0;
            bottom: 0;

            width:
                var(--sidebar-width);

            background:
                var(--green-900);

            color:
                #ffffff;

            z-index: 1000;

            display: flex;

            flex-direction: column;

            box-shadow:
                8px 0 30px rgba(0, 0, 0, 0.08);

        }


        /*
        |--------------------------------------------------------------------------
        | SIDEBAR BRAND
        |--------------------------------------------------------------------------
        */

        .sidebar-brand {

            height: 82px;

            padding:
                16px 20px;

            display: flex;

            align-items: center;

            gap: 12px;

            border-bottom:
                1px solid rgba(255, 255, 255, 0.09);

        }


        .sidebar-brand img {

            width: 44px;
            height: 44px;

            object-fit: contain;

            background:
                #ffffff;

            border-radius: 10px;

            padding: 4px;

        }


        .brand-text {

            display: flex;

            flex-direction: column;

            line-height: 1.1;

        }


        .brand-text strong {

            font-size: 13px;

            letter-spacing:
                0.5px;

        }


        .brand-text span {

            margin-top: 4px;

            font-size: 9px;

            color:
                rgba(255, 255, 255, 0.65);

            letter-spacing:
                0.7px;

        }


        /*
        |--------------------------------------------------------------------------
        | SIDEBAR NAVIGATION
        |--------------------------------------------------------------------------
        */

        .sidebar-nav {

            flex: 1;

            padding:
                20px 12px;

            overflow-y: auto;

        }


        .nav-section-title {

            padding:
                0 12px;

            margin:
                14px 0 8px;

            font-size: 9px;

            font-weight: 700;

            letter-spacing:
                1.4px;

            text-transform:
                uppercase;

            color:
                rgba(255, 255, 255, 0.42);

        }


        .nav-item {

            position: relative;

            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                11px 13px;

            margin-bottom: 4px;

            border-radius: 9px;

            color:
                rgba(255, 255, 255, 0.72);

            font-size: 13px;

            transition:
                background .2s ease,
                color .2s ease,
                transform .2s ease;

        }


        .nav-item i {

            width: 18px;

            text-align: center;

            font-size: 14px;

        }


        .nav-item:hover {

            background:
                rgba(255, 255, 255, 0.08);

            color:
                #ffffff;

            transform:
                translateX(2px);

        }


        .nav-item.active {

            background:
                rgba(255, 255, 255, 0.13);

            color:
                #ffffff;

        }


        .nav-item.active::before {

            content: "";

            position: absolute;

            left: 0;

            top: 8px;
            bottom: 8px;

            width: 3px;

            border-radius:
                0 4px 4px 0;

            background:
                #72c79c;

        }


        /*
        |--------------------------------------------------------------------------
        | SIDEBAR FOOTER
        |--------------------------------------------------------------------------
        */

        .sidebar-footer {

            padding: 14px;

            border-top:
                1px solid rgba(255, 255, 255, 0.09);

        }


        .logout-link {

            display: flex;

            align-items: center;

            gap: 10px;

            padding:
                11px 13px;

            border-radius: 9px;

            color:
                rgba(255, 255, 255, 0.72);

            font-size: 13px;

        }


        .logout-link:hover {

            background:
                rgba(255, 255, 255, 0.08);

            color:
                #ffffff;

        }


        /*
        |--------------------------------------------------------------------------
        | MAIN
        |--------------------------------------------------------------------------
        */

        .main {

            margin-left:
                var(--sidebar-width);

            min-height: 100vh;

        }


        /*
        |--------------------------------------------------------------------------
        | TOPBAR
        |--------------------------------------------------------------------------
        */

        .topbar {

            height: 72px;

            padding:
                0 28px;

            background:
                #ffffff;

            border-bottom:
                1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            position: sticky;

            top: 0;

            z-index: 900;

        }


        .topbar-title {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .topbar-title-icon {

            width: 38px;
            height: 38px;

            border-radius: 10px;

            display: grid;

            place-items: center;

            background:
                #e8f3ee;

            color:
                var(--green-700);

        }


        .topbar-title h1 {

            font-size: 18px;

            font-weight: 700;

        }


        .topbar-title p {

            margin-top: 2px;

            font-size: 11px;

            color:
                var(--muted);

        }


        .topbar-actions {

            display: flex;

            align-items: center;

            gap: 10px;

        }


        /*
        |--------------------------------------------------------------------------
        | CONTENT
        |--------------------------------------------------------------------------
        */

        .content {

            padding: 18px;

            height:
                calc(100vh - 72px);

        }


        /*
        |--------------------------------------------------------------------------
        | MAP WRAPPER
        |--------------------------------------------------------------------------
        */

        .map-card {

            position: relative;

            width: 100%;

            height: 100%;

            min-height: 520px;

            overflow: hidden;

            border:
                1px solid var(--border);

            border-radius: 14px;

            background:
                #dfe8e1;

            box-shadow:
                0 12px 35px rgba(11, 61, 46, 0.08);

        }


        /*
        |--------------------------------------------------------------------------
        | LEAFLET MAP
        |--------------------------------------------------------------------------
        */

        #adminGISMap {

            width: 100%;

            height: 100%;

            min-height: 520px;

        }


        /*
        |--------------------------------------------------------------------------
        | MAP STATUS
        |--------------------------------------------------------------------------
        */

        .map-status {

            position: absolute;

            left: 18px;

            bottom: 18px;

            z-index: 500;

            padding:
                9px 13px;

            border-radius: 8px;

            background:
                rgba(255, 255, 255, 0.94);

            border:
                1px solid rgba(0, 0, 0, 0.08);

            box-shadow:
                0 5px 18px rgba(0, 0, 0, 0.12);

            color:
                var(--text);

            font-size: 11px;

            font-weight: 600;

        }


        /*
        |--------------------------------------------------------------------------
        | MAP ERROR
        |--------------------------------------------------------------------------
        */

        .map-error {

            position: absolute;

            inset: 0;

            z-index: 1000;

            display: none;

            align-items: center;

            justify-content: center;

            padding: 30px;

            text-align: center;

            background:
                rgba(244, 247, 245, 0.96);

        }


        .map-error.show {

            display: flex;

        }


        .map-error-box {

            max-width: 430px;

            padding: 28px;

            border:
                1px solid var(--border);

            border-radius: 14px;

            background:
                #ffffff;

            box-shadow:
                0 15px 40px rgba(11, 61, 46, 0.10);

        }


        .map-error-box i {

            font-size: 32px;

            color:
                #b94a48;

            margin-bottom: 12px;

        }


        .map-error-box h2 {

            font-size: 18px;

            margin-bottom: 7px;

        }


        .map-error-box p {

            color:
                var(--muted);

            font-size: 12px;

            line-height: 1.6;

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE MENU
        |--------------------------------------------------------------------------
        */

        .mobile-menu {

            display: none;

            width: 36px;
            height: 36px;

            border: 0;

            border-radius: 8px;

            background:
                #e8f3ee;

            color:
                var(--green-900);

            place-items: center;

            cursor: pointer;

            margin-right: 8px;

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .sidebar {

                transform:
                    translateX(-100%);

                transition:
                    transform .25s ease;

            }


            .sidebar.open {

                transform:
                    translateX(0);

            }


            .main {

                margin-left: 0;

            }


            .mobile-menu {

                display: grid;

            }

        }


        @media (max-width: 600px) {

            .topbar {

                padding:
                    0 16px;

            }


            .topbar-actions {

                display: none;

            }


            .content {

                padding: 10px;

                height:
                    calc(100vh - 72px);

            }


            .map-card {

                min-height: 450px;

                border-radius: 10px;

            }


            #adminGISMap {

                min-height: 450px;

            }

        }
    </style>

</head>


<body>


    <!--
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
-->

    <aside class="sidebar" id="sidebar">

        <!-- <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Collapse sidebar"
            title="Collapse sidebar">
            <i class="fa-solid fa-chevron-left"></i>
        </button> -->

        <div class="sidebar-brand">

            <img src="../../src/images/logo.png" alt="Southern Leyte SBCIS Logo">

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


            <a href="admin_dashboard.php" class="nav-item">

                <i class="fa-solid fa-chart-line"></i>

                <span>
                    Dashboard
                </span>

            </a>


            <div class="nav-section-title">
                Soil &amp; Location Data
            </div>


            <a href="soil_records.php" class="nav-item" data-page="soil_records.php">
                <i class="fa-solid fa-database"></i>
                <span>Soil Records</span>
            </a>

            <a href="boreholes.php" class="nav-item" data-page="boreholes.php">
                <i class="fa-solid fa-location-dot"></i>
                <span>Boreholes</span>
            </a>


            <a href="soil_layers.php" class="nav-item" data-page="soil_layers.php">
                <i class="fa-solid fa-layer-group"></i>
                <span>Soil Layers</span>
            </a>


            <div class="nav-section-title">
                Locations
            </div>


            <a href="municipalities.php" class="nav-item" data-page="municipalities.php">

                <i class="fa-solid fa-map-location-dot"></i>

                <span>
                    Municipalities
                </span>

            </a>


            <a href="barangays.php" class="nav-item" data-page="barangays.php">
                <i class="fa-solid fa-location-crosshairs"></i>
                <span>Barangays</span>
            </a>


            <div class="nav-section-title">
                GIS
            </div>


            <a href="admin_gis.php" class="nav-item active">

                <i class="fa-solid fa-map"></i>

                <span>
                    GIS Map
                </span>

            </a>


            <div class="nav-section-title">
                Reports
            </div>


            <a href="soil_reports.php" class="nav-item" data-page="soil_reports.php">
                <i class="fa-solid fa-file-lines"></i>
                <span>Soil Reports</span>
            </a>

            <a href="bearing_capacity.php" class="nav-item" data-page="bearing_capacity.php">
                <i class="fa-solid fa-chart-column"></i>
                <span>Bearing Capacity</span>
            </a>


        </nav>


        <div class="sidebar-footer">

            <a href="../../app/Controllers/logout.php" class="logout-link" id="logoutButton">

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

                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Open menu" title="Open menu">
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
                        Southern Leyte geographic boundaries
                    </p>

                </div>

            </div>

        </header>



        <!--
    |--------------------------------------------------------------------------
    | MAP
    |--------------------------------------------------------------------------
    -->

        <main class="content">

            <section class="map-card">

                <div id="adminGISMap" aria-label="Southern Leyte GIS Map"></div>


                <div class="map-status" id="mapStatus">
                    Loading Southern Leyte boundaries...
                </div>


                <div class="map-error" id="mapError">

                    <div class="map-error-box">

                        <i class="fa-solid fa-triangle-exclamation"></i>

                        <h2>
                            Unable to load map boundaries
                        </h2>

                        <p id="mapErrorMessage">
                            Please check the GIS GeoJSON files and try again.
                        </p>

                    </div>

                </div>

            </section>

        </main>

    </div>



    <!-- Leaflet -->

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>


    <script>
        const boreholes =
            <?= $mapJson ?: '[]' ?>;

        function escapeHtml(value) {
            if (value === null || value === undefined) {
                return "";
            }

            return String(value)
                .replaceAll("&", "&amp;")
                .replaceAll("<", "&lt;")
                .replaceAll(">", "&gt;")
                .replaceAll('"', "&quot;")
                .replaceAll("'", "&#039;");
        }

        function formatMetric(value, suffix) {
            if (value === null || value === undefined || value === "") {
                return "Not recorded";
            }

            return `${Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${suffix}`;
        }

        function highestBearingCapacity(borehole) {
            let capacity = null;

            borehole.layers.forEach(function (layer) {
                if (layer.bearing_capacity_kpa === null || layer.bearing_capacity_kpa === "") {
                    return;
                }

                const value = Number(layer.bearing_capacity_kpa);

                if (capacity === null || value > capacity) {
                    capacity = value;
                }
            });

            return capacity;
        }

        function capacityColor(value) {
            if (value === null) return "#64748b";
            if (value > 300) return "#166534";
            if (value >= 200) return "#2e8b57";
            if (value >= 100) return "#a66a3f";
            if (value >= 50) return "#b45309";
            return "#991b1b";
        }

        function boreholePopup(borehole) {
            const layers = borehole.layers.length
                ? borehole.layers.map(function (layer) {
                    return `
                        <div class="soil-popup-layer">
                            <strong>Layer ${escapeHtml(layer.layer_number)}: ${escapeHtml(layer.soil_type)}</strong>
                            <span>${escapeHtml(layer.soil_classification || "Unclassified")}</span>
                            <span>${escapeHtml(layer.soil_description || "No description")}</span>
                            <span>Depth: ${escapeHtml(layer.depth_from_m)} - ${escapeHtml(layer.depth_to_m)} m</span>
                            <span>SPT N-value: ${escapeHtml(layer.spt_n_value ?? "Not recorded")}</span>
                            <span>Bearing: ${escapeHtml(formatMetric(layer.bearing_capacity_kpa, "kPa"))}</span>
                        </div>
                    `;
                }).join("")
                : '<div class="soil-popup-layer">No soil layer records.</div>';

            return `
                <div class="soil-popup">
                    <h3>${escapeHtml(borehole.borehole_code)}</h3>
                    <p>${escapeHtml(borehole.barangay_name || "Barangay not recorded")}, ${escapeHtml(borehole.municipality_name || "Municipality not recorded")}</p>
                    <div class="soil-popup-grid">
                        <span><strong>Depth</strong>${escapeHtml(formatMetric(borehole.borehole_depth_m, "m"))}</span>
                        <span><strong>Elevation</strong>${escapeHtml(formatMetric(borehole.elevation_m, "m"))}</span>
                        <span><strong>Latitude</strong>${escapeHtml(borehole.latitude)}</span>
                        <span><strong>Longitude</strong>${escapeHtml(borehole.longitude)}</span>
                    </div>
                    <h4>Soil Layers</h4>
                    ${layers}
                </div>
            `;
        }

        document.addEventListener(
            "DOMContentLoaded",
            async function () {


                /*
                |--------------------------------------------------------------------------
                | MOBILE SIDEBAR
                |--------------------------------------------------------------------------
                */

                const mobileMenu =
                    document.getElementById("mobileMenu");

                const sidebar =
                    document.getElementById("sidebar");


                if (mobileMenu && sidebar) {

                    mobileMenu.addEventListener(
                        "click",
                        function () {

                            sidebar.classList.toggle("open");

                        }
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | MAP ELEMENTS
                |--------------------------------------------------------------------------
                */

                const mapElement =
                    document.getElementById("adminGISMap");

                const mapStatus =
                    document.getElementById("mapStatus");

                const mapError =
                    document.getElementById("mapError");

                const mapErrorMessage =
                    document.getElementById("mapErrorMessage");


                /*
                |--------------------------------------------------------------------------
                | INITIALIZE LEAFLET
                |--------------------------------------------------------------------------
                */

                try {

                    if (!window.L) {

                        throw new Error(
                            "Leaflet could not be loaded."
                        );

                    }


                    const map =
                        L.map(
                            "adminGISMap",
                            {
                                preferCanvas: true,

                                minZoom: 8,

                                maxZoom: 19,

                                zoomControl: true,

                                attributionControl: true
                            }
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | OPEN STREET MAP
                    |--------------------------------------------------------------------------
                    */

                    const tiles =
                        L.tileLayer(
                            "https://tile.openstreetmap.org/{z}/{x}/{y}.png",
                            {
                                tileSize: 256,

                                maxZoom: 19,

                                attribution:
                                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                            }
                        )
                            .addTo(map);


                    tiles.on(
                        "tileerror",
                        function () {

                            mapStatus.textContent =
                                "Background map unavailable. Boundaries remain available.";

                        }
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | SCALE
                    |--------------------------------------------------------------------------
                    */

                    L.control
                        .scale({
                            imperial: false
                        })
                        .addTo(map);


                    /*
                    |--------------------------------------------------------------------------
                    | LOAD QGIS GEOJSON FILES
                    |--------------------------------------------------------------------------
                    |
                    | These are the SAME files used by the public GIS map.
                    |
                    */

                    const datasetNames = [
                        "boundary",
                        "municipalities",
                        "barangays"
                    ];


                    const datasets =
                        await Promise.all(

                            datasetNames.map(
                                async function (name) {

                                    const response =
                                        await fetch(
                                            `../../src/qgis/southern_leyte_${name}.geojson`,
                                            {
                                                cache: "no-store"
                                            }
                                        );


                                    if (!response.ok) {

                                        throw new Error(
                                            `Unable to load ${name} boundary file.`
                                        );

                                    }


                                    const data =
                                        await response.json();


                                    if (
                                        data.type !==
                                        "FeatureCollection" ||
                                        !data.features ||
                                        !data.features.length
                                    ) {

                                        throw new Error(
                                            `${name} boundary file is empty or invalid.`
                                        );

                                    }


                                    return data;

                                }
                            )

                        );


                    const [
                        province,
                        municipalities,
                        barangays
                    ] = datasets;


                    /*
                    |--------------------------------------------------------------------------
                    | SAME STYLES AS USER GIS MAP
                    |--------------------------------------------------------------------------
                    */

                    const municipalityStyle = {

                        color:
                            "#176b4d",

                        weight:
                            2,

                        fillColor:
                            "#75b590",

                        fillOpacity:
                            0.16

                    };


                    const barangayStyle = {

                        color:
                            "#4788ac",

                        weight:
                            1.3,

                        fillColor:
                            "#8cc7dd",

                        fillOpacity:
                            0.12

                    };


                    /*
                    |--------------------------------------------------------------------------
                    | SOUTHERN LEYTE PROVINCE OUTLINE
                    |--------------------------------------------------------------------------
                    */

                    const provinceLayer =
                        L.geoJSON(
                            province,
                            {

                                interactive: false,

                                style: {

                                    color:
                                        "#0b3d2e",

                                    weight:
                                        4,

                                    fillColor:
                                        "#75b590",

                                    fillOpacity:
                                        0.05

                                }

                            }
                        )
                            .addTo(map);


                    /*
                    |--------------------------------------------------------------------------
                    | MUNICIPALITY BOUNDARIES
                    |--------------------------------------------------------------------------
                    */

                    const municipalityLayer =
                        L.geoJSON(
                            municipalities,
                            {

                                style:
                                    municipalityStyle,


                                onEachFeature:
                                    function (
                                        feature,
                                        layer
                                    ) {

                                        const name =
                                            feature
                                                .properties
                                                .NAME_2;


                                        /*
                                        | Municipality label
                                        */

                                        layer.bindTooltip(
                                            name,
                                            {
                                                sticky: true,

                                                direction:
                                                    "center",

                                                className:
                                                    "admin-map-label"
                                            }
                                        );


                                        /*
                                        | Highlight on hover
                                        */

                                        layer.on(
                                            "mouseover",
                                            function () {

                                                layer.setStyle(
                                                    {
                                                        weight: 3,

                                                        fillOpacity:
                                                            0.28
                                                    }
                                                );

                                            }
                                        );


                                        layer.on(
                                            "mouseout",
                                            function () {

                                                layer.setStyle(
                                                    municipalityStyle
                                                );

                                            }
                                        );

                                    }

                            }
                        )
                            .addTo(map);


                    /*
                    |--------------------------------------------------------------------------
                    | BARANGAY BOUNDARIES
                    |--------------------------------------------------------------------------
                    |
                    | Unlike the public explorer, the admin map displays
                    | the QGIS barangay boundaries directly so the entire
                    | Southern Leyte boundary structure is visible.
                    |
                    */

                    const barangayLayer =
                        L.geoJSON(
                            barangays,
                            {

                                style:
                                    barangayStyle,


                                onEachFeature:
                                    function (
                                        feature,
                                        layer
                                    ) {

                                        const barangay =
                                            feature
                                                .properties
                                                .NAME_3;

                                        const municipality =
                                            feature
                                                .properties
                                                .NAME_2;


                                        layer.bindTooltip(
                                            `${barangay}, ${municipality}`,
                                            {
                                                sticky: true,

                                                direction:
                                                    "center",

                                                className:
                                                    "admin-map-label"
                                            }
                                        );


                                        layer.on(
                                            "mouseover",
                                            function () {

                                                layer.setStyle(
                                                    {
                                                        weight: 2.5,

                                                        fillOpacity:
                                                            0.25
                                                    }
                                                );

                                            }
                                        );


                                        layer.on(
                                            "mouseout",
                                            function () {

                                                layer.setStyle(
                                                    barangayStyle
                                                );

                                            }
                                        );

                                    }

                            }
                        )
                            .addTo(map);


                    const boreholeLayer =
                        L.layerGroup()
                            .addTo(map);


                    boreholes.forEach(
                        function (borehole) {

                            const marker =
                                L.circleMarker(
                                    [
                                        Number(borehole.latitude),
                                        Number(borehole.longitude)
                                    ],
                                    {
                                        radius: 8,
                                        fillColor: capacityColor(highestBearingCapacity(borehole)),
                                        color: "#ffffff",
                                        weight: 2,
                                        opacity: 1,
                                        fillOpacity: 0.92
                                    }
                                );

                            marker.bindPopup(
                                boreholePopup(borehole),
                                {
                                    maxWidth: 380
                                }
                            );

                            marker.addTo(boreholeLayer);

                        }
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | LAYER CONTROL
                    |--------------------------------------------------------------------------
                    */

                    L.control.layers(
                        null,
                        {

                            "Southern Leyte Boundary":
                                provinceLayer,

                            "Municipality / City Boundaries":
                                municipalityLayer,

                            "Barangay Boundaries":
                                barangayLayer,

                            "Borehole Soil Records":
                                boreholeLayer

                        },
                        {
                            collapsed: false
                        }
                    )
                        .addTo(map);


                    /*
                    |--------------------------------------------------------------------------
                    | FIT MAP TO SOUTHERN LEYTE
                    |--------------------------------------------------------------------------
                    */

                    if (boreholes.length) {

                        const boreholeBounds =
                            L.latLngBounds(
                                boreholes.map(
                                    function (borehole) {
                                        return [
                                            Number(borehole.latitude),
                                            Number(borehole.longitude)
                                        ];
                                    }
                                )
                            );

                        map.fitBounds(
                            boreholeBounds,
                            {
                                padding: [
                                    40,
                                    40
                                ],
                                maxZoom: 13
                            }
                        );

                    } else {

                        map.fitBounds(
                            provinceLayer.getBounds(),
                            {
                                padding: [
                                    20,
                                    20
                                ]
                            }
                        );

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | STATUS
                    |--------------------------------------------------------------------------
                    */

                    mapStatus.textContent =
                        boreholes.length
                            ? `Southern Leyte boundaries loaded - ${boreholes.length} borehole records shown`
                            : "Southern Leyte boundaries loaded - no borehole records yet";


                    /*
                    |--------------------------------------------------------------------------
                    | RESIZE
                    |--------------------------------------------------------------------------
                    */

                    if (
                        "ResizeObserver"
                        in window
                    ) {

                        new ResizeObserver(
                            function () {

                                map.invalidateSize();

                            }
                        )
                            .observe(mapElement);

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | BROWSER RESIZE
                    |--------------------------------------------------------------------------
                    */

                    window.addEventListener(
                        "resize",
                        function () {

                            map.invalidateSize();

                        }
                    );


                }
                catch (error) {

                    console.error(
                        "Admin GIS error:",
                        error
                    );


                    mapStatus.style.display =
                        "none";


                    mapError.classList.add(
                        "show"
                    );


                    mapErrorMessage.textContent =
                        error.message ||
                        "The Southern Leyte GIS boundary data could not be loaded.";

                }

            }

        );


        /*
        |--------------------------------------------------------------------------
        | PREVENT BACK/FORWARD CACHE FROM RESTORING ADMIN PAGE
        |--------------------------------------------------------------------------
        */

        window.addEventListener(
            "pageshow",
            function (event) {

                if (event.persisted) {

                    window.location.reload();

                }

            }
        );

    </script>


    <style>
        /*
|--------------------------------------------------------------------------
| MAP LABEL
|--------------------------------------------------------------------------
*/

        .admin-map-label {

            background:
                rgba(255, 255, 255, 0.94);

            border:
                1px solid rgba(11, 61, 46, 0.16);

            border-radius: 5px;

            padding:
                4px 7px;

            color:
                #16362b;

            font-family:
                "Inter",
                Arial,
                sans-serif;

            font-size: 10px;

            font-weight: 700;

            box-shadow:
                0 3px 10px rgba(0, 0, 0, 0.12);

        }


        /*
|--------------------------------------------------------------------------
| LEAFLET CONTROLS
|--------------------------------------------------------------------------
*/

        .leaflet-control-layers {

            border:
                1px solid #dce5e0 !important;

            border-radius:
                9px !important;

            box-shadow:
                0 6px 18px rgba(0, 0, 0, 0.12) !important;

        }


        .leaflet-control-layers-expanded {

            padding:
                9px 11px !important;

            font-family:
                "Inter",
                Arial,
                sans-serif;

            font-size:
                11px;

        }

        .soil-popup {
            min-width: 250px;
            font-family: "Inter", Arial, sans-serif;
        }

        .soil-popup h3 {
            margin: 0 0 3px;
            color: #0b3d2e;
            font-size: 15px;
        }

        .soil-popup p {
            margin: 0 0 10px;
            color: #64748b;
            font-size: 11px;
        }

        .soil-popup h4 {
            margin: 12px 0 6px;
            padding-top: 8px;
            border-top: 1px solid #e3e9e5;
            font-size: 11px;
        }

        .soil-popup-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        .soil-popup-grid span,
        .soil-popup-layer {
            display: grid;
            gap: 2px;
            padding: 7px;
            border-radius: 6px;
            background: #f6f9f7;
            color: #33423c;
            font-size: 10px;
        }

        .soil-popup-grid strong {
            color: #71807a;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .soil-popup-layer {
            margin-top: 5px;
            background: #fff;
            border: 1px solid #edf1ee;
        }
    </style>


    <script>

        /*
        |--------------------------------------------------------------------------
        | LOGOUT CONFIRMATION
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            "DOMContentLoaded",
            function () {

                const logoutButton =
                    document.getElementById(
                        "logoutButton"
                    );


                if (!logoutButton) {
                    return;
                }


                logoutButton.addEventListener(
                    "click",
                    function (event) {

                        event.preventDefault();


                        if (
                            typeof Swal === "undefined"
                        ) {

                            window.location.href =
                                logoutButton.href;

                            return;

                        }


                        Swal.fire({

                            toast: false,

                            position:
                                "center",

                            icon:
                                "question",

                            title:
                                "Sign out?",

                            text:
                                "You will be returned to the public site.",

                            showConfirmButton:
                                true,

                            showCancelButton:
                                true,

                            confirmButtonText:
                                "Sign out",

                            cancelButtonText:
                                "Stay",

                            confirmButtonColor:
                                "#0b3d2e"

                        })
                            .then(
                                function (result) {

                                    if (
                                        result.isConfirmed
                                    ) {

                                        window.location.href =
                                            logoutButton.href;

                                    }

                                }
                            );

                    }
                );

            }
        );

    </script>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


</body>

</html>
