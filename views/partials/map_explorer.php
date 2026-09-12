    <div class="gis-map-shell explorer-open">
      <div id="gisMap" tabindex="0" role="region" aria-label="Interactive map of Southern Leyte"></div>

      <button id="toggleExplorer" class="gis-panel-toggle" type="button" aria-expanded="true" aria-controls="gisExplorer">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg><span class="toggle-label">Hide explorer</span>
      </button>

      <aside id="gisExplorer" class="gis-sidebar" aria-label="Location explorer">
        <div class="gis-sidebar-heading">
          <div><p class="gis-kicker">SOUTHERN LEYTE</p><h1>Explore the map</h1></div>
          <button id="closeExplorer" type="button" aria-label="Close location explorer">×</button>
        </div>
        <p class="gis-sidebar-intro">Find a municipality or barangay and view its boundary.</p>

        <label class="gis-search-field" for="gisSearch">
          <span class="gis-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg></span>
          <input id="gisSearch" type="search" placeholder="Search a location" disabled autocomplete="off" aria-controls="gisResults">
        </label>
        <div id="gisResults" class="gis-results" aria-label="Search results" aria-live="polite"></div>

        <div class="gis-fields">
          <label for="gisMunicipality">Municipality or city</label>
          <select id="gisMunicipality" disabled><option value="">Loading locations…</option></select>
          <label for="gisBarangay">Barangay</label>
          <select id="gisBarangay" disabled><option value="">Select a municipality first</option></select>
        </div>

        <p id="locationSource" class="gis-source" role="status">Loading location directory...</p>
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
