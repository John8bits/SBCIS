<?php

session_start();

// prevent cache
header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);
  
header(
    'Cache-Control: post-check=0, pre-check=0',
    false
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);


/*
|--------------------------------------------------------------------------
| IF ADMIN IS STILL LOGGED IN
|--------------------------------------------------------------------------
|
| Do not allow the admin to return to the public landing page.
| They must logout first.
|
*/

if (
    isset($_SESSION['admin_logged_in']) &&
    $_SESSION['admin_logged_in'] === true
) {

    header(
        'Location: dashboard/admin/admin_dashboard.php'
    );

    exit;

}


$home = require __DIR__ . '/app/Controllers/home.php';
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script defer src="src/js/hero-map.js?v=<?= filemtime(__DIR__ . '/src/js/hero-map.js') ?>"></script>
  <link rel="icon" type="image/png" href="src/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description"
    content="Southern Leyte Soil Bearing Capacity Information System - A GIS-based platform for accessing soil investigation and soil bearing capacity information in Southern Leyte.">
  <meta name="theme-color" content="#0b3d2e">
  <title>Southern Leyte Soil Bearing Capacity Information System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="src/css/style.css?v=<?= filemtime(__DIR__ . '/src/css/style.css') ?>">

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="src/js/toast.js"></script>


</head>

<body>

  <header class="site-header" id="top">

    <nav class="navbar container" aria-label="Primary navigation">

      <a href="#home" class="brand">

        <span class="brand-mark">
          <img src="src/images/logo.png" alt="Southern Leyte Soil Information System logo">
        </span>

        <span class="brand-copy">
          <strong>SOUTHERN LEYTE</strong>
          <small>SOIL INFORMATION SYSTEM</small>
        </span>

      </a>

      <button class="nav-toggle" type="button" aria-label="Open navigation" aria-expanded="false">

        <i class="fa-solid fa-bars"></i>

      </button>

      <div class="nav-panel">

        <ul class="nav-links">
          <li><a class="active" href="#home">Home</a></li>
          <li><a href="#soil-data">Soil Data</a></li>
          <li><a href="#workflow">How It Works</a></li>
          <li><a href="#about">About</a></li>
          <li><a href="#contact">Contact</a></li>
          <li><a href="views/gis.php">GIS Map</a></li>
        </ul>

        <div class="nav-actions">

          <button class="icon-btn search-trigger" type="button" aria-label="Search">

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

  <main>
<section class="hero" id="home">

      <div class="hero-overlay"></div>

      <div class="hero-pattern"></div>

      <div class="container hero-content">

        <div class="hero-copy reveal">

          <p class="eyebrow">
            DATA FOR A STRONGER SOUTHERN LEYTE
          </p>

          <h1>
            Soil Bearing Capacity
            <span>Information System</span>
          </h1>

          <p class="hero-text">

            A GIS-based platform for accessible soil investigation data to support safe,
            sustainable, and cost-effective construction planning in
            Southern Leyte.

          </p>

          <div class="hero-actions">

            <a href="views/gis.php" class="btn btn-primary">

              <i class="fa-regular fa-map"></i>

              Explore GIS Map

            </a>

            <a href="#soil-data" class="btn btn-outline-light">

              <i class="fa-regular fa-file-lines"></i>

              View Soil Records

            </a>

          </div>

        </div>

        <div class="hero-visual reveal">

          <div class="hero-map-card">

            <div class="mini-map-header">

              <div>

                <span class="mini-label">
                  SOUTHERN LEYTE
                </span>

                <strong>
                  Explore Southern Leyte
                </strong>

              </div>

            </div>

            <div id="heroMap" class="mini-map" role="region" aria-label="Southern Leyte map preview with zoom controls"></div>
            <div class="hero-map-actions">
              <button id="heroMapReset" type="button" disabled>Reset view</button>
              <a href="views/gis.php">Explore full map <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
            </div>
            <p id="heroMapStatus" class="hero-map-note" role="status">Loading map...</p>


          </div>

        </div>

      </div>

    </section>

