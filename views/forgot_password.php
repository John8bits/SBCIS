<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';
App\Support\AdminSession::start();
header('Cache-Control: no-store');
$_SESSION['reset_csrf'] ??= bin2hex(random_bytes(32));
$sent = false;
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['reset_csrf'], $_POST['csrf'])) {
        $error = 'The form expired. Reload this page and try again.';
    } else {
        try {
            (new App\Services\PasswordResetService(App\Database\Connection::get()))
                ->request((string)($_POST['email'] ?? ''), (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $sent = true;
        } catch (Throwable $exception) {
            error_log('Password reset request: ' . $exception->getMessage());
            $error = 'The request could not be processed. Please try again later.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Forgot password | SBCIS</title>
<link rel="stylesheet" href="../src/css/password-reset.css"></head><body>
<main class="reset-card"><a class="back" href="../index.php">&larr; Back to SBCIS</a>
<h1>Reset your password</h1><p>Enter your administrator email. If it matches an account, we’ll send a link that expires in 30 minutes.</p>
<?php if ($sent): ?><p role="status" class="notice">If this email belongs to an administrator, a reset link will arrive shortly. Check your spam folder too.</p><?php endif; ?>
<?php if ($error): ?><p role="alert" class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['reset_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" maxlength="255" required>
<button type="submit">Send reset link</button></form></main></body></html>
