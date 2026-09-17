<div class="gis-map-shell">
  <div id="gisMap" tabindex="0" role="region" aria-label="Southern Leyte borehole and interpolation map"></div>

  <button id="toggleExplorer" class="gis-panel-toggle gis-explorer-toggle" type="button" aria-expanded="false" aria-controls="gisExplorer">
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg>
    <span class="toggle-label">Locations</span>
  </button>

  <div class="gis-map-actions" aria-label="Map controls">
    <button id="quickResetMap" class="gis-panel-toggle gis-quick-reset" type="button" aria-label="Reset map view" disabled>
      <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M4 12a8 8 0 1 0 2.34-5.66L4 8.7M4 4v4.7h4.7"></path></svg>
      <span>Reset</span>
    </button>
    <button id="toggleSurface" class="gis-panel-toggle gis-surface-toggle" type="button" aria-label="Hide interpolation colors" aria-pressed="true">
      <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M3 12s3.2-5.5 9-5.5S21 12 21 12s-3.2 5.5-9 5.5S3 12 3 12Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
      <span>Surface</span>
    </button>
    <button id="toggleInsights" class="gis-panel-toggle gis-insights-toggle" type="button" aria-label="Open map data" aria-expanded="false" aria-controls="gisInsights">
      <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M5 5h14v14H5zM8 9h8M8 12h8M8 15h5"></path></svg>
      <span>Map data</span>
    </button>
    <button id="toggleFullscreen" class="gis-panel-toggle gis-fullscreen-toggle" type="button" aria-label="View map fullscreen" aria-pressed="false">
      <svg class="gis-fullscreen-enter" aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"></path></svg>
      <svg class="gis-fullscreen-exit" aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M3 8h5V3M21 8h-5V3M3 16h5v5M21 16h-5v5"></path></svg>
      <span>Fullscreen</span>
    </button>
  </div>

  <aside id="gisExplorer" class="gis-sidebar" aria-label="Location search">
    <div class="gis-sidebar-heading">
      <div><p class="gis-kicker">MAP TOOLS</p><h1>Find a location</h1></div>
      <button id="closeExplorer" type="button" aria-label="Close location search">&times;</button>
    </div>
    <label class="gis-search-field" for="gisSearch">
      <span class="gis-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg></span>
      <input id="gisSearch" type="search" placeholder="Search location or Borehole ID" disabled autocomplete="off" aria-controls="gisResults">
    </label>
    <div id="gisResults" class="gis-results" aria-label="Search results" aria-live="polite"></div>
    <div class="gis-fields">
      <label for="gisMunicipality">Municipality or city</label>
      <select id="gisMunicipality" disabled><option value="">Loading locations&hellip;</option></select>
      <label for="gisBarangay">Barangay</label>
      <select id="gisBarangay" disabled><option value="">Select a municipality first</option></select>
    </div>
    <p id="locationSource" class="gis-source" role="status">Loading locations&hellip;</p>
    <div class="gis-details" aria-live="polite">
      <div><p class="gis-kicker" id="locationType">PROVINCE</p><h2 id="locationName">Southern Leyte</h2></div>
      <p id="locationDescription">Loading boundaries&hellip;</p>
    </div>
    <button id="resetMap" class="gis-reset" type="button" disabled><span aria-hidden="true">&#8634;</span> Show all Southern Leyte</button>
    <details class="gis-legend">
      <summary>Map key</summary>
      <div>
        <p><span class="key-line province-key"></span>Province</p>
        <p><span class="key-line municipality-key"></span>Municipality / city</p>
        <p><span class="key-line barangay-key"></span>Barangay</p>
        <p><span class="key-line selected-key"></span>Selected area</p>
      </div>
    </details>
  </aside>

  <aside id="gisInsights" class="gis-insights" aria-label="Interpolation and borehole data">
    <div class="gis-insights-heading">
      <div><p class="gis-kicker">MAP DATA</p><h2>Interpolation</h2></div>
      <button id="closeInsights" type="button" aria-label="Close map data">&times;</button>
    </div>
    <section id="gisInterpolation" class="gis-interpolation" aria-label="Interpolation status"
      data-mode="<?= !empty($interpolationAdmin) ? 'admin' : 'public' ?>"
      <?php if (!empty($interpolationAdmin)): ?>data-csrf="<?= htmlspecialchars($_SESSION['interpolation_csrf'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
      <div class="gis-stat-grid">
        <div><span>Area</span><strong>Southern Leyte</strong></div>
        <div><span>Measurement</span><strong data-interpolation="measurement">Not configured</strong></div>
        <div><span>Method</span><strong data-interpolation="method">Not available</strong></div>
        <div><span>Boreholes</span><strong id="visibleBoreholeCount">&mdash;</strong></div>
      </div>
      <div class="gis-state-card" role="status" aria-live="polite">
        <span class="gis-state-icon" aria-hidden="true"></span>
        <div><strong data-interpolation="state-label">Checking data</strong><p data-interpolation="status">Loading interpolation&hellip;</p></div>
      </div>
      <section class="gis-model-summary" aria-label="Interpolation model summary">
        <div class="gis-model-summary-heading"><span class="gis-model-live" aria-hidden="true"></span><strong>Published interpolation model</strong><span data-interpolation="generated" class="gis-model-time">Checking&hellip;</span></div>
        <p class="gis-model-description">A spatial estimate built from recorded borehole measurements. It is not a substitute for a site investigation.</p>
        <div class="gis-model-metrics">
          <div><span>Input points</span><strong data-interpolation="observations">&mdash;</strong></div>
          <div><span>Model</span><strong data-interpolation="model">Checking&hellip;</strong></div>
          <div><span>Output range</span><strong data-interpolation="output-range">&mdash;</strong></div>
        </div>
        <div class="gis-model-keys"><span><i class="gis-observed-key" aria-hidden="true"></i>Observed borehole</span><span><i class="gis-estimate-key" aria-hidden="true"></i>Interpolated estimate</span></div>
      </section>
      <section class="gis-info-section" aria-labelledby="areaTitle">
        <div class="gis-section-heading"><div><span class="gis-section-eyebrow">SELECTED</span><h3 id="areaTitle">Map area</h3></div></div>
        <p><strong id="insightAreaName">Southern Leyte</strong></p>
        <p id="insightAreaType" class="gis-muted">Province</p>
      </section>
      <section class="gis-info-section gis-surface-legend" aria-labelledby="surfaceLegendTitle">
        <div class="gis-section-heading">
          <div><span class="gis-section-eyebrow">SURFACE</span><h3 id="surfaceLegendTitle">Bearing capacity</h3></div>
          <span class="gis-data-badge gis-estimated-badge">ESTIMATE</span>
        </div>
        <div data-interpolation="scale" class="gis-range-list" hidden>
          <div><i class="range-very-low"></i><span><strong>&lt; 100 kPa</strong>Very low</span></div>
          <div><i class="range-low"></i><span><strong>100–150 kPa</strong>Low</span></div>
          <div><i class="range-moderate"></i><span><strong>151–200 kPa</strong>Moderate</span></div>
          <div><i class="range-high"></i><span><strong>201–250 kPa</strong>High</span></div>
          <div><i class="range-very-high"></i><span><strong>&gt; 250 kPa</strong>Very high</span></div>
        </div>
        <div data-interpolation="ticks" class="gis-scale-ticks" hidden></div>
        <p data-interpolation="legend">No interpolation is displayed.</p>
        <div class="gis-symbol-row"><span class="gis-area-key" aria-hidden="true"></span><span>Area bands are estimates; select an area to inspect source records.</span></div>
      </section>
      <?php if (!empty($interpolationAdmin)): ?>
      <section class="gis-info-section gis-admin-tools" aria-labelledby="adminInterpolationTitle">
        <div class="gis-section-heading"><div><span class="gis-section-eyebrow">ADMIN</span><h3 id="adminInterpolationTitle">Interpolation status</h3></div></div>
        <p data-interpolation="counts"></p>
        <p data-interpolation="reasons"></p>
        <p data-interpolation="admin"></p>
        <details><summary>Technical details</summary><p data-interpolation="metadata"></p></details>
        <div class="gis-admin-actions">
          <button data-interpolation="regenerate" class="gis-reset gis-primary-action" type="button">Update surface</button>
          <button data-interpolation="retry" class="gis-reset" type="button">Refresh</button>
        </div>
        <a class="gis-record-link" href="soil_records.php">Manage soil records <span aria-hidden="true">&rarr;</span></a>
      </section>
      <?php endif; ?>
      <p class="gis-map-note">Map estimates are for reference and do not replace a site investigation.</p>
    </section>
  </aside>

  <section id="gisCapacityCard" class="gis-capacity-card" aria-live="polite" hidden>
    <button id="closeCapacityCard" type="button" aria-label="Close bearing capacity details">&times;</button>
    <div class="gis-capacity-heading">
      <span id="gisCapacitySwatch" class="gis-capacity-swatch" aria-hidden="true"></span>
      <div><small id="gisCapacityScope">SELECTED AREA</small><strong id="gisCapacityName">Southern Leyte</strong></div>
    </div>
    <div class="gis-capacity-value"><strong id="gisCapacityValue">—</strong><span id="gisCapacityClass">No estimate</span></div>
    <p id="gisCapacityRange">Click a colored area to inspect its interpolation range.</p>
    <button id="gisCapacityRecords" class="gis-capacity-records" type="button">View borehole records</button>
  </section>

  <div id="gisDataModal" class="gis-data-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="gisDataModalTitle" hidden>
    <button id="gisDataModalBackdrop" class="gis-data-modal-backdrop" type="button" aria-label="Close area details"></button>
    <section class="gis-data-modal-card" aria-label="Selected area details">
      <header class="gis-data-modal-header">
        <div><p class="gis-kicker" id="gisDataModalScope">AREA DETAILS</p><h2 id="gisDataModalTitle">Area data</h2><p id="gisDataModalLocation" class="gis-data-modal-location">Southern Leyte</p></div>
        <button id="closeDataModal" type="button" aria-label="Close area details">&times;</button>
      </header>
      <div class="gis-modal-source-row"><span id="gisDataModalBadge" class="gis-data-badge">RECORDS</span><p id="gisDataModalSource">Borehole records inside this boundary.</p></div>
      <div class="gis-modal-stats">
        <div><span>Boreholes</span><strong id="modalBoreholeCount">0</strong></div>
        <div><span>Soil layers</span><strong id="modalLayerCount">0</strong></div>
        <div><span>Avg. bearing</span><strong id="modalBearingAverage">&mdash;</strong></div>
        <div><span>Avg. SPT N</span><strong id="modalSptAverage">&mdash;</strong></div>
      </div>
      <div class="gis-modal-estimate"><div><span class="gis-data-badge gis-estimated-badge">ESTIMATE</span><strong>Interpolated area value</strong></div><p id="modalEstimatedValue">No interpolation is available for this area.</p></div>
      <div id="gisDataModalEmpty" class="gis-modal-empty">No borehole records are available inside this boundary.</div>
      <div id="gisDataModalRecords" class="gis-modal-records"></div>
      <footer>Interpolated values are estimates. Use site-specific testing for engineering decisions.</footer>
    </section>
  </div>

  <button id="gisBackdrop" class="gis-backdrop" type="button" aria-label="Close map panel" tabindex="-1"></button>
  <div id="gisDataKey" class="gis-data-key" hidden><span aria-hidden="true">1</span><strong>Boreholes in area</strong></div>
  <div id="gisStatus" class="gis-status" role="status">Loading map&hellip;</div>
  <button id="retryMap" class="gis-retry" type="button" hidden>Retry loading map</button>
</div>