<section class="section records-section" id="soil-data" aria-labelledby="soil-data-title">
      <div class="container">
        <div class="section-heading centered reveal">
          <p class="section-kicker">MAP COVERAGE &amp; SOIL RECORDS</p>
          <h2 id="soil-data-title">Soil Data</h2>
          <p>Boundary coverage and available soil records. Mapped areas do not indicate soil test locations.</p>
        </div>
        <div class="stats-grid soil-coverage">
          <?php foreach ([
              ['municipalities', 'Municipalities / Cities', 'Areas in the boundary map', 'fa-map-location-dot'],
              ['barangays', 'Barangays', 'Areas in the boundary map', 'fa-location-crosshairs'],
              ['boreholes', 'Borehole Locations', 'Locations saved in soil records', 'fa-location-dot'],
              ['soilLayers', 'Soil Layer Records', 'Recorded layers, not individual tests', 'fa-layer-group'],
          ] as [$key, $label, $description, $icon]): ?>
            <article class="stat-card reveal">
              <div class="stat-icon green"><i class="fa-solid <?= $escape($icon) ?>" aria-hidden="true"></i></div>
              <div>
                <strong<?= $home[$key] === null ? ' class="stat-unavailable"' : '' ?>><?= $home[$key] === null ? 'Unavailable' : number_format($home[$key]) ?></strong>
                <h3><?= $escape($label) ?></h3>
                <p><?= $escape($description) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <?php if (!$home['databaseAvailable']): ?>
          <div class="soil-empty-state reveal">
            <span class="soil-empty-icon" aria-hidden="true"><i class="fa-regular fa-folder-open"></i></span>
            <h3>Soil data is temporarily unavailable</h3>
            <p>Please try again later. You can still explore the location boundaries.</p>
            <a href="views/gis.php" class="text-link">Explore the map <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
          </div>
        <?php elseif (!$home['records']): ?>
          <div class="soil-empty-state reveal">
            <span class="soil-empty-icon" aria-hidden="true"><i class="fa-regular fa-folder-open"></i></span>
            <h3>No soil data yet</h3>
            <p>Soil investigation records will appear here once they are available.</p>
            <a href="views/gis.php" class="text-link">Explore location boundaries <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
          </div>
        <?php else: ?>
          <div class="records-table-card reveal">
            <div class="table-header"><strong>Latest soil layer records</strong><span>Showing <?= count($home['records']) ?> of <?= number_format($home['soilLayers']) ?></span></div>
            <div class="table-wrapper">
              <table>
                <caption class="soil-table-caption">Latest recorded layers by borehole and location</caption>
                <thead><tr><th scope="col">Borehole</th><th scope="col">Municipality / City</th><th scope="col">Barangay</th><th scope="col">Layer</th><th scope="col">Soil Type</th><th scope="col">Bearing Capacity</th></tr></thead>
                <tbody>
                  <?php foreach ($home['records'] as $record): ?>
                    <tr>
                      <td><?= $escape($record['borehole_code']) ?></td>
                      <td><?= $escape($record['municipality_name'] ?? 'Not recorded') ?></td>
                      <td><?= $escape($record['barangay_name'] ?? 'Not recorded') ?></td>
                      <td><?= $escape($record['layer_number']) ?></td>
                      <td><?= $escape($record['soil_type']) ?></td>
                      <td><?= $record['bearing_capacity_kpa'] === null ? 'Not recorded' : $escape($record['bearing_capacity_kpa']) . ' kPa' ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>

<section class="section workflow-section" id="workflow">
      <div class="container">
        <div class="section-heading centered reveal"><p class="section-kicker">HOW IT WORKS</p><h2>Explore a Location</h2><p>Find a municipality or barangay on the map, then check for available soil records.</p></div>
        <div class="workflow-grid">
          <article class="workflow-card workflow-green reveal"><div class="workflow-number">01</div><div class="workflow-icon"><i class="fa-solid fa-map" aria-hidden="true"></i></div><span class="workflow-label">OPEN THE MAP</span><h3>Explore Southern Leyte</h3><p>Open the GIS map to view the province and municipality boundaries.</p></article>
          <div class="workflow-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
          <article class="workflow-card workflow-green reveal"><div class="workflow-number">02</div><div class="workflow-icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></div><span class="workflow-label">CHOOSE A PLACE</span><h3>Select a Municipality</h3><p>Choose a municipality or city to see its barangay boundaries.</p></article>
          <div class="workflow-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
          <article class="workflow-card workflow-green reveal"><div class="workflow-number">03</div><div class="workflow-icon"><i class="fa-solid fa-magnifying-glass-location" aria-hidden="true"></i></div><span class="workflow-label">LOOK CLOSER</span><h3>Explore a Barangay</h3><p>Click a barangay to zoom in, or search for a place on the full map.</p></article>
          <div class="workflow-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
          <article class="workflow-card workflow-green reveal"><div class="workflow-number">04</div><div class="workflow-icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></div><span class="workflow-label">CHECK AVAILABILITY</span><h3>View Soil Records</h3><p>Check the Soil Data section for saved records. Areas on the boundary map may not have soil records.</p></article>
        </div>
      </div>
    </section>

