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
      <div class="small text-muted">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. Built with Vanilla PHP, Bootstrap &amp; jQuery.</div>
    </div>
  </footer>

  <!-- =====================  MOBILE BOTTOM TAB BAR (native app feel)  ===================== -->
  <nav class="mobile-tabbar d-flex">
    <a href="index.php" class="tab-item"><i class="fa-solid fa-map-location-dot"></i>Map</a>
    <a href="report.php" class="tab-item"><i class="fa-solid fa-file-circle-plus"></i>Report</a>
    <a href="https://wa.me/<?= e(ltrim(WA_NUMBER,'+')) ?>" target="_blank" class="tab-item"><i class="fa-brands fa-whatsapp"></i>WhatsApp</a>
    <a href="subscribe.php" class="tab-item"><i class="fa-solid fa-bell"></i>Alerts</a>
    <a href="auth_login.php" class="tab-item"><i class="fa-solid fa-user-shield"></i>Admin</a>
  </nav>

  <div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:2000"></div>

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <!-- Bootstrap 5 bundle (incl. Popper) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <!-- Shared app helpers -->
  <script src="assets/app.js?v=1"></script>
</body>
</html>
