<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GIS Map | Southern Leyte Soil Information System</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="css/gis.css">
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script defer src="js/gis.js"></script>
</head>
<body class="gis-page">
  <header class="gis-header">
    <a class="brand" href="index.php"><span class="brand-mark"><img src="images/logo.png" alt="Southern Leyte logo"></span><span class="brand-copy"><strong>SOUTHERN LEYTE</strong><small>SOIL INFORMATION SYSTEM</small></span></a>
    <nav aria-label="Primary navigation"><a href="index.php">Home</a><a href="gis.php" aria-current="page">GIS Map</a><a href="index.php#soil-data">Soil Data</a></nav>
  </header>
  <main class="gis-main">
    <div class="gis-heading"><div><p class="gis-kicker">EXPLORE SOUTHERN LEYTE</p><h1>GIS Map</h1><p>Select a municipality, then zoom into a barangay.</p></div><button id="resetMap" type="button" disabled>View whole province</button></div>
    <div class="gis-workspace">
      <aside class="gis-sidebar" aria-label="Location explorer">
        <h2>Find a location</h2>
        <label for="gisMunicipality">Municipality / City</label>
        <select id="gisMunicipality" disabled><option value="">Loading municipalities…</option></select>
        <label for="gisBarangay">Barangay</label>
        <select id="gisBarangay" disabled><option value="">Select a municipality first</option></select>
        <label for="gisSearch">Search locations</label>
        <input id="gisSearch" type="search" placeholder="Municipality or barangay name" disabled autocomplete="off" aria-controls="gisResults">
        <div id="gisResults" class="gis-results" aria-label="Search results"></div>
        <div class="gis-details" aria-live="polite"><p class="gis-kicker" id="locationType">PROVINCE</p><h2 id="locationName">Southern Leyte</h2><p id="locationDescription">Loading boundaries…</p></div>
        <div class="gis-key"><h3>Map legend</h3><p><span class="key-line municipality-key"></span>Municipality / City</p><p><span class="key-line barangay-key"></span>Barangay</p><p><span class="key-line selected-key"></span>Selected location</p></div>
        <p class="gis-note">Boundaries show administrative areas. Soil bearing capacity values are not included in this boundary dataset.</p>
      </aside>
      <div class="gis-canvas">
        <div id="gisMap" aria-label="Interactive map of Southern Leyte"></div>
        <div id="gisStatus" class="gis-status" role="status">Loading map and boundaries…</div>
        <button id="retryMap" class="gis-retry" type="button" hidden>Retry loading map</button>
      </div>
    </div>
    <noscript>Enable JavaScript to explore municipalities and barangays on the map.</noscript>
  </main>
</body>
</html>
