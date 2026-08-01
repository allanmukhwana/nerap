<?php
/**
 * =========================================================================
 * guide.php — Standalone testing guide page for judges.
 * Contains the same content as the homepage modal, plus a Vimeo walkthrough.
 * =========================================================================
 */
require_once __DIR__ . '/config.php';
$page_title = 'Testing Guide — ' . SITE_NAME;
require_once __DIR__ . '/includes_header.php';
?>

<div class="container py-4" style="max-width: 900px;">

  <div class="nerap-card p-4 mb-4">
    <h2 class="fw-brand mb-1"><i class="fa-solid fa-circle-info text-secondary-nerap me-2"></i>NERAP Cloud — Judge Testing Guide</h2>
    <p class="text-muted mb-0">Everything you need to test the platform as an admin and as a user.</p>
  </div>
  <!-- Video Walkthrough -->
  <div class="nerap-card p-4 mb-4">
    <h5 class="fw-brand mb-3"><i class="fa-solid fa-play-circle text-secondary-nerap me-2"></i>Video Walkthrough</h5>
    <div class="ratio ratio-16x9 rounded-nerap overflow-hidden">
      <iframe src="https://player.vimeo.com/video/1214596511" title="NERAP Cloud Walkthrough" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
    </div>
    <p class="text-muted small mt-2 mb-0">Full system walkthrough — admin panel, public map, and WhatsApp flow.</p>
  </div>

  <!-- Demo Credentials -->
  <div class="nerap-card p-4 mb-4">
    <div class="alert alert-warning border-warning mb-0">
      <h6 class="fw-brand mb-2"><i class="fa-solid fa-key me-2"></i>Demo Credentials</h6>
      <table class="table table-sm table-borderless mb-0">
        <tr><td class="fw-bold text-nowrap" style="width:160px;">Admin Email</td><td><code>admin@nerap.cloud</code></td></tr>
        <tr><td class="fw-bold text-nowrap">Admin Password</td><td><code>nerap@2026</code></td></tr>
        <tr><td class="fw-bold text-nowrap">WhatsApp Number</td><td><code>+254738162126</code></td></tr>
      </table>
    </div>
  </div>

  <!-- Admin Testing -->
  <div class="nerap-card p-4 mb-4">
    <h5 class="fw-brand text-primary-nerap mb-3"><i class="fa-solid fa-user-shield me-2"></i>Testing as Admin</h5>
    <ol>
      <li class="mb-2"><strong>Login</strong>: Click <em>"Moderator Login"</em> in the top navbar (or visit <code>auth_login.php</code>). Enter the admin credentials above.</li>
      <li class="mb-2"><strong>Dashboard</strong>: After login you'll see the admin dashboard with live counters and charts mirroring the public homepage.</li>
      <li class="mb-2"><strong>Facilities CRUD</strong>: Go to <em>Facilities</em> in the sidebar. Add a new facility (e.g. "Kenyatta National Hospital" with coordinates latitude <code>-1.301</code>, longitude <code>36.807</code>). Edit or delete existing facilities. Changes appear instantly on the public map.</li>
      <li class="mb-2"><strong>Resource Types CRUD</strong>: Go to <em>Resource Types</em>. Add a new resource (e.g. category "Oxygen", subtype "Cylinders", synonyms "oxygen,cylinders,oksijeni"). These resources appear in the public filter dropdown and WhatsApp menu.</li>
      <li class="mb-2"><strong>Link Resources to Facilities</strong>: In <em>Facilities</em>, click a facility and assign resources with a stock status (Confirmed / Low / Out / Unverified). This populates the map markers and WhatsApp search results.</li>
      <li class="mb-2"><strong>Moderation Queue</strong>: Go to <em>Moderation Queue</em>. Any stock reports submitted via WhatsApp or the web form appear here as <em>pending</em>. Approve or reject them. Approving a report updates the facility's stock status and triggers instant alert notifications to subscribers.</li>
      <li class="mb-2"><strong>Subscribers</strong>: Go to <em>Subscribers</em> to view all users who subscribed to alerts (via WhatsApp or the web form). You can filter by channel, region, or resource.</li>
      <li class="mb-2"><strong>Broadcast Alert</strong>: Go to <em>Broadcast Alert</em>. Compose a message, select a region and/or resource scope, and send it to all matching subscribers via WhatsApp and/or email.</li>
      <li class="mb-2"><strong>Admin Users</strong>: Go to <em>Admin Users</em> (super_admin only). Add new moderators or admins. Assign roles and regions.</li>
    </ol>
  </div>

  <!-- Public/User Testing -->
  <div class="nerap-card p-4 mb-4">
    <h5 class="fw-brand text-primary-nerap mb-3"><i class="fa-solid fa-globe me-2"></i>Testing as a Public User (Web)</h5>
    <ol>
      <li class="mb-2"><strong>Live Map</strong>: On the homepage, use the filter bar to filter by resource type, region, or stock status. Click map markers for facility details, phone numbers, and directions.</li>
      <li class="mb-2"><strong>Facility List</strong>: The right sidebar shows facility cards. Click a card to pan/zoom the map to that facility.</li>
      <li class="mb-2"><strong>Real-Time Analytics</strong>: Scroll down to see Chart.js dashboards — stock trends, status breakdown doughnut, regional comparison, and alert activity. These update every 30 seconds via AJAX polling.</li>
      <li class="mb-2"><strong>Report Stock</strong>: Click <em>"Report Stock"</em> in the navbar. Select a facility (or type a new name), choose a resource, and set the stock status. Your report goes to the moderation queue for admin approval.</li>
      <li class="mb-2"><strong>Subscribe to Alerts</strong>: Click <em>"Get Alerts"</em> in the navbar. Choose WhatsApp, email, or both. Optionally filter by region and resource. You'll be notified when stock status changes.</li>
    </ol>
  </div>

  <!-- WhatsApp Testing -->
  <div class="nerap-card p-4 mb-4">
    <h5 class="fw-brand text-primary-nerap mb-3"><i class="fa-brands fa-whatsapp me-2"></i>Testing via WhatsApp</h5>
    <p>Send a WhatsApp message to <code>+254738162126</code> and follow the numbered menu:</p>
    <ol>
      <li class="mb-2"><strong>Main Menu</strong>: Send <code>MENU</code> or <code>HI</code>. You'll receive:
        <pre class="bg-light p-2 rounded mt-1 mb-2" style="font-size:.85rem;">👋 Welcome to NERAP Cloud!