<section class="section about-section" id="about">

      <div class="container">

        <div class="about-layout">

          <div class="about-image reveal">

            <img src="src/images/soil_data.jpg" alt="Southern Leyte landscape">

            <div class="about-image-caption">

              <i class="fa-solid fa-location-dot"></i>

              Southern Leyte, Philippines

            </div>

          </div>

          <div class="about-copy reveal">

            <p class="section-kicker">
              ABOUT THE SYSTEM
            </p>

            <h2>
              Better soil data for better decisions.
            </h2>

            <p>
              The Southern Leyte Soil Bearing Capacity
              Information System is designed to organize
              soil investigation information and provide
              an accessible GIS-based platform for viewing
              available data.
            </p>

            <p>
              By combining geographic information,
              soil investigation records, and a simple
              web interface, the system aims to support
              preliminary planning, research, and
              informed decision-making.
            </p>

            <div class="about-points">

              <div>

                <i class="fa-solid fa-check"></i>

                <span>
                  Organized soil information
                </span>

              </div>

              <div>

                <i class="fa-solid fa-check"></i>

                <span>
                  GIS-based location visualization
                </span>

              </div>

              <div>

                <i class="fa-solid fa-check"></i>

                <span>
                  Accessible public reference
                </span>

              </div>

            </div>

          </div>

        </div>

      </div>

    </section>

<section class="section users-section">

      <div class="container">

        <div class="section-heading centered reveal">

          <p class="section-kicker">
            <i class="fa-solid fa-users"></i>
            TARGET USERS
          </p>

          <h2>
            Who Can Use This Information
          </h2>

          <p>
            The system provides an accessible reference
            platform for different users involved in
            planning, research, and development.
          </p>

        </div>

        <div class="users-grid">

          <article class="user-card reveal">

            <div class="user-icon">
              <i class="fa-solid fa-helmet-safety"></i>
            </div>

            <h3>
              Civil Engineers
            </h3>

            <p>
              Access available soil information during
              preliminary planning and site assessment.
            </p>

          </article>

          <article class="user-card reveal">

            <div class="user-icon">
              <i class="fa-solid fa-building-columns"></i>
            </div>

            <h3>
              LGUs
            </h3>

            <p>
              Support local planning and development
              decisions using organized geographic data.
            </p>

          </article>

          <article class="user-card reveal">

            <div class="user-icon">
              <i class="fa-solid fa-book-open"></i>
            </div>

            <h3>
              Researchers
            </h3>

            <p>
              Locate and reference available soil
              investigation information for research.
            </p>

          </article>

          <article class="user-card reveal">

            <div class="user-icon">
              <i class="fa-solid fa-person-chalkboard"></i>
            </div>

            <h3>
              Students
            </h3>

            <p>
              Explore GIS and soil data as an educational
              and research reference.
            </p>

          </article>

          <article class="user-card reveal">

            <div class="user-icon">
              <i class="fa-solid fa-city"></i>
            </div>

            <h3>
              Planners
            </h3>

            <p>
              Use available information as a reference
              for preliminary land and infrastructure planning.
            </p>

          </article>

        </div>

      </div>

    </section>

<section class="section contact-section" id="contact">

    <div class="container">

      <div class="section-heading centered reveal">

        <p class="section-kicker">
          <i class="fa-solid fa-envelope"></i>
          CONTACT
        </p>

        <h2>
          Need More Information?
        </h2>

        <p>
          For questions regarding soil investigation
          records, GIS data, or system administration,
          contact the responsible system administrator.
        </p>

      </div>

      <div class="contact-grid">

        <div class="contact-card reveal">

          <div class="contact-icon">
            <i class="fa-solid fa-envelope"></i>
          </div>

          <div>
            <span>Email</span>
            <strong></strong>
          </div>

        </div>

        <div class="contact-card reveal">

          <div class="contact-icon">
            <i class="fa-solid fa-location-dot"></i>
          </div>

          <div>
            <span>Location</span>
            <strong>
              Southern Leyte, Philippines
            </strong>
          </div>

        </div>

        <div class="contact-card reveal">

          <div class="contact-icon">
            <i class="fa-solid fa-user-shield"></i>
          </div>

          <div>
            <span>Administration</span>
            <strong>
              Authorized System Administrator
            </strong>
          </div>

        </div>

      </div>

    </div>

  </section>
</main>

