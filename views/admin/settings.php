<?php
use App\Database\Connection;
use App\Support\AdminSession;
use App\Support\View;

require_once __DIR__ . '/../../config/bootstrap.php';

AdminSession::start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SESSION['admin_logged_in'] ?? false) !== true || empty($_SESSION['admin_id'])) {
    header('Location: ../../index.php?login=required');
    exit;
}

$_SESSION['settings_csrf'] = $_SESSION['settings_csrf'] ?? bin2hex(random_bytes(32));
$settingsMessage = $_SESSION['settings_message'] ?? null;
$settingsError = null;
$isSuperAdmin = AdminSession::isSuperAdmin();
$roleAccounts = [];
$admin = [
    'email' => $_SESSION['admin_email'] ?? '',
    'password' => '',
    'role' => $_SESSION['admin_role'] ?? 'admin',
    'created_at' => null,
];
unset($_SESSION['settings_message']);

try {
    $db = Connection::get();
    if (!$db instanceof PDO)
        throw new RuntimeException('Database connection is unavailable.');
    $adminStatement = $db->prepare('SELECT email, password, role, created_at FROM admins WHERE admin_id = :admin_id LIMIT 1');
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

        $action = $_POST['action'] ?? 'account_update';
        if (in_array($action, ['add_admin', 'edit_admin', 'delete_admin'], true)) {
            if (!$isSuperAdmin)
                throw new InvalidArgumentException('Only a super administrator can manage administrator accounts.');
            $currentPassword = $_POST['current_password'] ?? '';
            if (!is_string($currentPassword) || !password_verify($currentPassword, $admin['password']))
                throw new InvalidArgumentException('Enter your current password to manage administrator accounts.');

            if ($action === 'add_admin') {
                $email = trim((string) ($_POST['email'] ?? ''));
                $password = $_POST['new_password'] ?? '';
                $role = $_POST['role'] ?? 'admin';
                if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                    throw new InvalidArgumentException('Enter a valid administrator email address.');
                if (!is_string($password) || strlen($password) < 8)
                    throw new InvalidArgumentException('New passwords must be at least 8 characters long.');
                if (!in_array($role, ['admin', 'super_admin'], true))
                    throw new InvalidArgumentException('Choose a valid account role.');
                $duplicate = $db->prepare('SELECT admin_id FROM admins WHERE email = :email LIMIT 1');
                $duplicate->execute([':email' => $email]);
                if ($duplicate->fetch())
                    throw new InvalidArgumentException('That email address is already used by another administrator.');
                $insert = $db->prepare('INSERT INTO admins (email, password, role) VALUES (:email, :password, :role)');
                $insert->execute([
                    ':email' => $email,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':role' => $role,
                ]);
                $_SESSION['settings_message'] = 'The administrator account was added.';
            } else {
                $targetId = filter_var($_POST['admin_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
                $targetStatement = $db->prepare('SELECT admin_id, email, password, role FROM admins WHERE admin_id = :admin_id LIMIT 1');
                $targetStatement->execute([':admin_id' => $targetId]);
                $target = $targetStatement->fetch(PDO::FETCH_ASSOC);
                if (!$target)
                    throw new InvalidArgumentException('That administrator account no longer exists.');
                if ($action === 'delete_admin') {
                    if ($targetId === (int) $_SESSION['admin_id'])
                        throw new InvalidArgumentException('You cannot delete your own administrator account.');
                    if ($target['role'] === 'super_admin' && (int) $db->query("SELECT COUNT(*) FROM admins WHERE role = 'super_admin'")->fetchColumn() <= 1)
                        throw new InvalidArgumentException('At least one super administrator must remain active.');
                    $delete = $db->prepare('DELETE FROM admins WHERE admin_id = :admin_id');
                    $delete->execute([':admin_id' => $targetId]);
                    $_SESSION['settings_message'] = 'The administrator account was deleted.';
                } else {
                    if ($targetId === (int) $_SESSION['admin_id'])
                        throw new InvalidArgumentException('Manage your own email and password in the account settings above.');
                    $email = trim((string) ($_POST['email'] ?? ''));
                    $password = $_POST['new_password'] ?? '';
                    $role = $_POST['role'] ?? '';
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                        throw new InvalidArgumentException('Enter a valid administrator email address.');
                    if ($password !== '' && (!is_string($password) || strlen($password) < 8))
                        throw new InvalidArgumentException('New passwords must be at least 8 characters long.');
                    if (!in_array($role, ['admin', 'super_admin'], true))
                        throw new InvalidArgumentException('Choose a valid account role.');
                    if ($target['role'] === 'super_admin' && $role === 'admin' && (int) $db->query("SELECT COUNT(*) FROM admins WHERE role = 'super_admin'")->fetchColumn() <= 1)
                        throw new InvalidArgumentException('At least one super administrator must remain active.');
                    $duplicate = $db->prepare('SELECT admin_id FROM admins WHERE email = :email AND admin_id <> :admin_id LIMIT 1');
                    $duplicate->execute([':email' => $email, ':admin_id' => $targetId]);
                    if ($duplicate->fetch())
                        throw new InvalidArgumentException('That email address is already used by another administrator.');
                    $update = $db->prepare('UPDATE admins SET email = :email, password = :password, role = :role WHERE admin_id = :admin_id');
                    $update->execute([
                        ':email' => $email,
                        ':password' => $password === '' ? $target['password'] : password_hash($password, PASSWORD_DEFAULT),
                        ':role' => $role,
                        ':admin_id' => $targetId,
                    ]);
                    $_SESSION['settings_message'] = 'The administrator account was updated.';
                }
            }
            header('Location: settings.php');
            exit;
        }

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
    if ($isSuperAdmin) {
        $roleAccounts = $db->query('SELECT admin_id, email, role, created_at FROM admins ORDER BY email')->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (InvalidArgumentException $error) {
    $settingsError = $error->getMessage();
} catch (Throwable $error) {
    error_log('Admin settings: ' . $error->getMessage());
    $settingsError = 'The account could not be updated. Please try again.';
}

$escape = [View::class, 'escape'];
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
            <input type="hidden" name="action" value="account_update"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['settings_csrf']) ?>">
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
            <div class="settings-status"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><div><strong><?= $admin['role'] === 'super_admin' ? 'Super administrator access' : 'Administrator access' ?></strong><span>Protected account</span></div></div>
            <dl><div><dt>Signed-in email</dt><dd><?= $escape($admin['email']) ?></dd></div><div><dt>Role</dt><dd><?= $admin['role'] === 'super_admin' ? 'Super admin' : 'Admin' ?></dd></div><div><dt>Account created</dt><dd><?= $admin['created_at'] ? $escape(date('F j, Y', strtotime($admin['created_at']))) : 'Unavailable' ?></dd></div></dl>
            <?php if ($isSuperAdmin): ?><a class="ov-button secondary settings-super-login" href="../../app/Controllers/logout.php?super_admin=1"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Login as super admin</a><?php endif; ?>
            <p class="settings-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Sign out after changing your password on a shared computer.</p>
        </div>
    </aside>
</div>
<?php if ($isSuperAdmin): ?>
<section class="panel role-management">
    <div class="panel-header"><div><h3>Administrator accounts</h3><span>Add, edit, delete, and change administrator access.</span></div><button class="submit-button" type="button" id="open-admin-add"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add admin</button></div>
    <div class="table-wrap"><table><thead><tr><th>Email</th><th>Role</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($roleAccounts as $account): $isCurrentAccount = (int) $account['admin_id'] === (int) $_SESSION['admin_id']; ?><tr><td><strong><?= $escape($account['email']) ?></strong></td><td><?= $account['role'] === 'super_admin' ? 'Super admin' : 'Admin' ?></td><td><div class="admin-actions"><button class="icon-button edit-admin-button" type="button" title="<?= $isCurrentAccount ? 'Edit your account above' : 'Edit ' . $account['email'] ?>" aria-label="<?= $isCurrentAccount ? 'Edit your account above' : 'Edit ' . $account['email'] ?>" data-admin-id="<?= (int) $account['admin_id'] ?>" data-admin-email="<?= $escape($account['email']) ?>" data-admin-role="<?= $escape($account['role']) ?>"<?= $isCurrentAccount ? ' disabled' : '' ?>><i class="fa-solid fa-pen" aria-hidden="true"></i></button><form class="delete-admin-form" method="post" action="settings.php" data-admin-email="<?= $escape($account['email']) ?>"><input type="hidden" name="action" value="delete_admin"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['settings_csrf']) ?>"><input type="hidden" name="admin_id" value="<?= (int) $account['admin_id'] ?>"><input type="password" name="current_password" class="visually-hidden-input" aria-label="Your password" autocomplete="current-password"><button class="icon-button danger-button" type="submit" title="<?= $isCurrentAccount ? 'You cannot delete your own account' : 'Delete ' . $account['email'] ?>" aria-label="<?= $isCurrentAccount ? 'You cannot delete your own account' : 'Delete ' . $account['email'] ?>"<?= $isCurrentAccount ? ' disabled' : '' ?>><i class="fa-solid fa-trash" aria-hidden="true"></i></button></form></div></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<dialog class="admin-edit-dialog" id="admin-add-dialog" aria-labelledby="admin-add-title">
    <form class="admin-edit-form" method="post" action="settings.php">
        <div class="dialog-heading"><div><h2 id="admin-add-title">Add administrator</h2><p>Create a new administrator account and assign its access level.</p></div><button class="dialog-close" type="button" data-close-admin-add aria-label="Close add administrator dialog">&times;</button></div>
        <input type="hidden" name="action" value="add_admin"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['settings_csrf']) ?>">
        <label>Email address<input type="email" name="email" autocomplete="email" required></label>
        <label>Temporary password<input type="password" name="new_password" minlength="8" autocomplete="new-password" required></label>
        <label>Role<select name="role"><option value="admin">Admin</option><option value="super_admin">Super admin</option></select></label>
        <label>Your password<input type="password" name="current_password" autocomplete="current-password" required></label>
        <div class="admin-edit-actions"><button class="ov-button secondary" type="button" data-close-admin-add>Cancel</button><button class="submit-button" type="submit"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add admin</button></div>
    </form>
</dialog>
<dialog class="admin-edit-dialog" id="admin-edit-dialog" aria-labelledby="admin-edit-title">
    <form class="admin-edit-form" method="post" action="settings.php">
        <div class="dialog-heading"><div><h2 id="admin-edit-title">Edit administrator</h2><p>Update this administrator's access and sign-in details.</p></div><button class="dialog-close" type="button" data-close-admin-dialog aria-label="Close edit dialog">&times;</button></div>
        <input type="hidden" name="action" value="edit_admin"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['settings_csrf']) ?>"><input type="hidden" name="admin_id" id="edit-admin-id">
        <label>Email address<input type="email" name="email" id="edit-admin-email" required></label>
        <label>New password <span>(optional)</span><input type="password" name="new_password" minlength="8" autocomplete="new-password"></label>
        <label>Role<select name="role" id="edit-admin-role"><option value="admin">Admin</option><option value="super_admin">Super admin</option></select></label>
        <label>Your password<input type="password" name="current_password" autocomplete="current-password" required></label>
        <div class="admin-edit-actions"><button class="ov-button secondary" type="button" data-close-admin-dialog>Cancel</button><button class="submit-button" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Save changes</button></div>
    </form>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const addDialog = document.getElementById('admin-add-dialog');
    const dialog = document.getElementById('admin-edit-dialog');
    if (!addDialog || !dialog) return;
    document.getElementById('open-admin-add').addEventListener('click', event => { addDialog.showModal(); window.SBCISModalOrigin?.apply(addDialog, event.currentTarget); });
    addDialog.querySelectorAll('[data-close-admin-add]').forEach(button => button.addEventListener('click', () => addDialog.close()));
    addDialog.addEventListener('click', event => { if (event.target === addDialog) addDialog.close(); });
    document.querySelectorAll('.edit-admin-button').forEach(button => button.addEventListener('click', event => {
        document.getElementById('edit-admin-id').value = button.dataset.adminId;
        document.getElementById('edit-admin-email').value = button.dataset.adminEmail;
        document.getElementById('edit-admin-role').value = button.dataset.adminRole;
        dialog.showModal();
        window.SBCISModalOrigin?.apply(dialog, event.currentTarget);
    }));
    dialog.querySelectorAll('[data-close-admin-dialog]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
});
</script>
<?php endif; ?>
