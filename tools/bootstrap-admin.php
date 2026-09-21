<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/config/bootstrap.php';

$email = getenv('SBCIS_BOOTSTRAP_EMAIL');
$password = getenv('SBCIS_BOOTSTRAP_PASSWORD');
if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !is_string($password) || strlen($password) < 12) {
    fwrite(STDERR, "Set SBCIS_BOOTSTRAP_EMAIL and a unique SBCIS_BOOTSTRAP_PASSWORD of at least 12 characters.\n");
    exit(2);
}

$database = App\Database\Connection::get();
$count = (int) $database->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if ($count !== 0) {
    fwrite(STDERR, "Bootstrap refused because an administrator account already exists.\n");
    exit(3);
}

$statement = $database->prepare('INSERT INTO admins (email,password,role) VALUES (:email,:password,\'super_admin\')');
$statement->execute([':email'=>strtolower(trim($email)), ':password'=>password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Initial super administrator created. Clear the bootstrap environment variables now.\n");
