<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';
App\Support\AdminSession::start();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$_SESSION['reset_csrf'] ??= bin2hex(random_bytes(32));
$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$success = false;
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['reset_csrf'], $_POST['csrf'])) {
        $error = 'The form expired. Reload the link from your email.';
    } elseif ((string)($_POST['password'] ?? '') !== (string)($_POST['confirm_password'] ?? '')) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $success = (new App\Services\PasswordResetService(App\Database\Connection::get()))
                ->reset($token, (string)($_POST['password'] ?? ''));
            if (!$success) $error = 'This link is invalid, expired, or already used. Request a new one.';
        } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
        catch (Throwable $exception) { error_log('Password reset: ' . $exception->getMessage()); $error = 'Unable to reset your password. Please try again.'; }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>New password | SBCIS</title>
<link rel="stylesheet" href="../src/css/password-reset.css"></head><body>
<main class="reset-card"><a class="back" href="../index.php">&larr; Back to SBCIS</a>
<h1><?= $success ? 'Password updated' : 'Set a new password' ?></h1>
<?php if ($success): ?><p role="status" class="notice">Your password has been updated. You can now sign in with the new password.</p><a class="button" href="../index.php?login=required">Return to sign in</a>
<?php else: ?>
<p>Use at least 12 characters. This link expires after 30 minutes and works once.</p>
<?php if ($error): ?><p role="alert" class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if (preg_match('/\A[a-f0-9]{64}\z/D', $token)): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['reset_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
<label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
<label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
<button type="submit">Update password</button></form>
<?php else: ?><p class="notice error" role="alert">This link is invalid. Request a new reset link.</p><a class="button" href="forgot_password.php">Request a link</a><?php endif; ?>
<?php endif; ?></main></body></html>
