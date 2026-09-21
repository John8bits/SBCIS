<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Database\Connection;
use App\Services\LoginRateLimiter;
use App\Support\AdminSession;

$db = Connection::get();
$db->exec('CREATE TEMPORARY TABLE admins (
    admin_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM(\'admin\',\'super_admin\') NOT NULL DEFAULT \'admin\',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE login_attempts (
    attempt_key CHAR(64) PRIMARY KEY, failure_count INT UNSIGNED NOT NULL DEFAULT 0,
    first_failed_at DATETIME NOT NULL, last_failed_at DATETIME NOT NULL,
    blocked_until DATETIME NULL
) ENGINE=InnoDB');

$hash = password_hash('isolated-test-password', PASSWORD_DEFAULT);
$insert = $db->prepare('INSERT INTO admins (email,password,role) VALUES (?,?,?)');
$insert->execute(['fixture@example.test', $hash, 'super_admin']);
$admin = ['admin_id'=>(int)$db->lastInsertId(), 'email'=>'fixture@example.test', 'password'=>$hash, 'role'=>'super_admin'];

$sessionDirectory = sys_get_temp_dir() . '/sbcis-auth-test-' . bin2hex(random_bytes(6));
mkdir($sessionDirectory, 0700);
session_save_path($sessionDirectory);
session_id('sbcisauth' . bin2hex(random_bytes(10)));

$checks = 0;
$expect = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};

AdminSession::establish($admin);
$params = session_get_cookie_params();
$expect($params['httponly'] === true, 'Session cookie must be HttpOnly.');
$expect(strtolower((string) $params['samesite']) === 'lax', 'Session cookie must use SameSite=Lax.');
$expect(AdminSession::isLoggedIn(), 'Fresh administrator session rejected.');
$expect(AdminSession::isSuperAdmin(), 'Super administrator role unavailable.');

$db->exec("UPDATE admins SET role='admin' WHERE admin_id=1");
$expect(AdminSession::isLoggedIn() && !AdminSession::isSuperAdmin(), 'Role demotion was not applied to the active session.');
$db->exec("UPDATE admins SET password='changed-hash' WHERE admin_id=1");
$expect(!AdminSession::isLoggedIn(), 'Password change did not revoke the active session.');

session_id('sbcisauth' . bin2hex(random_bytes(10)));
$admin['password'] = $hash;
$admin['role'] = 'admin';
$db->prepare('UPDATE admins SET password=?, role=? WHERE admin_id=1')->execute([$hash, 'admin']);
AdminSession::establish($admin);
$db->exec('DELETE FROM admins WHERE admin_id=1');
$expect(!AdminSession::isLoggedIn(), 'Deleted administrator retained access.');

$limiter = new LoginRateLimiter($db);
for ($attempt = 0; $attempt < 5; $attempt++) $limiter->recordFailure('192.0.2.10');
try {
    $limiter->assertAllowed('192.0.2.10');
    throw new RuntimeException('Rate limiter did not block repeated failures.');
} catch (RuntimeException $error) {
    $expect(str_starts_with($error->getMessage(), 'Too many sign-in attempts'), 'Unexpected rate-limit error.');
}
$limiter->clear('192.0.2.10');
$limiter->assertAllowed('192.0.2.10');
$checks++;

echo $checks . " authentication/session checks passed.\n";