0️⃣ Search Resources
1️⃣ Report Stock Update
2️⃣ Subscribe to Alerts

Reply with a number:</pre>
      </li>
      <li class="mb-2"><strong>Search Resources</strong>: Reply <code>0</code>. You'll get a numbered list of all resource types. Reply with a number (e.g. <code>1</code>) to see which facilities stock that resource, with status, phone, and Google Maps links.</li>
      <li class="mb-2"><strong>Report Stock</strong>: Reply <code>1</code>. Select a facility from the numbered list (or press <code>0</code> to type a custom name). Then select a resource from the numbered list. Then choose stock status: <code>1</code> = Confirmed, <code>2</code> = Low, <code>3</code> = Out. Your report enters the moderation queue.</li>
      <li class="mb-2"><strong>Subscribe to Alerts</strong>: Reply <code>2</code>. Select a resource from the numbered list. You're now subscribed — you'll receive a WhatsApp message when that resource's stock status changes at any facility.</li>
      <li class="mb-2"><strong>Reset Anytime</strong>: Send <code>MENU</code> or <code>RESET</code> at any point to return to the main menu.</li>
    </ol>
  </div>

  <!-- Tech Stack -->
  <div class="nerap-card p-4 mb-4">
    <h5 class="fw-brand text-primary-nerap mb-3"><i class="fa-solid fa-layer-group me-2"></i>Tech Stack</h5>
    <ul class="mb-0">
      <li class="mb-1"><strong>Backend</strong>: Vanilla PHP (no framework), MySQL, flat file structure</li>
      <li class="mb-1"><strong>Frontend</strong>: Bootstrap 5, jQuery, Font Awesome, Chart.js, Google Maps API</li>
      <li class="mb-1"><strong>Messaging</strong>: WhatsApp Cloud API-compatible provider, Brevo transactional email (REST API)</li>
      <li class="mb-1"><strong>Geospatial</strong>: Bounding-box pre-filter + Haversine distance formula for facility search</li>
      <li class="mb-1"><strong>State Machine</strong>: MySQL row-locked sessions (<code>wa_sessions</code>) for multi-step WhatsApp conversations</li>
      <li class="mb-1"><strong>PWA</strong>: Manifest + theme color for mobile-native app feel</li>
    </ul>
  </div>

  <div class="text-center mb-4">
    <a href="index.php" class="btn btn-nerap-primary btn-lg"><i class="fa-solid fa-arrow-left me-2"></i>Back to Homepage</a>
  </div>

</div>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
