<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\LoginRateLimiter;
use App\Services\AuditLogger;
use App\Support\AdminSession;
use PDO;
use RuntimeException;
use Throwable;

final class AuthController
{
    public function login(array $input, string $method): void
    {
        AdminSession::start();

        if ($method !== 'POST') {
            $this->redirect('../../index.php');
        }

        $email = trim((string) ($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        $csrf = $input['csrf'] ?? '';
        if (!is_string($csrf) || empty($_SESSION['login_csrf']) || !hash_equals($_SESSION['login_csrf'], $csrf)) {
            $this->securityLog('login_csrf_rejected', $email);
            $this->redirect('../../index.php?login=expired');
        }

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
            $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $limiter = new LoginRateLimiter($database);
            $limiter->assertAllowed($remoteAddress);
            $statement = $database->prepare(
                'SELECT admin_id, email, password, role FROM admins WHERE email = :email LIMIT 1'
            );
            $statement->execute([':email' => $email]);
            $admin = $statement->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify((string) $password, $admin['password'])) {
                $limiter->clear($remoteAddress);
                AdminSession::establish($admin);
                $this->securityLog('login_succeeded', $email, (int) $admin['admin_id']);
                AuditLogger::record($database, 'login', 'administrator', (int)$admin['admin_id'], [], (int)$admin['admin_id']);
                $this->redirect('../../views/admin/admin_dashboard.php?login=success');
            }

            $limiter->recordFailure($remoteAddress);
            $this->securityLog('login_failed', $email);
            $this->redirect('../../index.php?login=failed');
        } catch (RuntimeException $error) {
            if (str_starts_with($error->getMessage(), 'Too many sign-in attempts')) {
                $this->securityLog('login_throttled', $email);
                $this->redirect('../../index.php?login=throttled');
            }
            error_log('Login runtime failure: ' . $error->getMessage());
            $this->redirect('../../index.php?login=error');
        } catch (Throwable $error) {
            error_log('Login: ' . $error->getMessage());
            $this->redirect('../../index.php?login=error');
        }
    }

    public function logout(array $input, string $method): void
    {
        AdminSession::start();
        if ($method !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit('Method not allowed.');
        }
        $token = $input['csrf'] ?? '';
        if (($_SESSION['admin_logged_in'] ?? false) !== true || !is_string($token) ||
            empty($_SESSION['logout_csrf']) || !hash_equals($_SESSION['logout_csrf'], $token)) {
            http_response_code(403);
            exit('Unable to sign out from this request.');
        }
        $adminId = (int) ($_SESSION['admin_id'] ?? 0);
        try {
            AuditLogger::record(Connection::get(), 'logout', 'administrator', $adminId, [], $adminId);
        } catch (Throwable $error) {
            error_log('Logout audit connection failed: ' . $error->getMessage());
        }
        AdminSession::invalidate();
        $this->securityLog('logout', '', $adminId);
        $this->redirect('../../index.php?logout=success');
    }

    private function securityLog(string $event, string $email = '', int $adminId = 0): void
    {
        $identity = $email === '' ? '-' : substr(hash('sha256', strtolower($email)), 0, 16);
        $address = substr(hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')), 0, 16);
        error_log(sprintf('Security event=%s admin_id=%d identity=%s remote=%s', $event, $adminId, $identity, $address));
    }

    private function redirect(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
