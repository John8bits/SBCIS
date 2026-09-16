<div class="gis-map-shell">
  <div id="gisMap" tabindex="0" role="region" aria-label="Southern Leyte borehole and interpolation map"></div>

  <?php if (!empty($interpolationPreviewActive)): ?>
  <div class="gis-demo-banner" role="status">
    <strong>UI PREVIEW</strong>
    <span>Synthetic data — not field evidence or an engineering result</span>
  </div>
  <?php endif; ?>

  <button id="toggleExplorer" class="gis-panel-toggle gis-explorer-toggle" type="button" aria-expanded="true" aria-controls="gisExplorer">
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg>
    <span class="toggle-label">Hide explorer</span>
  </button>

  <button id="toggleInsights" class="gis-panel-toggle gis-insights-toggle" type="button" aria-label="Open map information" aria-expanded="false" aria-controls="gisInsights">
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M5 5h14v14H5zM8 9h8M8 12h8M8 15h5"></path></svg>
    <span>Map information</span>
  </button>

  <aside id="gisExplorer" class="gis-sidebar" aria-label="Location explorer">
    <div class="gis-sidebar-heading">
      <div>
        <p class="gis-kicker">SOUTHERN LEYTE</p>
        <h1>Location explorer</h1>
      </div>
      <button id="closeExplorer" type="button" aria-label="Close location explorer">&times;</button>
    </div>
    <p class="gis-sidebar-intro">Search the province and inspect its official municipality and barangay boundaries.</p>

    <label class="gis-search-field" for="gisSearch">
      <span class="gis-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg></span>
      <input id="gisSearch" type="search" placeholder="Search municipality or barangay" disabled autocomplete="off" aria-controls="gisResults">
    </label>
    <div id="gisResults" class="gis-results" aria-label="Search results" aria-live="polite"></div>

    <div class="gis-fields">
      <label for="gisMunicipality">Municipality or city</label>
      <select id="gisMunicipality" disabled><option value="">Loading locations&hellip;</option></select>
      <label for="gisBarangay">Barangay</label>
      <select id="gisBarangay" disabled><option value="">Select a municipality first</option></select>
    </div>

    <p id="locationSource" class="gis-source" role="status">Loading location directory&hellip;</p>
    <div class="gis-details" aria-live="polite">
      <div><p class="gis-kicker" id="locationType">PROVINCE</p><h2 id="locationName">Southern Leyte</h2></div>
      <p id="locationDescription">Loading boundaries&hellip;</p>
    </div>

    <button id="resetMap" class="gis-reset" type="button" disabled><span aria-hidden="true">&#8634;</span> Reset province view</button>

    <details class="gis-legend">
      <summary>Boundary key</summary>
      <div>
        <p><span class="key-line province-key"></span>Southern Leyte boundary</p>
        <p><span class="key-line municipality-key"></span>Municipality / city</p>
        <p><span class="key-line barangay-key"></span>Barangay</p>
        <p><span class="key-line selected-key"></span>Selected area</p>
      </div>
    </details>
  </aside>

  <aside id="gisInsights" class="gis-insights" aria-label="Interpolation and borehole information">
    <div class="gis-insights-heading">
      <div>
        <p class="gis-kicker">GEOTECHNICAL MAP</p>
        <h2>Southern Leyte soil interpolation</h2>
      </div>
      <button id="closeInsights" type="button" aria-label="Close map information">&times;</button>
    </div>
    <p class="gis-insights-intro">Measured boreholes and the latest approved estimated surface for the province.</p>

    <section id="gisInterpolation" class="gis-interpolation" aria-label="Interpolation status"
      data-mode="<?= !empty($interpolationPreviewActive) ? 'preview' : (!empty($interpolationAdmin) ? 'admin' : 'public') ?>"
      <?php if (!empty($interpolationAdmin)): ?>data-csrf="<?= htmlspecialchars($_SESSION['interpolation_csrf'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
      <div class="gis-stat-grid">
        <div><span>Study area</span><strong>Southern Leyte</strong></div>
        <div><span>Measurement</span><strong data-interpolation="measurement">Not configured</strong></div>
        <div><span>Method</span><strong data-interpolation="method">Not available</strong></div>
        <div><span>Source boreholes</span><strong id="visibleBoreholeCount">&mdash;</strong></div>
      </div>

      <div class="gis-state-card" role="status" aria-live="polite">
        <span class="gis-state-icon" aria-hidden="true"></span>
        <div>
          <strong data-interpolation="state-label">Checking data</strong>
          <p data-interpolation="status">Loading the latest approved interpolation&hellip;</p>
        </div>
      </div>

      <section class="gis-info-section" aria-labelledby="areaTitle">
        <div class="gis-section-heading">
          <div><span class="gis-section-eyebrow">MAP FOCUS</span><h3 id="areaTitle">Selected area</h3></div>
        </div>
        <p><strong id="insightAreaName">Southern Leyte</strong></p>
        <p id="insightAreaType" class="gis-muted">Province study boundary</p>
      </section>

      <section class="gis-info-section gis-surface-legend" aria-labelledby="surfaceLegendTitle">
        <div class="gis-section-heading">
          <div><span class="gis-section-eyebrow">ESTIMATED SURFACE</span><h3 id="surfaceLegendTitle">Interpolation legend</h3></div>
          <span class="gis-data-badge gis-estimated-badge">ESTIMATED</span>
        </div>
        <div data-interpolation="scale" class="gis-estimate-scale" hidden></div>
        <div data-interpolation="ticks" class="gis-scale-ticks" hidden><span></span><span></span><span></span></div>
        <p data-interpolation="legend">No approved estimated surface is displayed.</p>
        <div class="gis-symbol-row"><span class="gis-area-key" aria-hidden="true"></span><span>Click an area for measured records</span></div>
        <div class="gis-symbol-row"><span class="key-line municipality-key" aria-hidden="true"></span><span>Municipality boundary</span></div>
      </section>

      <?php if (!empty($interpolationAdmin) && empty($interpolationPreviewActive)): ?>
      <section class="gis-info-section gis-admin-tools" aria-labelledby="adminInterpolationTitle">
        <div class="gis-section-heading"><div><span class="gis-section-eyebrow">ADMINISTRATION</span><h3 id="adminInterpolationTitle">Data readiness</h3></div></div>
        <p data-interpolation="counts"></p>
        <p data-interpolation="reasons"></p>
        <p data-interpolation="admin"></p>
        <details><summary>Publication metadata</summary><p data-interpolation="metadata"></p></details>
        <div class="gis-admin-actions">
          <button data-interpolation="regenerate" class="gis-reset gis-primary-action" type="button">Recheck / regenerate</button>
          <button data-interpolation="retry" class="gis-reset" type="button">Refresh status</button>
        </div>
        <a class="gis-preview-link" href="admin_gis.php?preview=synthetic">Preview map with sample data <span aria-hidden="true">&#9654;</span></a>
        <a class="gis-record-link" href="soil_records.php">Review source soil records <span aria-hidden="true">&rarr;</span></a>
      </section>
      <?php elseif (!empty($interpolationAdmin)): ?>
      <section class="gis-info-section gis-preview-controls" aria-labelledby="previewControlTitle">
        <div class="gis-section-heading"><div><span class="gis-section-eyebrow">ADMIN PREVIEW</span><h3 id="previewControlTitle">Synthetic sample mode</h3></div></div>
        <p>This view uses non-field sample records only to demonstrate the interface. It is not published to public users.</p>
        <a class="gis-preview-exit" href="admin_gis.php">Exit sample preview</a>
      </section>
      <?php endif; ?>

      <div class="gis-disclaimer">
        <strong>Interpretation note</strong>
        <p>Boundary summaries show stored measurements for the selected area. A colored surface represents estimates between approved observations and does not replace a site-specific geotechnical investigation.</p>
      </div>
    </section>
  </aside>

  <div id="gisDataModal" class="gis-data-modal" role="dialog" aria-modal="false" aria-labelledby="gisDataModalTitle" hidden>
    <button id="gisDataModalBackdrop" class="gis-data-modal-backdrop" type="button" aria-label="Close area data"></button>
    <section class="gis-data-modal-card" aria-label="Selected boundary details">
      <header class="gis-data-modal-header">
        <div>
          <p class="gis-kicker" id="gisDataModalScope">SELECTED AREA</p>
          <h2 id="gisDataModalTitle">Area data</h2>
          <p id="gisDataModalLocation" class="gis-data-modal-location">Southern Leyte</p>
        </div>
        <button id="closeDataModal" type="button" aria-label="Close area data">&times;</button>
      </header>
      <div class="gis-modal-source-row">
        <span id="gisDataModalBadge" class="gis-data-badge">MEASURED</span>
        <p id="gisDataModalSource">Stored borehole observations inside this boundary.</p>
      </div>
      <div class="gis-modal-stats">
        <div><span>Boreholes</span><strong id="modalBoreholeCount">0</strong></div>
        <div><span>Soil layers</span><strong id="modalLayerCount">0</strong></div>
        <div><span>Avg. bearing</span><strong id="modalBearingAverage">—</strong></div>
        <div><span>Avg. SPT N</span><strong id="modalSptAverage">—</strong></div>
      </div>
      <div class="gis-modal-estimate">
        <div><span class="gis-data-badge gis-estimated-badge">ESTIMATED</span><strong>Area surface value</strong></div>
        <p id="modalEstimatedValue">No approved estimated value is available for this area.</p>
      </div>
      <div id="gisDataModalEmpty" class="gis-modal-empty">No measured borehole records are available inside this boundary.</div>
      <div id="gisDataModalRecords" class="gis-modal-records"></div>
      <footer>Measured records and interpolated estimates are different data types. Area summaries do not replace a site-specific geotechnical investigation.</footer>
    </section>
  </div>

  <button id="gisBackdrop" class="gis-backdrop" type="button" aria-label="Close map panel" tabindex="-1"></button>
  <div id="gisDataKey" class="gis-data-key" hidden>
    <span aria-hidden="true">1</span>
    <strong>Badge = boreholes in area</strong>
  </div>
  <div id="gisStatus" class="gis-status" role="status">Loading map and boundaries&hellip;</div>
  <button id="retryMap" class="gis-retry" type="button" hidden>Retry loading map</button>
</div>
