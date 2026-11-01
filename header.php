<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = $pageTitle ?? 'Inventory POS';
$active    = $active ?? '';
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(STORE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="<?= e($bodyClass) ?>">
<nav class="topbar">
    <a class="brand" href="<?= BASE_URL ?>/index.php"><i class="bi bi-basket3-fill"></i> <?= e(STORE_NAME) ?></a>
    <div class="nav-links">
        <a href="<?= BASE_URL ?>/index.php"     class="<?= $active === 'pos' ? 'active' : '' ?>"><i class="bi bi-upc-scan"></i> POS</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="<?= BASE_URL ?>/products.php"  class="<?= $active === 'products' ? 'active' : '' ?>"><i class="bi bi-box-seam"></i> Products</a>
    </div>
    <div class="clock" id="clock"></div>
</nav>
