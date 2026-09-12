<?php

require_once __DIR__ . '/../app/Models/geotechnical_data.php';

$boreholes = [];
$recordsAvailable = false;

try {
    $db = sbcis_get_database();

    if ($db instanceof PDO) {
        $boreholes = sbcis_fetch_map_boreholes($db);
        $recordsAvailable = true;
    }
} catch (Throwable $e) {
    $boreholes = [];
}

$mapJson = json_encode(
    $boreholes,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" href="../src/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Explore municipality and barangay boundaries across Southern Leyte.">
  <meta name="theme-color" content="#0b3d2e">
  <title>GIS Map | Southern Leyte Soil Information System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="../src/css/style.css?v=<?= filemtime(__DIR__ . '/../src/css/style.css') ?>">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="../src/css/gis.css">
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    window.SBCIS_BOREHOLES = <?= $mapJson ?: '[]' ?>;
    window.SBCIS_RECORDS_AVAILABLE = <?= $recordsAvailable ? 'true' : 'false' ?>;
  </script>
  <script defer src="../src/js/gis.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script defer src="../src/js/toast.js"></script>
  <script defer src="../src/js/login-modal.js?v=<?= filemtime(__DIR__ . '/../src/js/login-modal.js') ?>"></script>
</head>
<body class="gis-page">
  <a class="gis-skip" href="#gisMap">Skip to map</a>

  <header class="site-header" id="top">
    <nav class="navbar container" aria-label="Primary navigation">
      <a href="../index.php#home" class="brand">
        <span class="brand-mark">
          <img src="../src/images/logo.png" alt="Southern Leyte Soil Information System logo">
        </span>
        <span class="brand-copy">
          <strong>SOUTHERN LEYTE</strong>
          <small>SOIL INFORMATION SYSTEM</small>
        </span>
      </a>

      <button class="nav-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="gisNavigation">
        <i class="fa-solid fa-bars"></i>
      </button>

      <div class="nav-panel" id="gisNavigation">
        <ul class="nav-links">
          <li><a href="../index.php#home">Home</a></li>
          <li><a href="../index.php#soil-data">Soil Data</a></li>
          <li><a href="../index.php#workflow">How It Works</a></li>
          <li><a href="../index.php#about">About</a></li>
          <li><a href="../index.php#contact">Contact</a></li>
          <li><a class="active" href="gis.php" aria-current="page">GIS Map</a></li>
        </ul>

        <div class="nav-actions">
          <button class="icon-btn search-trigger" type="button" aria-label="Search locations">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
          <a href="#" id="openLogin" class="login-btn">
            <i class="fa-solid fa-user-shield"></i>
            Login
          </a>
        </div>
      </div>
    </nav>
  </header>

  <main class="gis-main">
    <?php require __DIR__ . '/partials/map_explorer.php'; ?>
    <noscript>Enable JavaScript to explore municipalities and barangays on the map.</noscript>
  </main>

  <div class="login-modal" id="loginModal" aria-hidden="true">
    <div class="login-modal-overlay" id="loginOverlay"></div>

    <div class="login-modal-card" role="dialog" aria-modal="true" aria-labelledby="loginTitle">
      <button type="button" class="modal-close" id="closeLogin" aria-label="Close login">
        <i class="fa-solid fa-xmark"></i>
      </button>

      <div class="login-form-wrapper">
        <div class="modal-logo">
          <div class="modal-logo-mark">
            <img src="../src/images/logo.png" alt="Southern Leyte Soil Information System logo">
          </div>

          <div class="modal-logo-copy">
            <strong>SOUTHERN LEYTE</strong>
            <span>SOIL INFORMATION SYSTEM</span>
          </div>
        </div>

        <div class="form-header">
          <h2 id="loginTitle">Sign in</h2>
        </div>

        <p class="form-description">Enter your account credentials to continue.</p>

        <form action="../app/Controllers/login_process.php" method="POST" class="login-form">
          <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrapper">
              <i class="fa-regular fa-envelope"></i>
              <input type="email" id="email" name="email" placeholder="Enter your email address" autocomplete="email" required>
            </div>
          </div>

          <div class="form-group">
            <div class="label-row">
              <label for="password">Password</label>
              <a href="#" class="forgot-link" id="forgotPassword">Forgot password?</a>
            </div>

            <div class="input-wrapper">
              <i class="fa-solid fa-lock"></i>
              <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
              <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="form-options">
            <label class="remember-me">
              <input type="checkbox" name="remember" value="1">
              <span class="custom-checkbox"></span>
              <span>Remember me</span>
            </label>
          </div>

          <button type="submit" class="login-submit">
            <span>Sign In</span>
            <i class="fa-solid fa-arrow-right"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
