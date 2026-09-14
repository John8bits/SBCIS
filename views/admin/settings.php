<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SESSION['admin_logged_in'] ?? false) !== true || empty($_SESSION['admin_id'])) {
    header('Location: ../../index.php?login=required');
    exit;
}

require_once __DIR__ . '/../../app/Models/geotechnical_data.php';

$_SESSION['settings_csrf'] = $_SESSION['settings_csrf'] ?? bin2hex(random_bytes(32));
$settingsMessage = $_SESSION['settings_message'] ?? null;
$settingsError = null;
$admin = [
    'email' => $_SESSION['admin_email'] ?? '',
    'password' => '',
    'created_at' => null,
];
unset($_SESSION['settings_message']);

try {
    $db = sbcis_get_database();
    if (!$db instanceof PDO)
        throw new RuntimeException('Database connection is unavailable.');
    $adminStatement = $db->prepare('SELECT email, password, created_at FROM admins WHERE admin_id = :admin_id LIMIT 1');
    $adminStatement->execute([':admin_id' => (int) $_SESSION['admin_id']]);
    $admin = $adminStatement->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        $_SESSION = [];
        session_destroy();
        header('Location: ../../index.php?login=required');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['settings_csrf'], $_POST['csrf']))
            throw new InvalidArgumentException('Your session expired. Reload the settings page and try again.');

        $currentPassword = $_POST['current_password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!is_string($currentPassword) || !password_verify($currentPassword, $admin['password']))
            throw new InvalidArgumentException('Enter your current password to save account changes.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            throw new InvalidArgumentException('Enter a valid administrator email address.');
        if ($newPassword !== '' && strlen($newPassword) < 8)
            throw new InvalidArgumentException('New passwords must be at least 8 characters long.');
        if ($newPassword !== $confirmPassword)
            throw new InvalidArgumentException('The new password and confirmation do not match.');

        $duplicate = $db->prepare('SELECT admin_id FROM admins WHERE email = :email AND admin_id <> :admin_id LIMIT 1');
        $duplicate->execute([':email' => $email, ':admin_id' => (int) $_SESSION['admin_id']]);
        if ($duplicate->fetch())
            throw new InvalidArgumentException('That email address is already used by another administrator.');

        $password = $newPassword === '' ? $admin['password'] : password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $db->prepare('UPDATE admins SET email = :email, password = :password WHERE admin_id = :admin_id');
        $update->execute([
            ':email' => $email,
            ':password' => $password,
            ':admin_id' => (int) $_SESSION['admin_id'],
        ]);

        $_SESSION['admin_email'] = $email;
        $_SESSION['settings_message'] = 'Your administrator account was updated.';
        header('Location: settings.php');
        exit;
    }
} catch (InvalidArgumentException $error) {
    $settingsError = $error->getMessage();
} catch (Throwable $error) {
    error_log('Admin settings: ' . $error->getMessage());
    $settingsError = 'The account could not be updated. Please try again.';
}

$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$title = 'Settings';
$subtitle = 'Manage your administrator account and sign-in security';
$activePage = 'settings.php';
$topbarActions = [];
require __DIR__ . '/overview_shell.php';
?>
<?php if ($settingsMessage): ?><div hidden data-toast data-icon="success" data-title="Settings saved"><?= $escape($settingsMessage) ?></div><?php endif; ?>
<?php if ($settingsError): ?><div hidden data-toast data-icon="error" data-title="Unable to save"><?= $escape($settingsError) ?></div><?php endif; ?>
<div class="settings-layout">
    <section class="panel settings-panel">
        <div class="panel-header"><div><h3>Account and security</h3><span>Keep your administrator contact and sign-in details current.</span></div></div>
        <form class="settings-form" method="post" action="settings.php">
            <input type="hidden" name="csrf" value="<?= $escape($_SESSION['settings_csrf']) ?>">
            <div class="settings-section">
                <h4>Administrator email</h4>
                <p class="settings-help">This address is used when you sign in to the Southern Leyte Soil Information System.</p>
                <label class="settings-field">Email address<input type="email" name="email" value="<?= $escape($admin['email']) ?>" autocomplete="email" required></label>
            </div>
            <div class="settings-section">
                <h4>Change password</h4>
                <p class="settings-help">Leave the new password fields blank if you only want to change the email address.</p>
                <div class="settings-grid">
                    <label class="settings-field">New password<input type="password" name="new_password" minlength="8" autocomplete="new-password"></label>
                    <label class="settings-field">Confirm new password<input type="password" name="confirm_password" minlength="8" autocomplete="new-password"></label>
                </div>
            </div>
            <div class="settings-section settings-confirm">
                <h4>Confirm changes</h4>
                <label class="settings-field">Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
                <p class="settings-help">Your current password is required for every account change.</p>
            </div>
            <div class="settings-actions"><button class="submit-button" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Save settings</button></div>
        </form>
    </section>
    <aside class="panel settings-summary">
        <div class="panel-header"><div><h3>Account overview</h3><span>Current administrator access</span></div></div>
        <div class="settings-summary-body">
            <div class="settings-status"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><div><strong>Administrator access</strong><span>Protected account</span></div></div>
            <dl><div><dt>Signed-in email</dt><dd><?= $escape($admin['email']) ?></dd></div><div><dt>Account created</dt><dd><?= $admin['created_at'] ? $escape(date('F j, Y', strtotime($admin['created_at']))) : 'Unavailable' ?></dd></div></dl>
            <p class="settings-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Sign out after changing your password on a shared computer.</p>
        </div>
    </aside>
</div>