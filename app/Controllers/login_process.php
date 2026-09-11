<?php

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../index.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: ../../index.php?login=empty');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../../index.php?login=invalid');
    exit;
}

try {

    require_once __DIR__ . '/../../config/config.php';

    $sql = "
        SELECT
            admin_id,
            email,
            password
        FROM admins
        WHERE email = :email
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':email' => $email
    ]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {

        session_regenerate_id(true);

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_email'] = $admin['email'];

        header('Location: ../../views/admin/admin_dashboard.php?login=success');
        exit;
    }

    header('Location: ../../index.php?login=failed');
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    header('Location: ../../index.php?login=error');
    exit;
}
