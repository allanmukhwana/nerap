<?php
/**
 * =========================================================================
 * admin_header.php — shared HTML head + sidebar + topbar for ADMIN pages.
 * Included by: admin_dashboard.php, admin_facilities.php, admin_resources.php,
 *              admin_moderation.php, admin_subscribers.php, admin_broadcast.php
 *
 * Calls require_admin() so any admin_*.php page is automatically protected.
 * Expects (optional) $page_title before including.
 * =========================================================================
 */
require_once __DIR__ . '/config.php';
require_admin();
$page_title = $page_title ?? 'Admin — ' . SITE_NAME;
$current_page = basename($_SERVER['SCRIPT_NAME']);

// Sidebar navigation items — single source of truth so links stay consistent.
$admin_nav = [
    ['href' => 'admin_dashboard.php',   'icon' => 'fa-chart-line',        'label' => 'Dashboard'],
    ['href' => 'admin_facilities.php',  'icon' => 'fa-hospital',          'label' => 'Facilities'],
    ['href' => 'admin_resources.php',   'icon' => 'fa-box-open',          'label' => 'Resource Types'],
    ['href' => 'admin_moderation.php',  'icon' => 'fa-clipboard-check',   'label' => 'Moderation Queue'],
    ['href' => 'admin_subscribers.php', 'icon' => 'fa-users',             'label' => 'Subscribers'],
    ['href' => 'admin_broadcast.php',   'icon' => 'fa-tower-broadcast',   'label' => 'Broadcast Alert'],
    ['href' => 'admin_users.php',       'icon' => 'fa-user-shield',       'label' => 'Admin Users'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
<title><?= e($page_title) ?></title>
<meta name="theme-color" content="<?= COLOR_PRIMARY ?>">
<link rel="icon" href="<?= SITE_LOGO ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="assets/style.css?v=1" rel="stylesheet">
</head>
<body class="bg-light">

<!-- =====================  SIDEBAR  ===================== -->
<aside class="admin-sidebar" id="adminSidebar">
  <div class="brand">
    <img src="<?= SITE_LOGO ?>" alt="logo" onerror="this.style.display='none'">
    <span><?= e(SITE_NAME) ?></span>
  </div>
  <nav class="nav flex-column">
    <?php foreach ($admin_nav as $item): ?>
      <a class="nav-link <?= $current_page === $item['href'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
        <i class="fa-solid <?= $item['icon'] ?>"></i><?= e($item['label']) ?>
      </a>
    <?php endforeach; ?>
    <hr class="border-secondary opacity-25 my-2">
    <a class="nav-link" href="auth_logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
  </nav>
</aside>

<!-- =====================  MAIN CONTENT  ===================== -->
<div class="admin-content">
  <div class="admin-topbar rounded-nerap mb-3">
    <div class="d-flex align-items-center gap-3">
      <button id="adminSidebarToggle" class="btn btn-outline-nerap d-lg-none btn-sm"><i class="fa-solid fa-bars"></i></button>
      <h5 class="mb-0 fw-brand"><?= e($page_title) ?></h5>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="small text-muted d-none d-sm-inline"><?= e($_SESSION['admin_name'] ?? '') ?> (<?= e($_SESSION['admin_role'] ?? '') ?>)</span>
      <div class="rounded-circle bg-secondary-soft text-secondary-nerap d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
        <i class="fa-solid fa-user"></i>
      </div>
    </div>
  </div>
