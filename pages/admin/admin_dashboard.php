<?php

session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {

    header('Location: ../../index.php?login=required');
    exit;

}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../../src/images/logo.png">
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../src/js/toast.js"></script>
</head>

<body>

    <h1>Admin Dashboard</h1>
    <a href="../../app/Controllers/logout.php" id="logoutButton">
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

            AppToast.fire({ icon: 'success', title: 'Welcome back!' });

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
                        toast: false,
                        position: 'center',
                        icon: 'question',
                        title: 'Sign out?',
                        timer: false,
                        showConfirmButton: true,
                        showCancelButton: true,
                        confirmButtonText: 'Sign out',
                        cancelButtonText: 'Stay',
                        confirmButtonColor: '#0b3d2e'
                    }).then((result) => {

                        if (result.isConfirmed) {

                            window.location.href ="../../app/Controllers/logout.php";

                        }

                    });

                }
            );

        }

    </script>

</body>

</html>
