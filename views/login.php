<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../src/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description"
        content="Secure account access for the Southern Leyte Soil Bearing Capacity Information System.">

    <meta name="theme-color" content="#0b3d2e">

    <title>Sign In | Southern Leyte Soil Information System</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../src/css/login.css">
</head>

<body>

    <div class="background-decoration decoration-one"></div>
    <div class="background-decoration decoration-two"></div>
    <div class="background-grid"></div>

    <main class="login-page">

        <section class="login-container">

            <div class="login-brand-panel">

                <div class="brand-content">

                    <a href="../index.php" class="brand">
                        <span class="brand-mark">
                            <img
                                src="../src/images/logo.png"
                                alt="Southern Leyte Soil Information System logo">
                        </span>

                        <span class="brand-copy">
                            <strong>SOUTHERN LEYTE</strong>
                            <small>SOIL INFORMATION SYSTEM</small>
                        </span>
                    </a>

                    <div class="brand-message">

                        <span class="brand-kicker">
                            <i class="fa-solid fa-shield-halved"></i>
                            SECURE ACCESS
                        </span>

                        <h1 style="font-size: 70px;">
                            Manage Soil Data with
                            <span> Confidence.</span>
                        </h1>

                        <!-- <p>
                            Access the secure workspace to manage soil
                            investigation records, GIS information, and
                            bearing capacity data for Southern Leyte.
                        </p> -->

                    </div>
<!-- 
                    <div class="brand-features">

                        <div class="brand-feature">
                            <span class="feature-icon">
                                <i class="fa-solid fa-database"></i>
                            </span>

                            <div>
                                <strong>Centralized Data</strong>
                                <span>Organize soil investigation records</span>
                            </div>
                        </div>

                        <div class="brand-feature">
                            <span class="feature-icon">
                                <i class="fa-solid fa-map-location-dot"></i>
                            </span>

                            <div>
                                <strong>GIS Information</strong>
                                <span>Manage location-based soil data</span>
                            </div>
                        </div>

                        <div class="brand-feature">
                            <span class="feature-icon">
                                <i class="fa-solid fa-user-shield"></i>
                            </span>

                            <div>
                                <strong>Account Access</strong>
                                <span>Secure system management</span>
                            </div>
                        </div>

                    </div> -->

                </div>

                <div class="brand-footer">
                    <span>
                        <i class="fa-solid fa-location-dot"></i>
                        Southern Leyte, Philippines
                    </span>

                    <span>
                        GIS-Based Information System
                    </span>
                </div>

            </div>

            <div class="login-form-panel">

                <div class="login-form-wrapper">

                    <div class="mobile-logo">
                        <div class="mobile-logo-mark">
                            <img
                                src="../src/images/logo.png"
                                alt="Southern Leyte logo">
                        </div>

                        <div>
                            <strong>SOUTHERN LEYTE</strong>
                            <span>SOIL INFORMATION SYSTEM</span>
                        </div>
                    </div>


                    <div class="form-header">

                        <div class="form-icon">
                            <i class="fa-solid fa-lock"></i>
                        </div>

                        <div>
                            <span class="form-kicker">
                                ACCOUNT ACCESS
                            </span>

                            <h2>Welcome Back</h2>
                        </div>

                    </div>

                    <p class="form-description">
                        Enter your account credentials to continue.
                    </p>


                    <form action="../app/Controllers/login_process.php" method="POST" class="login-form">

                        <div class="form-group">

                            <label for="email">
                                Email Address
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-regular fa-envelope"></i>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    placeholder="Enter your email address"
                                    autocomplete="email"
                                    required>

                            </div>

                        </div>

                        <div class="form-group">

                            <div class="label-row">

                                <label for="password">
                                    Password
                                </label>

                                <a href="#" class="forgot-link">
                                    Forgot password?
                                </a>

                            </div>

                            <div class="input-wrapper">

                                <i class="fa-solid fa-lock"></i>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required>

                                <button
                                    type="button"
                                    class="password-toggle"
                                    id="passwordToggle"
                                    aria-label="Show password">

                                    <i class="fa-regular fa-eye"></i>

                                </button>

                            </div>

                        </div>


                        <div class="form-options">

                            <label class="remember-me">

                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1">

                                <span class="custom-checkbox"></span>

                                <span>Remember me</span>

                            </label>

                        </div>


                        <button type="submit" class="login-submit">

                            <span>Sign In</span>

                            <i class="fa-solid fa-arrow-right"></i>

                        </button>

                    </form>

                    <a href="../index.php" class="back-home">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Southern Leyte Soil Information System
                    </a>


                    <div class="login-footer">

                        <span>
                            © 2026 Southern Leyte Soil Bearing Capacity
                            Information System
                        </span>

                        <span>
                            All Rights Reserved.
                        </span>

                    </div>

                </div>

            </div>

        </section>

    </main>


    <script>
        const passwordInput = document.getElementById("password");
        const passwordToggle = document.getElementById("passwordToggle");

        passwordToggle.addEventListener("click", function () {

            const isPassword =
                passwordInput.getAttribute("type") === "password";

            passwordInput.setAttribute(
                "type",
                isPassword ? "text" : "password"
            );

            this.innerHTML = isPassword
                ? '<i class="fa-regular fa-eye-slash"></i>'
                : '<i class="fa-regular fa-eye"></i>';

            this.setAttribute(
                "aria-label",
                isPassword ? "Hide password" : "Show password"
            );

        });
    </script>

</body>

</html>
