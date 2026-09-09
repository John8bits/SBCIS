<!DOCTYPE html>
<html lang="en">

<head>
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
  <link rel="stylesheet" href="src/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

          <li>
            <a class="active" href="#home">Home</a>
          </li>

          <li>
            <a href="gis.php">GIS Map</a>
          </li>

          <li>
            <a href="#soil-data">Soil Data</a>
          </li>

          <li>
            <a href="#about">About</a>
          </li>

          <li>
            <a href="#workflow">How It Works</a>
          </li>

          <li>
            <a href="#contact">Contact</a>
          </li>

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

            <a href="gis.php" class="btn btn-primary">

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
                  GIS DATA OVERVIEW
                </strong>

              </div>

            </div>

            <div class="mini-map">

              <img src="src/images/sample_map.png" alt="Preview of Southern Leyte GIS soil map">

              <div class="mini-map-overlay">

                <div class="map-marker marker-1">
                  <span></span>
                </div>

                <div class="map-marker marker-2">
                  <span></span>
                </div>

                <div class="map-marker marker-3">
                  <span></span>
                </div>

                <div class="map-marker marker-4">
                  <span></span>
                </div>

              </div>

              <div class="mini-map-label">
                <i class="fa-solid fa-location-dot"></i>
                Soil Data Locations
              </div>

            </div>

          </div>

        </div>

      </div>

    </section>

    <section class="section records-section" id="soil-data">

      <div class="container">

        <div class="section-heading-row reveal">

          <div>

            <p class="section-kicker">
              <i class="fa-regular fa-file-lines"></i>
              SOIL DATA RECORDS
            </p>

            <h2>
              Explore Soil Data Across Southern Leyte
            </h2>

            <p class="section-description">
              Search, filter, and explore available soil
              investigation locations by municipality,
              soil type, and bearing capacity.
            </p>

          </div>

          <a href="gis.php" class="text-link">
            View on Map
            <i class="fa-solid fa-arrow-right"></i>
          </a>

        </div>

        <div class="records-layout">

          <div class="records-table-card reveal">

            <div class="records-toolbar">

              <div class="records-search">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input type="search" id="recordsSearch" placeholder="Search soil records...">

              </div>

            </div>

            <div class="table-header">

              <div>

                <strong>
                  Recent Records
                </strong>

              </div>

            </div>

            <div class="table-wrapper">

              <table>

                <thead>

                  <tr>

                    <th>Location</th>

                    <th>Municipality</th>

                    <th>Test</th>

                    <th>Bearing Capacity</th>

                    <th>Date</th>

                  </tr>

                </thead>

                <tbody>

                  <tr>

                    <td>
                      <div class="location-cell">

                        <span class="table-icon">
                          <i class="fa-solid fa-location-dot"></i>
                        </span>

                        <strong>
                          Luyang
                        </strong>

                      </div>
                    </td>

                    <td>Sogod</td>

                    <td>
                      <span class="test-badge">
                        SPT
                      </span>
                    </td>

                    <td>
                      <strong class="capacity high-text">
                        250 kPa
                      </strong>
                    </td>

                    <td>Jun 20, 2024</td>

                  </tr>

                  <tr>

                    <td>
                      <div class="location-cell">

                        <span class="table-icon">
                          <i class="fa-solid fa-location-dot"></i>
                        </span>

                        <strong>
                          Guindapunan
                        </strong>

                      </div>
                    </td>

                    <td>Maasin City</td>

                    <td>
                      <span class="test-badge">
                        SPT
                      </span>
                    </td>

                    <td>
                      <strong class="capacity medium-text">
                        180 kPa
                      </strong>
                    </td>

                    <td>Jun 18, 2024</td>

                  </tr>

                  <tr>

                    <td>
                      <div class="location-cell">

                        <span class="table-icon">
                          <i class="fa-solid fa-location-dot"></i>
                        </span>

                        <strong>
                          An-per
                        </strong>

                      </div>
                    </td>

                    <td>San Juan</td>

                    <td>
                      <span class="test-badge">
                        SPT
                      </span>
                    </td>

                    <td>
                      <strong class="capacity medium-text">
                        120 kPa
                      </strong>
                    </td>

                    <td>Jun 15, 2024</td>

                  </tr>

                  <tr>

                    <td>
                      <div class="location-cell">

                        <span class="table-icon">
                          <i class="fa-solid fa-location-dot"></i>
                        </span>

                        <strong>
                          Hibaga-an
                        </strong>

                      </div>
                    </td>

                    <td>Hinunangan</td>

                    <td>
                      <span class="test-badge">
                        SPT
                      </span>
                    </td>

                    <td>
                      <strong class="capacity high-text">
                        210 kPa
                      </strong>
                    </td>

                    <td>Jun 10, 2024</td>

                  </tr>

                  <tr>

                    <td>
                      <div class="location-cell">

                        <span class="table-icon">
                          <i class="fa-solid fa-location-dot"></i>
                        </span>

                        <strong>
                          San Roque
                        </strong>

                      </div>
                    </td>

                    <td>Macrohon</td>

                    <td>
                      <span class="test-badge">
                        SPT
                      </span>
                    </td>

                    <td>
                      <strong class="capacity low-text">
                        95 kPa
                      </strong>
                    </td>

                    <td>Jun 05, 2024</td>

                  </tr>

                </tbody>

              </table>

            </div>

          </div>

          <div class="data-summary-card reveal">

            <div class="data-summary-top">

              <span class="summary-icon">
                <i class="fa-solid fa-chart-simple"></i>
              </span>

              <span>
                DATA OVERVIEW
              </span>

            </div>

            <h3>
              Soil parameters
              prepared for GIS visualization.
            </h3>

            <p>
              Each location can contain multiple
              investigation records and associated
              geographic information.
            </p>

            <div class="parameter-list">

              <div>
                <span>
                  <i class="fa-solid fa-location-dot"></i>
                  Coordinates
                </span>

                <strong>
                  Lat / Long
                </strong>
              </div>

              <div>
                <span>
                  <i class="fa-solid fa-mountain"></i>
                  Soil Type
                </span>

                <strong>
                  Recorded
                </strong>
              </div>

              <div>
                <span>
                  <i class="fa-solid fa-weight-hanging"></i>
                  Bearing Capacity
                </span>

                <strong>
                  kPa
                </strong>
              </div>

              <div>
                <span>
                  <i class="fa-solid fa-calendar"></i>
                  Test Date
                </span>

                <strong>
                  History
                </strong>
              </div>

              <div>
                <span>
                  <i class="fa-solid fa-file-lines"></i>
                  Data Source
                </span>

                <strong>
                  Reference
                </strong>
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
            Built for People Who Need Soil Information
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

    <section class="section workflow-section" id="workflow">

      <div class="container">

        <div class="section-heading centered reveal">

          <p class="section-kicker">
            <i class="fa-solid fa-diagram-project"></i>
            SYSTEM FLOW
          </p>

          <h2>
            From Soil Data Collection to Public Access
          </h2>

          <p>
            The system organizes collected soil investigation
            information and makes it easier to locate and view
            through a GIS-based interface.
          </p>

        </div>

        <div class="workflow-grid">

          <article class="workflow-card workflow-green reveal">

            <div class="workflow-number">
              01
            </div>

            <div class="workflow-icon">
              <i class="fa-solid fa-database"></i>
            </div>

            <span class="workflow-label">
              DATA COLLECTION
            </span>

            <h3>
              Collect Soil Data
            </h3>

            <p>
              Borehole locations, SPT results, soil type,
              bearing capacity, and other investigation
              information are gathered from available sources.
            </p>

          </article>

          <div class="workflow-arrow">
            <i class="fa-solid fa-arrow-right"></i>
          </div>

          <article class="workflow-card workflow-blue reveal">

            <div class="workflow-number">
              02
            </div>

            <div class="workflow-icon">
              <i class="fa-solid fa-map"></i>
            </div>

            <span class="workflow-label">
              GIS PREPARATION
            </span>

            <h3>
              Map with QGIS
            </h3>

            <p>
              Soil locations are plotted using coordinates
              and prepared as GIS layers for integration with
              the web-based map.
            </p>

          </article>

          <div class="workflow-arrow">
            <i class="fa-solid fa-arrow-right"></i>
          </div>

          <article class="workflow-card workflow-brown reveal">

            <div class="workflow-number">
              03
            </div>

            <div class="workflow-icon">
              <i class="fa-solid fa-gears"></i>
            </div>

            <span class="workflow-label">
              DATA MANAGEMENT
            </span>

            <h3>
              Manage Through Admin Panel
            </h3>

            <p>
              Authorized administrators encode, update,
              organize, and maintain soil investigation
              records in the system.
            </p>

          </article>

          <div class="workflow-arrow">
            <i class="fa-solid fa-arrow-right"></i>
          </div>

          <article class="workflow-card workflow-purple reveal">

            <div class="workflow-number">
              04
            </div>

            <div class="workflow-icon">
              <i class="fa-solid fa-globe"></i>
            </div>

            <span class="workflow-label">
              PUBLIC ACCESS
            </span>

            <h3>
              Search and View
            </h3>

            <p>
              Engineers, LGUs, researchers, students, and
              other users can search locations and view
              available soil information.
            </p>

          </article>

        </div>

      </div>

    </section>

    <section class="stats-section">

      <div class="container">

        <div class="stats-grid">

          <article class="stat-card reveal">

            <div class="stat-icon green">
              <i class="fa-solid fa-map-location-dot"></i>
            </div>

            <div>

              <strong class="counter" data-target="18">
                0
              </strong>

              <h3>Municipalities</h3>

              <p>Across Southern Leyte</p>

            </div>

          </article>

          <article class="stat-card reveal">

            <div class="stat-icon brown">
              <i class="fa-solid fa-location-crosshairs"></i>
            </div>

            <div>

              <strong class="counter" data-target="500">
                0
              </strong>

              <h3>Barangay</h3>

              <p>GIS-mapped soil locations</p>

            </div>

          </article>

          <article class="stat-card reveal">

            <div class="stat-icon blue">
              <i class="fa-solid fa-flask"></i>
            </div>

            <div>

              <strong class="counter" data-target="98">
                0
              </strong>

              <h3>Soil Test Records</h3>

              <p>SPT and other investigations</p>

            </div>

          </article>

          <article class="stat-card reveal">

            <div class="stat-icon olive">
              <i class="fa-solid fa-layer-group"></i>
            </div>

            <div>

              <strong class="stat-word">
                GIS
              </strong>

              <h3>Data Integration</h3>

              <p>QGIS and web mapping</p>

            </div>

          </article>

        </div>

      </div>

    </section>

  </main>

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

  <footer class="footer" id="contact">

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

        <a href="gis.php">
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

        <a href="gis.php">
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

        <form action="login_process.php" method="POST" class="login-form">

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

          Swal.fire({
            icon: "info",
            title: "Forgot Password?",
            text: "Please contact the system administrator to reset your password.",
            confirmButtonText: "Okay",
            confirmButtonColor: "#0b3d2e"
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



    if (
      loginStatus === "failed" ||
      loginStatus === "empty" ||
      loginStatus === "invalid"
    ) {

      let errorMessage =
        "Invalid email or password.";

      if (loginStatus === "empty") {

        errorMessage =
          "Please enter your email and password.";

      }

      if (loginStatus === "invalid") {

        errorMessage =
          "Please enter a valid email address.";

      }

      openLoginModal();


      setTimeout(function () {

        Swal.fire({

          icon: "error",

          title: "Login Failed",

          text: errorMessage,

          confirmButtonText: "Try Again",

          confirmButtonColor: "#0b3d2e",

          allowOutsideClick: false,

          allowEscapeKey: false,

          customClass: {
            container: "swal-login-alert"
          }

        });

      }, 350);

    }



    if (loginStatus === "error") {

      Swal.fire({

        icon: "error",

        title: "Something Went Wrong",

        text: "We could not process your login. Please try again later.",

        confirmButtonText: "Okay",

        confirmButtonColor: "#0b3d2e",

        allowOutsideClick: false

      });

    }

    if (logoutStatus === "success") {

      Swal.fire({

        icon: "success",

        title: "Logged Out Successfully",

        text: "You have been safely logged out of the administrator account.",

        confirmButtonText: "Okay",

        confirmButtonColor: "#0b3d2e",

        timer: 3000,

        timerProgressBar: true,

        allowOutsideClick: false

      });

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

  <script src="src/js/script.js"></script>

</body>

</html>