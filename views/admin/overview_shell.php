<?php
// Shared navigation for the dashboard and export workspace.
if (!isset($title, $activePage, $escape) || ($_SESSION['admin_logged_in'] ?? false) !== true) {
    http_response_code(404); exit;
}

?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $escape($title) ?> | Southern Leyte SBCIS</title>
<link rel="icon" href="../../src/images/logo.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../../src/css/admin_dashb.css"><link rel="stylesheet" href="../../src/css/overview.css"><link rel="stylesheet" href="../../src/css/admin_simple.css">
<?= $extraHead ?? '' ?>
<script src="../../src/js/overview.js" defer></script></head><body class="overview-page">
<?php $sidebarPage = $activePage; require __DIR__ . '/sidebar.php'; ?>
<div class="main"><header class="topbar"><div class="topbar-title"><button class="mobile-menu" id="mobileMenu" aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="false"><span aria-hidden="true">☰</span></button><div><h1><?= $escape($title) ?></h1><p>Southern Leyte / Administration</p></div></div><a class="ov-button secondary" href="../../index.php">Public site ↗</a></header>
<main class="content ov-content">
