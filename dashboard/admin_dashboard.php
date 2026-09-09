<?php

session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {

    header('Location: ../index.php?login=required');
    exit;

}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <h1>Admin Dashboard</h1>
    <a href="../logout.php" id="logoutButton">
        <i class="fa-solid fa-right-from-bracket"></i>
        Logout
    </a>
    

    <script>

        const urlParams =
            new URLSearchParams(
                window.location.search
            );

        const loginStatus =
            urlParams.get("login");


        if (loginStatus === "success") {

            Swal.fire({

                icon: "success",

                title: "Login Successful",

                text: "Welcome to the Southern Leyte Soil Information System.",

                confirmButtonText: "Continue",

                confirmButtonColor: "#0b3d2e",

                timer: 2500,

                timerProgressBar: true

            });

        }

        if (loginStatus) {

            window.history.replaceState(
                {},
                document.title,
                window.location.pathname
            );

        }



    </script>

    <script>

        const logoutButton =
            document.getElementById("logoutButton");


        if (logoutButton) {

            logoutButton.addEventListener(
                "click",
                function (event) {

                    event.preventDefault();

                    Swal.fire({

                        icon: "warning",
                        title: "Logout?",
                        text: "Are you sure you want to logout from?",
                        showCancelButton: true,
                        confirmButtonText: "Yes, Logout",
                        cancelButtonText: "Cancel",
                        confirmButtonColor: "#0b3d2e",
                        cancelButtonColor: "#6c757d",
                        reverseButtons: true

                    }).then((result) => {

                        if (result.isConfirmed) {

                            window.location.href ="../logout.php";

                        }

                    });

                }
            );

        }

    </script>

</body>

</html>