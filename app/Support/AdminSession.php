<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Connection;
use PDO;
use Throwable;

final class AdminSession
{
    private const DEFAULT_IDLE_TIMEOUT = 1800;
    private const DEFAULT_ABSOLUTE_LIFETIME = 28800;

    public static function requireLogin(string $redirect = '../../index.php?login=required'): void
    {
        header('Cache-Control: no-store');
        if (!self::isLoggedIn()) {
            header('Location: ' . $redirect);
            exit;
        }
    }

    public static function isLoggedIn(): bool
    {
        self::start();
        if (($_SESSION['admin_logged_in'] ?? false) !== true || empty($_SESSION['admin_id'])) return false;
        $now = time();
        $started = (int) ($_SESSION['admin_started_at'] ?? 0);
        $lastSeen = (int) ($_SESSION['admin_last_seen_at'] ?? 0);
        if ($started < 1 || $lastSeen < 1 ||
            $now - $lastSeen > self::secondsFromEnvironment('SBCIS_SESSION_IDLE_SECONDS', self::DEFAULT_IDLE_TIMEOUT) ||
            $now - $started > self::secondsFromEnvironment('SBCIS_SESSION_ABSOLUTE_SECONDS', self::DEFAULT_ABSOLUTE_LIFETIME)) {
            self::invalidate();
            return false;
        }

        try {
            $statement = Connection::get()->prepare(
                'SELECT admin_id, email, password, role FROM admins WHERE admin_id=:admin_id LIMIT 1'
            );
            $statement->execute([':admin_id' => (int) $_SESSION['admin_id']]);
            $admin = $statement->fetch(PDO::FETCH_ASSOC);
            $fingerprint = is_array($admin) ? hash('sha256', (string) $admin['password']) : '';
            if (!$admin || !is_string($_SESSION['admin_auth_fingerprint'] ?? null) ||
                !hash_equals((string) $_SESSION['admin_auth_fingerprint'], $fingerprint)) {
                self::invalidate();
                return false;
            }
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'] ?? 'admin';
            $_SESSION['admin_last_seen_at'] = $now;
            return true;
        } catch (Throwable $error) {
            error_log('Admin session validation failed: ' . $error->getMessage());
            self::invalidate();
            return false;
        }
    }

    public static function isSuperAdmin(): bool
    {
        return self::isLoggedIn() && ($_SESSION['admin_role'] ?? 'admin') === 'super_admin';
    }

    public static function requireSuperAdmin(): void
    {
        self::requireLogin();
        if (!self::isSuperAdmin()) {
            http_response_code(403);
            exit('Super administrator access required.');
        }
    }

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');
            session_name('SBCIS_ADMIN_SESSION');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function establish(array $admin): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = (int) $admin['admin_id'];
        $_SESSION['admin_email'] = (string) $admin['email'];
        $_SESSION['admin_role'] = (string) ($admin['role'] ?? 'admin');
        $_SESSION['admin_auth_fingerprint'] = hash('sha256', (string) $admin['password']);
        $_SESSION['admin_started_at'] = time();
        $_SESSION['admin_last_seen_at'] = time();
        $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
    }

    public static function invalidate(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?: 'Lax',
            ]);
        }
        session_destroy();
    }

    private static function secondsFromEnvironment(string $name, int $default): int
    {
        $value = filter_var(getenv($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => 60]]);
        return $value === false ? $default : $value;
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ||
            (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }
}

