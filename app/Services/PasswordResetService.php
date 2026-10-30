<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class PasswordResetService
{
    public function __construct(private PDO $database) {}

    public function request(string $email, string $remoteAddress): void
    {
        $email = strtolower(trim($email));
        $key = hash('sha256', $remoteAddress . '|' . $email);
        $this->database->beginTransaction();
        try {
            $this->database->prepare('INSERT IGNORE INTO password_reset_requests (request_key,last_requested_at)
                VALUES (?, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR))')->execute([$key]);
            $check = $this->database->prepare('SELECT last_requested_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 SECOND)
                FROM password_reset_requests WHERE request_key=? FOR UPDATE');
            $check->execute([$key]);
            if ((int)$check->fetchColumn() === 1) {
                $this->database->commit(); return;
            }
            $this->database->prepare('UPDATE password_reset_requests SET last_requested_at=UTC_TIMESTAMP() WHERE request_key=?')->execute([$key]);
            $this->database->commit();
        } catch (\Throwable $error) { if ($this->database->inTransaction()) $this->database->rollBack(); throw $error; }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;
        $base = rtrim($this->emailSetting('SBCIS_PUBLIC_BASE_URL', 'public_base_url'), '/');
        $api = (string)getenv('SBCIS_EMAIL_API_URL');
        $secret = (string)getenv('SBCIS_EMAIL_API_KEY');
        $from = (string)getenv('SBCIS_EMAIL_FROM');
        $emailJsService = $this->emailSetting('SBCIS_EMAILJS_SERVICE_ID', 'service_id');
        $emailJsTemplate = $this->emailSetting('SBCIS_EMAILJS_TEMPLATE_ID', 'template_id');
        $emailJsPublicKey = $this->emailSetting('SBCIS_EMAILJS_PUBLIC_KEY', 'public_key');
        $emailJsReady = $emailJsService !== '' && $emailJsTemplate !== '' && $emailJsPublicKey !== '';
        $localHttp = \Config\ProductionConfig::environment() !== 'production' &&
            preg_match('~^http://(?:127\.0\.0\.1|localhost)(?::\d+)?(?:/|$)~i', $base) === 1;
        if ($base === '' || (!str_starts_with($base, 'https://') && !$localHttp) ||
            (!$emailJsReady && ($api === '' || $secret === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)))) {
            error_log('Password reset email settings are incomplete.'); return;
        }
        $lookup = $this->database->prepare('SELECT admin_id FROM admins WHERE email=? LIMIT 1');
        $lookup->execute([$email]);
        $adminId = $lookup->fetchColumn();
        if ($adminId === false) return;
        $token = bin2hex(random_bytes(32));
        $this->database->prepare('DELETE FROM password_reset_tokens WHERE admin_id=?')->execute([$adminId]);
        $this->database->prepare('INSERT INTO password_reset_tokens (admin_id,token_hash,expires_at)
            VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))')->execute([$adminId, hash('sha256', $token)]);
        $url = $base . '/views/reset_password.php?token=' . $token;
        try {
            if ($emailJsReady) $this->sendEmailJs($emailJsService, $emailJsTemplate, $emailJsPublicKey, $email, $url);
            else $this->send($api, $secret, $from, $email, $url);
        }
        catch (\Throwable $error) {
            $this->database->prepare('DELETE FROM password_reset_tokens WHERE token_hash=?')->execute([hash('sha256', $token)]);
            error_log('Password reset delivery failed: ' . $error->getMessage());
        }
    }

    public function reset(string $token, string $password): bool
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)) return false;
        if (strlen($password) < 12 || strlen($password) > 128) throw new \InvalidArgumentException('Use a password of 12 to 128 characters.');
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare('SELECT token_id,admin_id FROM password_reset_tokens
                WHERE token_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE');
            $statement->execute([hash('sha256', $token)]);
            $record = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$record) { $this->database->rollBack(); return false; }
            $this->database->prepare('UPDATE admins SET password=? WHERE admin_id=?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $record['admin_id']]);
            $this->database->prepare('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE admin_id=?')
                ->execute([$record['admin_id']]);
            $this->database->commit();
            AuditLogger::record($this->database, 'password_reset', 'administrator', (int)$record['admin_id']);
            return true;
        } catch (\Throwable $error) { if ($this->database->inTransaction()) $this->database->rollBack(); throw $error; }
    }

    private function send(string $api, string $secret, string $from, string $to, string $url): void
    {
        if (!str_starts_with($api, 'https://')) throw new RuntimeException('The email API must use HTTPS.');
        $body = json_encode(['from'=>$from, 'to'=>$to, 'subject'=>'Reset your SBCIS password',
            'text'=>"A password reset was requested for your SBCIS administrator account.\n\nOpen this link within 30 minutes:\n$url\n\nIf you did not request this, ignore this email.",
            'html'=>'<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:28px;color:#17352a">'
                .'<p style="font-size:12px;letter-spacing:.08em;color:#146b4d">SOUTHERN LEYTE SOIL INFORMATION SYSTEM</p>'
                .'<h1 style="font-size:25px">Reset your password</h1>'
                .'<p>A password reset was requested for your SBCIS administrator account.</p>'
                .'<p><a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" style="display:inline-block;background:#146b4d;color:#fff;padding:12px 18px;text-decoration:none">Set a new password</a></p>'
                .'<p>This link expires in 30 minutes and can be used once. If you did not request it, you can ignore this email.</p>'
                .'</div>'], JSON_THROW_ON_ERROR);
        $this->postJson($api, $body, ['Authorization: Bearer ' . $secret, 'Content-Type: application/json']);
    }

    private function sendEmailJs(string $serviceId, string $templateId, string $publicKey, string $to, string $url): void
    {
        $this->postJson('https://api.emailjs.com/api/v1.0/email/send',
            json_encode($this->emailJsPayload($serviceId, $templateId, $publicKey, $to, $url), JSON_THROW_ON_ERROR),
            ['Content-Type: application/json']);
    }

    private function emailJsPayload(string $serviceId, string $templateId, string $publicKey, string $to, string $url): array
    {
        $payload = ['service_id' => $serviceId, 'template_id' => $templateId,
            'user_id' => $publicKey,
            'template_params' => ['to_email' => $to, 'reset_url' => $url,
                'expires_minutes' => '30', 'app_name' => 'SBCIS']];
        $privateKey = $this->emailSetting('SBCIS_EMAILJS_PRIVATE_KEY', 'private_key');
        if ($privateKey !== '') $payload['accessToken'] = $privateKey;
        return $payload;
    }

    private function postJson(string $api, string $body, array $headers): void
    {
        $handle = curl_init($api);
        if ($handle === false) throw new RuntimeException('Unable to initialize email delivery.');
        curl_setopt_array($handle, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body,
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_CONNECTTIMEOUT=>5]);
        $response = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($response === false || $status < 200 || $status >= 300) throw new RuntimeException($error ?: 'Email API returned HTTP ' . $status);
    }

    private function emailSetting(string $environment, string $localKey): string
    {
        $value = getenv($environment);
        if (is_string($value) && $value !== '') return $value;
        $file = dirname(__DIR__, 2) . '/config/email.local.php';
        $local = is_file($file) ? require $file : [];
        return is_array($local) && is_string($local[$localKey] ?? null) ? $local[$localKey] : '';
    }
}
