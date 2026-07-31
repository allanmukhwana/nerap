<?php
/**
 * =========================================================================
 * includes_header.php — shared HTML head + top navbar for PUBLIC pages.
 * Included by: index.php, report.php, subscribe.php, facilities.php
 *
 * Expects (optional) $page_title to be set before including this file.
 * =========================================================================
 */
require_once __DIR__ . '/config.php';
$page_title = $page_title ?? SITE_NAME;
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
<title><?= e($page_title) ?></title>
<meta name="description" content="NERAP Cloud — real-time emergency resource intelligence: antivenom, blood, ICU beds and shelters located instantly via WhatsApp and a live map.">
<meta name="theme-color" content="<?= COLOR_PRIMARY ?>">
<link rel="icon" href="<?= SITE_LOGO ?>">
<link rel="apple-touch-icon" href="<?= SITE_LOGO ?>">
<link rel="manifest" href="manifest.json">

<!-- Google Font: Plus Jakarta Sans — clean, modern, highly legible -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<!-- Brand stylesheet -->
<link href="assets/style.css?v=1" rel="stylesheet">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 bundle (incl. Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<!-- Shared app helpers -->
<script src="assets/app.js?v=1"></script>
</head>
<body>

<!-- =====================  TOP NAVBAR  ===================== -->
<nav class="navbar navbar-expand-lg nerap-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="<?= SITE_LOGO ?>" alt="<?= e(SITE_NAME) ?> logo" onerror="this.style.display='none'">
      <span><?= e(SITE_NAME) ?></span>
    </a>
    <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <i class="fa-solid fa-bars text-white"></i>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <li class="nav-item"><a class="nav-link <?= $current_page==='index.php'?'active':'' ?>" href="index.php"><i class="fa-solid fa-map-location-dot me-1"></i> Live Map</a></li>
        <li class="nav-item"><a class="nav-link <?= $current_page==='report.php'?'active':'' ?>" href="report.php"><i class="fa-solid fa-file-circle-plus me-1"></i> Report Stock</a></li>
        <li class="nav-item"><a class="nav-link <?= $current_page==='subscribe.php'?'active':'' ?>" href="subscribe.php"><i class="fa-solid fa-bell me-1"></i> Get Alerts</a></li>
        <li class="nav-item"><a class="nav-link" target="_blank" href="https://wa.me/<?= e(ltrim(WA_NUMBER,'+')) ?>"><i class="fa-brands fa-whatsapp me-1"></i> Chat on WhatsApp</a></li>
        <li class="nav-item"><a class="btn btn-nerap-secondary btn-sm ms-lg-2 mt-2 mt-lg-0" href="auth_login.php"><i class="fa-solid fa-user-shield me-1"></i> Moderator Login</a></li>
      </ul>
    </div>
  </div>
</nav>
