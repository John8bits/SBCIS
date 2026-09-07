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
  <link rel="stylesheet" href="css/style.css">
</head>

<body>

  <header class="site-header" id="top">

    <nav class="navbar container" aria-label="Primary navigation">

      <a href="#home" class="brand">

        <span class="brand-mark">
          <img src="images/logo.png" alt="Southern Leyte Soil Information System logo">
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
            <a href="#map">GIS Map</a>
          </li>

          <li>
            <a href="#soil-data">Soil Data</a>
          </li>

          <li>
            <a href="#about">About</a>
          </li>

          <li>
            <a href="#contact">Contact</a>
          </li>

          <li>
            <a href="#workflow">How It Works</a>
          </li>

        </ul>

        <div class="nav-actions">

          <button class="icon-btn search-trigger" type="button" aria-label="Search">

            <i class="fa-solid fa-magnifying-glass"></i>

          </button>

          <a href="login.php" class="login-btn">

            <i class="fa-solid fa-user-shield"></i>

            <span>Admin Login</span>

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

          <!-- <div class="hero-badge">

            <span class="status-dot"></span>

            GIS-BASED SOIL INFORMATION SYSTEM

          </div> -->

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

            <a href="#map" class="btn btn-primary">

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

              <!-- <span class="live-indicator">
                                <span></span>
                                SYSTEM PREVIEW
                            </span> -->

            </div>

            <div class="mini-map">

              <img src="images/sample_map.png" alt="Preview of Southern Leyte GIS soil map">

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

          <!-- <div class="hero-floating-card">

                        <div class="floating-icon">
                            <i class="fa-solid fa-database"></i>
                        </div>

                        <div>
                            <strong>Centralized Soil Data</strong>
                            <span>GIS-ready information</span>
                        </div>

                    </div> -->

        </div>

      </div>

    </section>

    <!-- qgis map -->
    <section class="section map-section" id="map">

      <div class="container">

        <div class="section-heading-row reveal">

          <div>

            <p class="section-kicker">
              <i class="fa-regular fa-map"></i>
              GIS VISUALIZATION
            </p>

            <h2>
              Explore Soil Information by Location
            </h2>

            <p class="section-description">
              A preview of the GIS map interface that will
              display soil investigation locations across
              Southern Leyte.
            </p>

          </div>

          <!-- <span class="development-badge">
            <i class="fa-solid fa-code"></i>
            GIS MODULE PREVIEW
          </span> -->

        </div>


        <div class="map-interface reveal">

          <div class="map-toolbar">

            <div class="map-search">

              <i class="fa-solid fa-magnifying-glass"></i>

              <input type="text" id="mapSearch" placeholder="Search municipality or barangay...">

            </div>


            <div class="map-filters">

              <select id="municipalityFilter">

                <option value="">
                  All Municipalities
                </option>

                <option value="sogod">
                  Sogod
                </option>

                <option value="maasin">
                  Maasin City
                </option>

                <option value="san-juan">
                  San Juan
                </option>

                <option value="hinunangan">
                  Hinunangan
                </option>

                <option value="macrohon">
                  Macrohon
                </option>

              </select>

              <select id="soilFilter">

                <option value="">
                  All Soil Type
                </option>

                <option value="clay">
                  Clay
                </option>

                <option value="gravelly_sand">
                  Gravelly Sand
                </option>

                <option value="sand">
                  Sand
                </option>

                <option value="sandy_claw">Sandy Clay</option>
                <option value="silty_sand">Silty Sand</option>

              </select>


              <select id="capacityFilter">

                <option value="">
                  All Bearing Capacity
                </option>

                <option value="very-high">
                  Very High — >300 kPa
                </option>

                <option value="high">
                  High — 200–300 kPa
                </option>

                <option value="medium">
                  Medium — 100–200 kPa
                </option>

                <option value="low">Low — 50–100 kPa</option>
                <option value="very-low">Very Low — 50 kPa </option>

              </select>

            </div>

          </div>


          <div class="map-body">

            <div class="map-view">

              <img src="images/sample_map.png" alt="Southern Leyte soil bearing capacity GIS map">

              <div class="map-overlay-gradient"></div>


              <!-- Prototype markers -->

              <button class="gis-marker high-marker" style="left: 36%; top: 32%;"
                data-location="Barangay Luyang — Sogod" aria-label="Luyang soil data">

                <span></span>

              </button>


              <button class="gis-marker medium-marker" style="left: 52%; top: 45%;"
                data-location="Barangay Guindapunan — Maasin City" aria-label="Guindapunan soil data">

                <span></span>

              </button>


              <button class="gis-marker low-marker" style="left: 62%; top: 58%;"
                data-location="Barangay An-per — San Juan" aria-label="An-per soil data">

                <span></span>

              </button>


              <button class="gis-marker high-marker" style="left: 45%; top: 67%;"
                data-location="Barangay Hibaga-an — Hinunangan" aria-label="Hibaga-an soil data">

                <span></span>

              </button>


              <button class="gis-marker very-low-marker" style="left: 71%; top: 38%;"
                data-location="Barangay San Roque — Macrohon" aria-label="San Roque soil data">

                <span></span>

              </button>


              <div class="map-location-toast" id="mapLocationToast">
                <i class="fa-solid fa-location-dot"></i>
                <span>Select a soil data marker</span>
              </div>

              <div class="map-attribution">
                GIS Map Preview • QGIS Data Preparation
              </div>


              <div class="map-controls">

                <button type="button" class="map-control" data-map-action="zoom-in" aria-label="Zoom in">

                  +
                </button>

                <button type="button" class="map-control" data-map-action="zoom-out" aria-label="Zoom out">

                  −
                </button>

                <button type="button" class="map-control" data-map-action="reset" aria-label="Reset map">

                  <i class="fa-solid fa-house"></i>

                </button>

              </div>


              <div class="map-legend">

                <strong>
                  Bearing Capacity
                </strong>

                <span>
                  <i class="legend-color very-high"></i>
                  &gt; 300 kPa
                </span>

                <span>
                  <i class="legend-color high"></i>
                  200 – 300 kPa
                </span>

                <span>
                  <i class="legend-color medium"></i>
                  100 – 200 kPa
                </span>

                <span>
                  <i class="legend-color low"></i>
                  50 – 100 kPa
                </span>

                <span>
                  <i class="legend-color very-low"></i>
                  &lt; 50 kPa
                </span>

              </div>

            </div>


            <aside class="map-information">

              <div class="map-info-header">

                <span class="map-info-icon">
                  <i class="fa-solid fa-circle-info"></i>
                </span>

                <div>

                  <span>
                    GIS INFORMATION
                  </span>

                  <h3>
                    Location Details
                  </h3>

                </div>

              </div>


              <div class="selected-location" id="selectedLocation">

                <div class="empty-location">

                  <i class="fa-solid fa-location-dot"></i>

                  <strong>
                    Select a marker
                  </strong>

                  <p>
                    Click a mapped soil location
                    to preview its information.
                  </p>

                </div>

              </div>


              <div class="map-data-note">

                <i class="fa-solid fa-circle-exclamation"></i>

                <p>
                  This map is currently a visual
                  prototype. The final implementation
                  will use QGIS-prepared GeoJSON data
                  with Leaflet.js.
                </p>

              </div>

            </aside>

          </div>

        </div>

      </div>

    </section>


    <!-- =========================================
             SOIL DATA
        ========================================== -->

    <section class="section records-section" id="soil-data">

      <div class="container">

        <div class="section-heading-row reveal">

          <div>

            <p class="section-kicker">
              <i class="fa-regular fa-file-lines"></i>
              SOIL DATA RECORDS
            </p>

            <h2>
              Available Soil Investigation Information
            </h2>

            <p class="section-description">
              Sample records showing how soil information
              can be organized and presented to users.
            </p>

          </div>

          <a href="#map" class="text-link">
            View on Map
            <i class="fa-solid fa-arrow-right"></i>
          </a>

        </div>


        <div class="records-layout">

          <div class="records-table-card reveal">

            <div class="table-header">

              <div>

                <span>
                  SOIL INVESTIGATION DATABASE
                </span>

                <strong>
                  Recent Records
                </strong>

              </div>

              <span class="record-count">
                5 Sample Records
              </span>

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


    <!-- =========================================
             FEATURES
        ========================================== -->

    <section class="section features-section">

      <div class="container">

        <div class="section-heading centered reveal">

          <p class="section-kicker">
            <i class="fa-solid fa-grid-2"></i>
            MAIN MODULES
          </p>

          <h2>
            Everything in One Soil Information Platform
          </h2>

          <p>
            The system is designed around the needs of
            users who need accessible and organized
            soil investigation information.
          </p>

        </div>


        <div class="features-grid">

          <article class="feature-card reveal">

            <div class="feature-icon green">
              <i class="fa-regular fa-map"></i>
            </div>

            <div>

              <span>
                01
              </span>

              <h3>
                GIS Map
              </h3>

              <p>
                Interactive visualization of soil
                investigation locations and bearing
                capacity information.
              </p>

            </div>

          </article>


          <article class="feature-card reveal">

            <div class="feature-icon blue">
              <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div>

              <span>
                02
              </span>

              <h3>
                Municipality & Barangay Search
              </h3>

              <p>
                Find available soil records by
                municipality, barangay, or location.
              </p>

            </div>

          </article>


          <article class="feature-card reveal">

            <div class="feature-icon brown">
              <i class="fa-solid fa-file-circle-check"></i>
            </div>

            <div>

              <span>
                03
              </span>

              <h3>
                Marker Details
              </h3>

              <p>
                View coordinates, borehole information,
                SPT results, and other available details.
              </p>

            </div>

          </article>


          <article class="feature-card reveal">

            <div class="feature-icon purple">
              <i class="fa-solid fa-user-shield"></i>
            </div>

            <div>

              <span>
                04
              </span>

              <h3>
                Admin Panel
              </h3>

              <p>
                Authorized administrators can add,
                edit, update, and manage soil data.
              </p>

            </div>

          </article>


          <article class="feature-card reveal">

            <div class="feature-icon olive">
              <i class="fa-solid fa-file-import"></i>
            </div>

            <div>

              <span>
                05
              </span>

              <h3>
                GeoJSON Support
              </h3>

              <p>
                Prepare and import GIS data exported
                from QGIS for web mapping.
              </p>

            </div>

          </article>


          <article class="feature-card reveal">

            <div class="feature-icon teal">
              <i class="fa-solid fa-clock-rotate-left"></i>
            </div>

            <div>

              <span>
                06
              </span>

              <h3>
                Data History
              </h3>

              <p>
                Support multiple soil investigation
                records for a location over time.
              </p>

            </div>

          </article>

        </div>

      </div>

    </section>


    <!-- =========================================
             HOW USER USES SYSTEM
        ========================================== -->

    <section class="section user-flow-section">

      <div class="container">

        <div class="user-flow-layout">

          <div class="user-flow-content reveal">

            <p class="section-kicker">
              <i class="fa-solid fa-route"></i>
              USER FLOW
            </p>

            <h2>
              Find soil information in a few simple steps.
            </h2>

            <p class="user-flow-intro">
              The public interface is designed to keep
              the process simple for engineers, LGUs,
              researchers, planners, students, and
              other users.
            </p>


            <div class="user-steps">

              <div class="user-step">

                <div class="step-number">
                  01
                </div>

                <div>

                  <h3>
                    Search Municipality
                  </h3>

                  <p>
                    Select or search for a
                    municipality in Southern Leyte.
                  </p>

                </div>

              </div>


              <div class="user-step">

                <div class="step-number">
                  02
                </div>

                <div>

                  <h3>
                    View Available Barangays
                  </h3>

                  <p>
                    See barangays where soil data
                    has been collected.
                  </p>

                </div>

              </div>


              <div class="user-step">

                <div class="step-number">
                  03
                </div>

                <div>

                  <h3>
                    View GIS Map
                  </h3>

                  <p>
                    The map focuses on the selected
                    location and displays available
                    soil markers.
                  </p>

                </div>

              </div>


              <div class="user-step">

                <div class="step-number">
                  04
                </div>

                <div>

                  <h3>
                    Click a Marker
                  </h3>

                  <p>
                    View available soil information
                    for the selected location.
                  </p>

                </div>

              </div>

            </div>

          </div>


          <div class="user-flow-visual reveal">

            <div class="phone-interface">

              <div class="phone-top">

                <span>
                  SOUTHERN LEYTE GIS
                </span>

                <i class="fa-solid fa-ellipsis"></i>

              </div>

              <div class="phone-map">

                <img src="images/sample_map.png" alt="GIS map interface preview">

                <div class="phone-marker one"></div>
                <div class="phone-marker two"></div>
                <div class="phone-marker three"></div>

              </div>

              <div class="phone-search">

                <i class="fa-solid fa-magnifying-glass"></i>

                <span>
                  Search location...
                </span>

              </div>

              <div class="phone-bottom">

                <div>
                  <i class="fa-solid fa-map"></i>
                  <span>Map</span>
                </div>

                <div>
                  <i class="fa-solid fa-database"></i>
                  <span>Data</span>
                </div>

                <div>
                  <i class="fa-solid fa-circle-info"></i>
                  <span>About</span>
                </div>

              </div>

            </div>

          </div>

        </div>

      </div>

    </section>


    <!-- =========================================
             TARGET USERS
        ========================================== -->

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

            <img src="images/soil_data.jpg" alt="Southern Leyte landscape">

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


          <!-- <aside class="mission-card reveal">

            <div class="mission-symbol">

              <i class="fa-solid fa-leaf"></i>

            </div>

            <span>
              SYSTEM PURPOSE
            </span>

            <h3>
              Building a Safer and Stronger Southern Leyte
            </h3>

            <p>
              Reliable data.
              Informed decisions.
              Sustainable development.
            </p>

            <div class="mission-line"></div>

          </aside> -->

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

            <div class="workflow-source">
              <i class="fa-solid fa-file-circle-check"></i>
              Reports • Tests • Records
            </div>

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

            <div class="workflow-source">
              <i class="fa-solid fa-file-export"></i>
              QGIS → GeoJSON
            </div>

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

            <div class="workflow-source">
              <i class="fa-solid fa-user-shield"></i>
              Secure Administration
            </div>

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

            <div class="workflow-source">
              <i class="fa-solid fa-magnifying-glass"></i>
              Search • Map • Details
            </div>

          </article>

        </div>


        <div class="workflow-bottom reveal">

          <div class="workflow-line"></div>

          <div class="workflow-caption">

            <span>
              <i class="fa-solid fa-arrows-rotate"></i>
              Continuous Data Updating
            </span>

            <p>
              Additional soil investigation records can be
              added as new data becomes available.
            </p>

          </div>

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

  <footer class="footer" id="contact">

    <div class="container footer-grid">

      <div class="footer-brand">

        <div class="footer-brand-row">

          <span class="footer-logo">

            <img src="images/logo.png" alt="Southern Leyte logo">

          </span>

          <div>

            <strong>
              SOUTHERN LEYTE
            </strong>

            <span>
              Soil Bearing Capacity Information System
            </span>

            <small>
              For safer and sustainable communities
            </small>

          </div>

        </div>

        <p class="footer-description">
          A GIS-based platform for organizing and accessing
          soil investigation information across Southern Leyte.
        </p>

      </div>


      <!-- <div class="group-f"> -->

      <div class="footer-column">

        <h3>
          System
        </h3>

        <a href="#home">
          Home
        </a>

        <a href="#workflow">
          How It Works
        </a>

        <a href="#map">
          GIS Map
        </a>

        <a href="#soil-data">
          Soil Data
        </a>

        <a href="#about">
          About
        </a>

      </div>


      <!-- <div class="footer-column">

          <h3>
            Information
          </h3>

          <a href="#workflow">
            System Flow
          </a>

          <a href="#about">
            Purpose
          </a>

          <a href="#soil-data">
            Data Records
          </a>

          <a href="#contact">
            Contact
          </a>

          <a href="#home">
            Disclaimer
          </a>

        </div> -->


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

      <!-- </div> -->

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


  <!-- JavaScript -->
  <script src="js/script.js"></script>

</body>

</html>