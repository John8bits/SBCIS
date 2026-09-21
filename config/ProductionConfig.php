<?php

declare(strict_types=1);

namespace Config;

use RuntimeException;

final class ProductionConfig
{
    public static function initialize(): void
    {
        $production = self::environment() === 'production';
        ini_set('display_errors', $production ? '0' : '1');
        ini_set('display_startup_errors', $production ? '0' : '1');
        ini_set('log_errors', '1');

        if (PHP_SAPI !== 'cli') {
            self::sendSecurityHeaders($production);
            if ($production && !self::isHttps()) {
                $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
                $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
                if ($host === '' || preg_match('/[^a-zA-Z0-9.\-:\[\]]/', $host)) {
                    throw new RuntimeException('Invalid production host header.');
                }
                header('Location: https://' . $host . $uri, true, 308);
                exit;
            }
        }

        if ($production) self::validateProductionEnvironment();
    }

    public static function environment(): string
    {
        $value = strtolower(trim((string) (getenv('SBCIS_ENV') ?: 'development')));
        return in_array($value, ['development','test','production'], true) ? $value : 'development';
    }

    private static function sendSecurityHeaders(bool $production): void
    {
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://unpkg.com https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data: https://tile.openstreetmap.org; connect-src 'self'");
        if ($production && self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private static function validateProductionEnvironment(): void
    {
        foreach (['SBCIS_DB_HOST','SBCIS_DB_NAME','SBCIS_DB_USER','SBCIS_DB_PASSWORD','SBCIS_INTERPOLATION_CACHE_DIR'] as $name) {
            if (!is_string(getenv($name)) || trim((string) getenv($name)) === '') {
                throw new RuntimeException('Missing required production environment setting: ' . $name);
            }
        }
        if (getenv('SBCIS_DB_USER') === 'root') {
            throw new RuntimeException('The production database must use a least-privilege account.');
        }
    }

    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') return true;
        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
        return getenv('SBCIS_TRUST_PROXY') === '1' &&
            strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0])) === 'https';
    }
}
