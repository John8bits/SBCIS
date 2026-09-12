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
    <link rel="stylesheet" href="../../src/css/admin_simple.css">
    <?= $extraHead ?? '' ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../src/js/toast.js"></script>
    <script src="../../src/js/admin_feedback.js" defer></script>
    <script src="../../src/js/overview.js" defer></script>
</head>

<body class="overview-page">
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
            <div class="topbar-actions"><?php foreach (($topbarActions ?? []) as $action): ?><a
                        class="ov-button <?= !empty($action['primary']) ? '' : 'secondary' ?>"
                        href="<?= $escape($action['href'] ?? '#') ?>"><?php if (!empty($action['icon'])): ?><i
                                class="fa-solid <?= $escape($action['icon']) ?>"
                                aria-hidden="true"></i><?php endif; ?><?= $escape($action['label'] ?? 'Open') ?></a><?php endforeach; ?><a
                    class="ov-button secondary public-site-link" href="../../index.php">Public site <span
                        aria-hidden="true">&#8599;</span></a></div>
        </header>
        <main class="content ov-content">