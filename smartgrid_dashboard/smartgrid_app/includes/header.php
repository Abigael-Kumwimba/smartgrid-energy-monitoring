<?php
$pageTitle = $pageTitle ?? 'SmartGrid Energy';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?></title>
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
<aside class="sidebar">
    <div class="brand">
        <span class="brand-mark">SG</span>
        <div>
            <strong>SmartGrid</strong>
            <small>Energy Meter</small>
        </div>
    </div>

    <nav class="nav">
        <a class="<?= active_nav($activePage, 'dashboard'); ?>" href="dashboard.php">Dashboard</a>
        <a class="<?= active_nav($activePage, 'clients'); ?>" href="clients.php">Clients</a>
        <a class="<?= active_nav($activePage, 'compteurs'); ?>" href="compteurs.php">Compteurs</a>
        <a class="<?= active_nav($activePage, 'consommations'); ?>" href="consommations.php">Consommations</a>
        <a class="<?= active_nav($activePage, 'factures'); ?>" href="factures.php">Factures</a>
        <a class="<?= active_nav($activePage, 'alertes'); ?>" href="alertes_predictions.php">Alertes</a>
        <a class="<?= active_nav($activePage, 'predictions'); ?>" href="predictions.php">Predictions ML</a>
    </nav>
</aside>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">Prototype IoT local</p>
            <h1><?= e($pageTitle); ?></h1>
        </div>
        <div class="status-pill">API locale / MySQL</div>
    </header>
