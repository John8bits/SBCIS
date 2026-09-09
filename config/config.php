<?php

$host = '127.0.0.1';
$dbname = 'sbcdb';
$dbUsername = 'root';
$dbPassword = 'shawnmarlogaldo@1122'; // Your MySQL password.

$pdo = new PDO(
    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
    $dbUsername,
    $dbPassword,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
?>
