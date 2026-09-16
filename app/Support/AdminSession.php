<?php

declare(strict_types=1);

namespace App\Support;

final class AdminSession
{
    public static function requireLogin(string $redirect = '../../index.php?login=required'): void
    {
        self::start();
        header('Cache-Control: no-store');

        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: ' . $redirect);
            exit;
        }
    }

    public static function isSuperAdmin(): bool
    {
        self::start();
        return ($_SESSION['admin_role'] ?? 'admin') === 'super_admin';
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
            session_start();
        }
    }
}

