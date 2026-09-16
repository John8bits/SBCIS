<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use PDO;
use Throwable;

final class AuthController
{
    public function login(array $input, string $method): void
    {
        $this->startSession();

        if ($method !== 'POST') {
            $this->redirect('../../index.php');
        }

        $email = trim((string) ($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->redirect('../../index.php?login=empty');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('../../index.php?login=invalid');
        }

        try {
            $database = Connection::get();
            if (!$database) {
                throw new \RuntimeException('Database connection is unavailable.');
            }
            $statement = $database->prepare(
                'SELECT admin_id, email, password FROM admins WHERE email = :email LIMIT 1'
            );
            $statement->execute([':email' => $email]);
            $admin = $statement->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify((string) $password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_email'] = $admin['email'];
                $this->redirect('../../views/admin/admin_dashboard.php?login=success');
            }

            $this->redirect('../../index.php?login=failed');
        } catch (Throwable $error) {
            error_log('Login: ' . $error->getMessage());
            $this->redirect('../../index.php?login=error');
        }
    }

    public function logout(): void
    {
        $this->startSession();
        $_SESSION = [];
        session_destroy();
        $this->redirect('../../index.php?logout=success');
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function redirect(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
