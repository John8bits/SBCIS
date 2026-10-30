<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/bootstrap.php';
$db = App\Database\Connection::get();
$db->exec('CREATE TEMPORARY TABLE admins (admin_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT, email VARCHAR(255), password VARCHAR(255)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE password_reset_tokens (token_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    admin_id INT UNSIGNED, token_hash CHAR(64), expires_at DATETIME, used_at DATETIME NULL) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE password_reset_requests (request_key CHAR(64) PRIMARY KEY, last_requested_at DATETIME) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE audit_log (audit_id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NULL,
    action VARCHAR(50), entity_type VARCHAR(50), entity_id VARCHAR(100), details_json TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$db->prepare('INSERT INTO admins (email,password) VALUES (?,?)')->execute(['admin@example.test', password_hash('original-password', PASSWORD_DEFAULT)]);
$service = new App\Services\PasswordResetService($db);
$settingMethod = new ReflectionMethod($service, 'emailSetting');
$localConfigFile = dirname(__DIR__) . '/config/email.local.php';
if (!is_file($localConfigFile)) {
    file_put_contents($localConfigFile, "<?php return ['service_id' => 'fixture_service'];\n");
    try {
        if ($settingMethod->invoke($service, 'SBCIS_EMAILJS_SERVICE_ID', 'service_id') !== 'fixture_service')
            throw new RuntimeException('Local EmailJS settings were not loaded.');
        putenv('SBCIS_EMAILJS_SERVICE_ID=environment_service');
        if ($settingMethod->invoke($service, 'SBCIS_EMAILJS_SERVICE_ID', 'service_id') !== 'environment_service')
            throw new RuntimeException('Environment settings should override local EmailJS settings.');
        putenv('SBCIS_EMAILJS_SERVICE_ID');
    } finally {
        putenv('SBCIS_EMAILJS_SERVICE_ID');
        unlink($localConfigFile);
    }
}
$emailJsPayload = new ReflectionMethod($service, 'emailJsPayload');
putenv('SBCIS_EMAILJS_PRIVATE_KEY=example-private-key');
$payload = $emailJsPayload->invoke($service, 'service_test', 'template_test', 'public_test',
    'admin@example.test', 'https://example.test/views/reset_password.php?token=abc');
if ($payload['service_id'] !== 'service_test' || $payload['template_id'] !== 'template_test'
    || $payload['user_id'] !== 'public_test' || $payload['accessToken'] !== 'example-private-key'
    || $payload['template_params']['to_email'] !== 'admin@example.test'
    || !str_contains($payload['template_params']['reset_url'], 'token=abc'))
    throw new RuntimeException('EmailJS request payload is incorrect.');
putenv('SBCIS_EMAILJS_PRIVATE_KEY');
$payload = $emailJsPayload->invoke($service, 's', 't', 'p', 'admin@example.test', 'https://example.test/reset');
if (isset($payload['accessToken'])) throw new RuntimeException('Missing EmailJS private key should be omitted.');
$service->request('unknown@example.test', '192.0.2.1');
if ((int)$db->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn() !== 1) throw new RuntimeException('Request throttle missing.');
$service->request('unknown@example.test', '192.0.2.1');
if ((int)$db->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn() !== 1) throw new RuntimeException('Request throttle failed.');
$token = bin2hex(random_bytes(32));
$db->prepare('INSERT INTO password_reset_tokens (admin_id,token_hash,expires_at) VALUES (1,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))')
    ->execute([hash('sha256',$token)]);
try { $service->reset($token, 'short'); throw new RuntimeException('Short password accepted.'); }
catch (InvalidArgumentException $expected) {}
if ($service->reset('invalid', 'new-strong-password')) throw new RuntimeException('Invalid token accepted.');
if (!$service->reset($token, 'new-strong-password')) throw new RuntimeException('Valid token failed.');
if ($service->reset($token, 'another-strong-password')) throw new RuntimeException('Token reused.');
if (!password_verify('new-strong-password', (string)$db->query('SELECT password FROM admins')->fetchColumn()))
    throw new RuntimeException('Password not updated.');
$expired = bin2hex(random_bytes(32));
$db->prepare('INSERT INTO password_reset_tokens (admin_id,token_hash,expires_at) VALUES (1,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))')
    ->execute([hash('sha256',$expired)]);
if ($service->reset($expired, 'another-strong-password')) throw new RuntimeException('Expired token accepted.');
echo "Password reset checks passed: throttling, validation, single use, expiry and password update.\n";
