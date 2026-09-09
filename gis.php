<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Explore municipality and barangay boundaries across Southern Leyte.">
  <meta name="theme-color" content="#0b3d2e">
  <title>GIS Map | Southern Leyte Soil Information System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="css/gis.css">
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script defer src="js/gis.js"></script>
</head>
<body class="gis-page">
  <a class="gis-skip" href="#gisMap">Skip to map</a>

  <header class="gis-header">
    <a class="brand" href="index.php" aria-label="Southern Leyte Soil Information System home">
      <span class="brand-mark"><img src="images/logo.png" alt=""></span>
      <span class="brand-copy"><strong>SOUTHERN LEYTE</strong><small>SOIL INFORMATION SYSTEM</small></span>
    </a>
    <button class="gis-nav-toggle" id="gisNavToggle" type="button" aria-expanded="false" aria-controls="gisNavigation">
      <span>Menu</span><span aria-hidden="true">☰</span>
    </button>
    <nav id="gisNavigation" aria-label="Primary navigation">
      <a href="index.php">Home</a>
      <a href="gis.php" aria-current="page">GIS Map</a>
      <a href="index.php#soil-data">Soil Data</a>
    </nav>
  </header>

  <main class="gis-main">
    <div class="gis-map-shell explorer-open">
      <div id="gisMap" tabindex="0" role="region" aria-label="Interactive map of Southern Leyte"></div>

      <button id="toggleExplorer" class="gis-panel-toggle" type="button" aria-expanded="true" aria-controls="gisExplorer">
        <span aria-hidden="true">⌕</span><span class="toggle-label">Hide explorer</span>
      </button>

      <aside id="gisExplorer" class="gis-sidebar" aria-label="Location explorer">
        <div class="gis-sidebar-heading">
          <div><p class="gis-kicker">SOUTHERN LEYTE</p><h1>Explore the map</h1></div>
          <button id="closeExplorer" type="button" aria-label="Close location explorer">×</button>
        </div>
        <p class="gis-sidebar-intro">Find a municipality or barangay and view its boundary.</p>

        <label class="gis-search-field" for="gisSearch">
          <span aria-hidden="true">⌕</span>
          <input id="gisSearch" type="search" placeholder="Search a location" disabled autocomplete="off" aria-controls="gisResults">
        </label>
        <div id="gisResults" class="gis-results" aria-label="Search results" aria-live="polite"></div>

        <div class="gis-fields">
          <label for="gisMunicipality">Municipality or city</label>
          <select id="gisMunicipality" disabled><option value="">Loading locations…</option></select>
          <label for="gisBarangay">Barangay</label>
          <select id="gisBarangay" disabled><option value="">Select a municipality first</option></select>
        </div>

        <div class="gis-details" aria-live="polite">
          <div><p class="gis-kicker" id="locationType">PROVINCE</p><h2 id="locationName">Southern Leyte</h2></div>
          <p id="locationDescription">Loading boundaries…</p>
        </div>

        <button id="resetMap" class="gis-reset" type="button" disabled><span aria-hidden="true">↺</span> Reset province view</button>

        <details class="gis-legend">
          <summary>Map legend</summary>
          <div><p><span class="key-line municipality-key"></span>Municipality / City</p><p><span class="key-line barangay-key"></span>Barangay</p><p><span class="key-line selected-key"></span>Selected area</p></div>
        </details>
      </aside>

      <button id="gisBackdrop" class="gis-backdrop" type="button" aria-label="Close location explorer" tabindex="-1"></button>
      <div id="gisStatus" class="gis-status" role="status">Loading map and boundaries…</div>
      <button id="retryMap" class="gis-retry" type="button" hidden>Retry loading map</button>
    </div>
    <noscript>Enable JavaScript to explore municipalities and barangays on the map.</noscript>
  </main>
</body>
</html>