<footer class="footer">

    <div class="container footer-grid">

      <div class="footer-brand">

        <div class="footer-brand-row">

          <span class="footer-logo">

            <img src="src/images/logo.png" alt="Southern Leyte logo">

          </span>

          <div>

            <strong style="font-size: 17px;">SOUTHERN LEYTE</strong>

            <span style="font-size: 10px;">
              Soil Bearing Capacity Information System
            </span>

            <small>
              For safer and sustainable communities
            </small>

          </div>

        </div>

        <p class="footer-description" style="font-size: 12px;">
          A GIS-based platform for organizing and accessing
          soil investigation information across Southern Leyte.
        </p>

      </div>

      <div class="footer-column">

        <h3>Resources</h3>

        <a href="views/gis.php">
          GIS Map
        </a>

        <a href="#soil-data">
          Soil Records
        </a>

        <a href="#workflow">
          System Flow
        </a>

        <a href="#about">
          About the System
        </a>

      </div>

      <div class="footer-column">

        <h3>System</h3>

        <a href="#home">
          Home
        </a>

        <a href="views/gis.php">
          Explore Map
        </a>

        <a href="#soil-data">
          Soil Data
        </a>

        <a href="#contact">
          Contact
        </a>

        <a href="#" id="footerLogin">
          Sign In
        </a>

      </div>

      <div class="footer-column footer-contact">

        <h3>
          Contact
        </h3>

        <p>

          <i class="fa-regular fa-envelope"></i>
        </p>

        <p>

          <i class="fa-solid fa-phone"></i>

        </p>

        <p>

          <i class="fa-solid fa-location-dot"></i>

        </p>

        <div class="footer-social">

          <a href="#" aria-label="Facebook">
            <i class="fa-brands fa-facebook-f"></i>
          </a>

          <a href="#" aria-label="YouTube">
            <i class="fa-brands fa-youtube"></i>
          </a>

          <a href="mailto:info@slsoil.gov.ph" aria-label="Email">
            <i class="fa-solid fa-envelope"></i>
          </a>

        </div>

      </div>

    </div>

    <div class="footer-bottom">

      <div class="container">

        <span>
          © 2026 Southern Leyte Soil Bearing Capacity Information System
        </span>

        <span>
          All Rights Reserved.
        </span>

      </div>

    </div>

  </footer>

  <div class="search-modal" aria-hidden="true">

    <div class="search-backdrop"></div>

    <div class="search-dialog" role="dialog" aria-modal="true" aria-labelledby="search-title">

      <button class="search-close" type="button" aria-label="Close search">

        <i class="fa-solid fa-xmark"></i>

      </button>

      <p class="section-kicker">
        QUICK SEARCH
      </p>

      <h2 id="search-title">
        Find Soil Information
      </h2>

      <p class="search-description">
        Search for a municipality, barangay, soil test,
        or other available keyword.
      </p>

      <form class="search-form">

        <label for="site-search">
          Search keyword
        </label>

        <div class="search-input-row">

          <div class="search-input-wrapper">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input id="site-search" type="search" placeholder="e.g. Sogod, Luyang, SPT" autocomplete="off">

          </div>

          <button class="btn btn-primary" type="submit">

            Search

          </button>

        </div>

        <p class="search-result" aria-live="polite">
        </p>

      </form>

    </div>

  </div>

  <div class="login-modal" id="loginModal" aria-hidden="true">

    <div class="login-modal-overlay" id="loginOverlay"></div>

    <div class="login-modal-card" role="dialog" aria-modal="true" aria-labelledby="loginTitle">

      <button type="button" class="modal-close" id="closeLogin" aria-label="Close login">
        <i class="fa-solid fa-xmark"></i>
      </button>

      <div class="login-form-wrapper">

        <div class="modal-logo">

          <div class="modal-logo-mark">

            <img src="src/images/logo.png" alt="Southern Leyte Soil Information System logo">

          </div>

          <div class="modal-logo-copy">

            <strong>SOUTHERN LEYTE</strong>

            <span>
              SOIL INFORMATION SYSTEM
            </span>

          </div>

        </div>

        <div class="form-header">
          <h2 id="loginTitle">Sign in</h2>
        </div>

        <p class="form-description">
          Enter your account credentials to continue.
        </p>

        <form action="app/Controllers/login_process.php" method="POST" class="login-form">

          <div class="form-group">

            <label for="email">
              Email Address
            </label>

            <div class="input-wrapper">

              <i class="fa-regular fa-envelope"></i>

              <input type="email" id="email" name="email" placeholder="Enter your email address" autocomplete="email"
                required>

            </div>

          </div>

          <div class="form-group">

            <div class="label-row">

              <label for="password">
                Password
              </label>

              <a href="#" class="forgot-link" id="forgotPassword">
                Forgot password?
              </a>

            </div>

            <div class="input-wrapper">

              <i class="fa-solid fa-lock"></i>

              <input type="password" id="password" name="password" placeholder="Enter your password"
                autocomplete="current-password" required>

              <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password">

                <i class="fa-regular fa-eye"></i>

              </button>

            </div>

          </div>

          <div class="form-options">

            <label class="remember-me">

              <input type="checkbox" name="remember" value="1">

              <span class="custom-checkbox"></span>

              <span>
                Remember me
              </span>

            </label>

          </div>

          <button type="submit" class="login-submit">

            <span>
              Sign In
            </span>

            <i class="fa-solid fa-arrow-right"></i>

          </button>

          <span style="color: #71807a; font-family: DM Sans, sans-serif; font-size: 14px; margin-top: 17px; text-align: center;">Authorized Personel Only!</span>
        </form>

      </div>

    </div>

  </div>


  <script>

    const loginModal = document.getElementById("loginModal");
    const openLogin = document.getElementById("openLogin");
    const closeLogin = document.getElementById("closeLogin");
    const loginOverlay = document.getElementById("loginOverlay");
    const passwordInput = document.getElementById("password");
    const passwordToggle = document.getElementById("passwordToggle");


    function openLoginModal() {

      if (!loginModal) return;

      loginModal.classList.add("active");

      loginModal.setAttribute(
        "aria-hidden",
        "false"
      );

      document.body.classList.add(
        "login-modal-open"
      );

      setTimeout(function () {

        const email =
          document.getElementById("email");

        if (email) {


          email.focus();

        }

      }, 300);

    }


    function closeLoginModal() {

      if (!loginModal) return;

      loginModal.classList.remove("active");

      loginModal.setAttribute(
        "aria-hidden",
        "true"
      );

      document.body.classList.remove(
        "login-modal-open"
      );

    }

    if (openLogin) {

      openLogin.addEventListener(
        "click",
        function (event) {

          event.preventDefault();

          openLoginModal();

        }
      );

    }

    if (closeLogin) {

      closeLogin.addEventListener(
        "click",
        function () {

          closeLoginModal();

        }
      );

    }

    if (loginOverlay) {

      loginOverlay.addEventListener(
        "click",
        function () {

          closeLoginModal();

        }
      );

    }
    document.addEventListener(
      "keydown",
      function (event) {

        if (
          event.key === "Escape" &&
          loginModal &&
          loginModal.classList.contains("active")
        ) {

          closeLoginModal();

        }

      }
    );

    if (
      passwordToggle &&
      passwordInput
    ) {

      passwordToggle.addEventListener(
        "click",
        function () {

          const isPassword =
            passwordInput.type === "password";

          passwordInput.type =
            isPassword
              ? "text"
              : "password";

          this.innerHTML =
            isPassword
              ? '<i class="fa-regular fa-eye-slash"></i>'
              : '<i class="fa-regular fa-eye"></i>';

          this.setAttribute(
            "aria-label",
            isPassword
              ? "Hide password"
              : "Show password"
          );

        }
      );

    }

    const forgotPassword =
      document.getElementById(
        "forgotPassword"
      );

    if (forgotPassword) {

      forgotPassword.addEventListener(
        "click",
        function (event) {

          event.preventDefault();

          AppToast.fire({
            icon: 'info',
            title: 'Contact your admin to reset your password.'
          });

        }
      );

    }


    const footerLogin =
      document.getElementById("footerLogin");

    if (footerLogin) {

      footerLogin.addEventListener(
        "click",
        function (event) {

          event.preventDefault();

          openLoginModal();

        }
      );

    }

    const urlParams =
      new URLSearchParams(
        window.location.search
      );

    const loginStatus =
      urlParams.get("login");

    const logoutStatus =
      urlParams.get("logout");

  

    const loginMessages = {
      failed: 'Incorrect email or password.',
      empty: 'Enter your email and password.',
      invalid: 'Enter a valid email address.',
      error: 'Unable to sign in. Please try again.',
      required: 'Please sign in to continue.'
    };

    if (Object.hasOwn(loginMessages, loginStatus)) {
      openLoginModal();
      AppToast.fire({
        icon: loginStatus === 'required' ? 'info' : 'error',
        title: loginMessages[loginStatus]
      });
    }

    if (logoutStatus === 'success') {
      AppToast.fire({ icon: 'success', title: 'You are signed out.' });
    }

    if (
      loginStatus ||
      logoutStatus
    ) {

      window.history.replaceState(
        {},
        document.title,
        window.location.pathname
      );

    }

  </script>

  <script src="src/js/script.js?v=<?= filemtime(__DIR__ . '/src/js/script.js') ?>"></script>

</body>
</html>
