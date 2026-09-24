<?php
// Shared navigation for the dashboard and export workspace.
if (!isset($title, $activePage, $escape) || ($_SESSION['admin_logged_in'] ?? false) !== true) {
    http_response_code(404);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($title) ?> | Southern Leyte SBCIS</title>
    <link rel="icon" href="../../src/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../../src/css/admin_dashb.css">
    <link rel="stylesheet" href="../../src/css/overview.css">
    <link rel="stylesheet" href="../../src/css/admin_simple.css?v=<?= filemtime(__DIR__ . '/../../src/css/admin_simple.css') ?>">
    <link rel="stylesheet" href="../../src/css/alerts.css?v=<?= filemtime(__DIR__ . '/../../src/css/alerts.css') ?>">
    <script src="../../src/js/modal-origin.js?v=<?= filemtime(__DIR__ . '/../../src/js/modal-origin.js') ?>"></script>
    <?= $extraHead ?? '' ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5"></script>
    <script src="../../src/js/toast.js"></script>
    <script src="../../src/js/loading-state.js?v=<?= filemtime(__DIR__ . '/../../src/js/loading-state.js') ?>"></script>
    <script src="../../src/js/admin_feedback.js" defer></script>
    <script src="../../src/js/overview.js" defer></script>
</head>

<body class="overview-page<?= isset($bodyClass) && $bodyClass !== '' ? ' ' . $escape($bodyClass) : '' ?>">
    <?php $sidebarPage = $activePage;
    require __DIR__ . '/sidebar.php'; ?>
    <div class="main">
        <header class="topbar">
            <div class="topbar-title"><button class="mobile-menu" id="mobileMenu" aria-label="Toggle navigation"
                    aria-controls="sidebar" aria-expanded="false"><span aria-hidden="true">&#9776;</span></button>
                <div>
                    <h1><?= $escape($title) ?></h1>
                    <p><?= $escape($subtitle ?? 'Southern Leyte / Administration') ?></p>
                </div>
            </div>
                <div class="topbar-actions"><?php if ($activePage === 'admin_dashboard.php'): ?><div class="dashboard-clock" aria-label="Current date and time">
                    <div class="dashboard-clock-item"><i class="fa-regular fa-calendar" aria-hidden="true"></i><span><small>Date</small><time data-live-date>Loading date</time></span></div>
                    <div class="dashboard-clock-item"><i class="fa-regular fa-clock" aria-hidden="true"></i><span><small>Local time</small><time data-live-time>--:--</time></span></div>
                </div><?php $headerEmail = (string) ($_SESSION['admin_email'] ?? 'administrator'); $headerUsername = strstr($headerEmail, '@', true) ?: $headerEmail; $headerRole = (string) ($_SESSION['admin_role'] ?? 'admin'); $headerRoleLabel = $headerRole === 'super_admin' ? 'superadmin' : 'admin'; ?><span class="admin-panel-indicator"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span><strong><?= $escape($headerUsername) ?></strong><small><?= $escape($headerRoleLabel) ?></small></span></span><?php else: ?><?php foreach (($topbarActions ?? []) as $action): ?><a
                        class="ov-button <?= !empty($action['primary']) ? '' : 'secondary' ?>"
                        href="<?= $escape($action['href'] ?? '#') ?>"><?php if (!empty($action['icon'])): ?><i
                                class="fa-solid <?= $escape($action['icon']) ?>"
                        aria-hidden="true"></i><?php endif; ?><?= $escape($action['label'] ?? 'Open') ?></a><?php endforeach; ?><?php endif; ?></div>
        </header>
        <main class="content ov-content">
