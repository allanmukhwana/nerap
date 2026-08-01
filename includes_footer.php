<?php
/**
 * =========================================================================
 * includes_footer.php — shared footer + bottom mobile tab bar + scripts
 * for PUBLIC pages. Included at the bottom of every public page.
 * =========================================================================
 */
?>
  <!-- =====================  FOOTER  ===================== -->
  <footer class="bg-white border-top mt-5 py-4 d-none d-md-block">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="d-flex align-items-center gap-2">
        <img src="<?= SITE_LOGO ?>" alt="logo" style="height:26px" onerror="this.style.display='none'">
        <span class="fw-bold text-primary-nerap"><?= e(SITE_NAME) ?></span>
        <span class="text-muted small ms-2"><?= e(SITE_TAGLINE) ?></span>
      </div>
      <div class="d-flex align-items-center gap-3">
        <a href="guide.php" class="btn btn-outline-nerap btn-sm"><i class="fa-solid fa-circle-info me-1"></i> Testing Guide</a>
        <div class="small text-muted">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. Built with Vanilla PHP, Bootstrap &amp; jQuery.</div>
      </div>
    </div>
  </footer>

  <!-- =====================  MOBILE BOTTOM TAB BAR (native app feel)  ===================== -->
  <nav class="mobile-tabbar d-flex">
    <a href="index.php" class="tab-item"><i class="fa-solid fa-map-location-dot"></i>Map</a>
    <a href="report.php" class="tab-item"><i class="fa-solid fa-file-circle-plus"></i>Report</a>
    <a href="https://wa.me/<?= e(ltrim(WA_NUMBER,'+')) ?>" target="_blank" class="tab-item"><i class="fa-brands fa-whatsapp"></i>WhatsApp</a>
    <a href="subscribe.php" class="tab-item"><i class="fa-solid fa-bell"></i>Alerts</a>
    <a href="auth_login.php" class="tab-item"><i class="fa-solid fa-user-shield"></i>Admin</a>
    <a href="guide.php" target="_blank" class="tab-item"><i class="fa-solid fa-circle-info"></i>Guide</a>
  </nav>

  <div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:2000"></div>

  <!-- Floating Testing Guide button (always visible on public pages) -->
  <a href="guide.php" class="btn btn-nerap-secondary rounded-circle position-fixed d-flex align-items-center justify-content-center shadow-nerap"
     style="width:52px;height:52px;bottom:calc(80px + var(--safe-bottom));right:16px;z-index:1050;" title="Testing Guide">
    <i class="fa-solid fa-circle-info fs-5"></i>
  </a>

</body>
</html>
