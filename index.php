<?php
/**
 * =========================================================================
 * index.php — Public homepage: hero, live counters, interactive Google Map,
 * filters, Chart.js analytics, and a mobile-friendly facility card list.
 * This is the "Live Interactive Resource Map" + "Real-Time Analytics
 * Dashboard" described in the README, built with Bootstrap + jQuery + Chart.js.
 * =========================================================================
 */

require_once __DIR__ . '/config.php';
$page_title = SITE_NAME . ' — Live Emergency Resource Map';
require_once __DIR__ . '/includes_header.php';

// Resource types for the filter dropdown (small table, safe to load directly).
$resources = db()->query("SELECT id, category, subtype FROM resources ORDER BY category, subtype")->fetch_all(MYSQLI_ASSOC);
$regions = db()->query("SELECT DISTINCT region FROM facilities WHERE status='active' ORDER BY region")->fetch_all(MYSQLI_ASSOC);
?>

<!-- =====================  HERO  ===================== -->
<section class="hero-section">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <h1 class="display-6 fw-brand mb-2">Find life-saving resources in seconds.</h1>
        <p class="lead mb-3">Antivenom, blood units, ICU beds, shelters — verified in real time by facilities across the country. Search on WhatsApp or explore the live map below.</p>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-nerap-secondary btn-lg" target="_blank" href="https://wa.me/<?= e(ltrim(WA_NUMBER,'+')) ?>"><i class="fa-brands fa-whatsapp me-2"></i>Search on WhatsApp</a>
          <a class="btn btn-outline-light btn-lg" href="report.php"><i class="fa-solid fa-file-circle-plus me-2"></i>Report Stock</a>
          <button class="btn btn-outline-light btn-lg" data-bs-toggle="modal" data-bs-target="#guideModal"><i class="fa-solid fa-circle-info me-2"></i>How to Test</button>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="row g-2">
          <div class="col-6">
            <div class="stat-tile"><div class="stat-icon mb-2"><i class="fa-solid fa-hospital"></i></div>
              <div class="stat-value count-up" data-target="0">0</div><div class="stat-label">Verified Facilities</div></div>
          </div>
          <div class="col-6">
            <div class="stat-tile"><div class="stat-icon mb-2"><i class="fa-solid fa-box-open"></i></div>
              <div class="stat-value count-up" data-target="0">0</div><div class="stat-label">Resources Tracked</div></div>
          </div>
          <div class="col-6">
            <div class="stat-tile"><div class="stat-icon mb-2"><i class="fa-solid fa-bell"></i></div>
              <div class="stat-value count-up" data-target="0" id="statAlerts24h">0</div><div class="stat-label">Alerts (24h)</div></div>
          </div>
          <div class="col-6">
            <div class="stat-tile"><div class="stat-icon mb-2"><i class="fa-solid fa-clock"></i></div>
              <div class="stat-value" id="statLastUpdate" style="font-size:1rem;">--</div><div class="stat-label">Last Updated</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="container mt-4">

  <!-- =====================  FILTERS  ===================== -->
  <div class="nerap-card p-3 mb-3">
    <div class="row g-2 align-items-center">
      <div class="col-md-3 col-6">
        <select id="fResource" class="form-select">
          <option value="">All Resources</option>
          <?php foreach ($resources as $r): ?>
            <option value="<?= (int)$r['id'] ?>"><?= e($r['category']) ?><?= $r['subtype'] ? ' - '.e($r['subtype']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 col-6">
        <select id="fRegion" class="form-select">
          <option value="">All Regions</option>
          <?php foreach ($regions as $r): ?>
            <option value="<?= e($r['region']) ?>"><?= e($r['region']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 col-6">
        <select id="fStatus" class="form-select">
          <option value="">Any Status</option>
          <option value="confirmed">🟢 Confirmed</option>
          <option value="low">🟡 Low Stock</option>
          <option value="out">🔴 Out of Stock</option>
          <option value="unverified">⚫ Unverified</option>
        </select>
      </div>
      <div class="col-md-3 col-6">
        <input type="text" id="fSearch" class="form-control" placeholder="Search facility name...">
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- =====================  LIVE MAP  ===================== -->
    <div class="col-lg-8">
      <div class="nerap-card p-2">
        <div id="nerap-map"></div>
      </div>
    </div>

    <!-- =====================  FACILITY LIST (mobile-first cards)  ===================== -->
    <div class="col-lg-4">
      <div class="nerap-card p-3" style="max-height:480px; overflow-y:auto;">
        <h6 class="fw-brand mb-3">Nearby Facilities</h6>
        <div id="facilityList"><div class="text-muted small">Loading...</div></div>
      </div>
    </div>
  </div>

  <!-- =====================  ANALYTICS DASHBOARD  ===================== -->
  <h4 class="fw-brand mt-5 mb-3"><i class="fa-solid fa-chart-line text-secondary-nerap me-2"></i>Real-Time Analytics</h4>
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="nerap-card p-3"><h6 class="fw-brand">Stock Trend (14 days)</h6><canvas id="chartTrend" height="180"></canvas></div>
    </div>
    <div class="col-lg-6">
      <div class="nerap-card p-3"><h6 class="fw-brand">Regional Coverage</h6><canvas id="chartDoughnut" height="180"></canvas></div>
    </div>
    <div class="col-lg-6">
      <div class="nerap-card p-3"><h6 class="fw-brand">Facility Comparison by Region</h6><canvas id="chartBar" height="180"></canvas></div>
    </div>
    <div class="col-lg-6">
      <div class="nerap-card p-3"><h6 class="fw-brand">Alert Activity</h6><canvas id="chartAlerts" height="180"></canvas></div>
    </div>
  </div>
</div>

<!-- Google Maps JavaScript API -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?= e(GOOGLE_MAPS_API_KEY) ?>&libraries=visualization&callback=initNerapMap" async defer></script>
<script>
/**
 * index.js (inline) — map + filters + AJAX polling + Chart.js wiring.
 * Kept inline (not a separate file) since it is tightly coupled to this
 * single page's DOM elements and PHP-rendered filter options.
 */
let map, markers = [], heatmap, charts = {};

function initNerapMap() {
    map = new google.maps.Map(document.getElementById('nerap-map'), {
        center: { lat: -1.286389, lng: 36.817223 }, // Nairobi
        zoom: 6,
        mapId: 'NERAP_MAP',
    });
    loadFacilities();
}
window.initNerapMap = initNerapMap;

function currentFilters() {
    return {
        resource_id: $('#fResource').val(),
        region: $('#fRegion').val(),
        status: $('#fStatus').val(),
        q: $('#fSearch').val(),
    };
}

function loadFacilities() {
    $.ajax({ url: 'api_facilities.php', data: currentFilters(), dataType: 'json' })
        .done(function (res) {
            if (!res || res.status !== 200) return;
            renderMarkers(res.data);
            renderFacilityList(res.data);
        });
}

function renderMarkers(facilities) {
    markers.forEach(m => m.setMap(null));
    markers = [];
    facilities.forEach(function (f) {
        const marker = new google.maps.Marker({
            position: { lat: f.lat, lng: f.lng },
            map: map,
            title: f.name,
            icon: { path: google.maps.SymbolPath.CIRCLE, scale: 9, fillColor: f.color, fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 },
        });
        const info = new google.maps.InfoWindow({ content: buildInfoWindow(f) });
        marker.addListener('click', () => info.open(map, marker));
        markers.push(marker);
    });
}

function buildInfoWindow(f) {
    let resourcesHtml = f.resources.map(r =>
        `<div class="d-flex justify-content-between small py-1 border-bottom"><span>${r.name}</span><span class="badge-status status-${r.status}">${r.status}</span></div>`
    ).join('') || '<div class="text-muted small">No resource data yet.</div>';
    return `<div style="min-width:220px">
        <div class="fw-bold mb-1">${f.name}</div>
        <div class="small text-muted mb-2">${f.address || f.region}</div>
        ${resourcesHtml}
        <div class="d-flex gap-2 mt-2">
          <a href="${f.maps_link}" target="_blank" class="btn btn-sm btn-nerap-primary">Directions</a>
          ${f.phone ? `<a href="tel:${f.phone}" class="btn btn-sm btn-outline-nerap">Call</a>` : ''}
        </div></div>`;
}

function renderFacilityList(facilities) {
    if (!facilities.length) { $('#facilityList').html('<div class="text-muted small">No facilities match these filters.</div>'); return; }
    let html = '';
    facilities.forEach(function (f) {
        html += `<div class="facility-card nerap-card p-2 mb-2" onclick="map.panTo({lat:${f.lat},lng:${f.lng}}); map.setZoom(13);">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-bold small">${f.name}</div>
                <div class="text-muted" style="font-size:0.75rem">${f.region}</div>
              </div>
              <span class="badge-status status-${f.overall_status}"><span class="dot"></span>${f.overall_status}</span>
            </div>
        </div>`;
    });
    $('#facilityList').html(html);
}

function refreshDashboard() {
    $.ajax({ url: 'api_dashboard.php', dataType: 'json' }).done(function (res) {
        if (!res || res.status !== 200) return;
        $('.stat-value.count-up').eq(0).attr('data-target', res.counters.total_facilities);
        $('.stat-value.count-up').eq(1).attr('data-target', res.counters.total_resources);
        $('#statAlerts24h').attr('data-target', res.counters.active_alerts_24h);
        $('#statLastUpdate').text(new Date(res.counters.last_update).toLocaleTimeString());
        animateCounters(document);
        renderCharts(res);
    });
}

function renderCharts(res) {
    const brandGreen = '#1b693f', brandNavy = '#042238', warn = '#e0a800', danger = '#c0392b', grey = '#6c757d';

    if (charts.trend) charts.trend.destroy();
    charts.trend = new Chart(document.getElementById('chartTrend'), {
        type: 'line',
        data: { labels: res.trend_chart.labels, datasets: [{ label: 'Approved Reports', data: res.trend_chart.data, borderColor: brandGreen, backgroundColor: 'rgba(27,105,63,0.15)', tension: 0.35, fill: true }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    if (charts.doughnut) charts.doughnut.destroy();
    charts.doughnut = new Chart(document.getElementById('chartDoughnut'), {
        type: 'doughnut',
        data: { labels: ['Confirmed', 'Low', 'Out', 'Unverified'], datasets: [{ data: [res.status_breakdown.confirmed, res.status_breakdown.low, res.status_breakdown.out, res.status_breakdown.unverified], backgroundColor: [brandGreen, warn, danger, grey] }] },
    });

    if (charts.bar) charts.bar.destroy();
    charts.bar = new Chart(document.getElementById('chartBar'), {
        type: 'bar',
        data: { labels: res.region_chart.labels, datasets: [
            { label: 'Confirmed', data: res.region_chart.confirmed, backgroundColor: brandGreen },
            { label: 'Low', data: res.region_chart.low, backgroundColor: warn },
            { label: 'Out', data: res.region_chart.out, backgroundColor: danger },
        ]},
        options: { scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } }
    });

    if (charts.alerts) charts.alerts.destroy();
    charts.alerts = new Chart(document.getElementById('chartAlerts'), {
        type: 'bar',
        data: { labels: res.alert_chart.labels, datasets: [{ label: 'Alerts Sent', data: res.alert_chart.data, backgroundColor: brandNavy }] },
        options: { plugins: { legend: { display: false } } }
    });
}

$(function () {
    $('#fResource, #fRegion, #fStatus').on('change', loadFacilities);
    let searchTimer;
    $('#fSearch').on('input', function () { clearTimeout(searchTimer); searchTimer = setTimeout(loadFacilities, 400); });

    refreshDashboard();
    setInterval(refreshDashboard, 30000); // jQuery AJAX polling every 30s (README spec)
    setInterval(loadFacilities, 30000);
});
</script>

<!-- =====================  TESTING GUIDE MODAL  ===================== -->
<div class="modal fade" id="guideModal" tabindex="-1" aria-labelledby="guideModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary-nerap text-white">
        <h5 class="modal-title" id="guideModalLabel"><i class="fa-solid fa-circle-info me-2"></i>NERAP Cloud — Judge Testing Guide</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <!-- Video Walkthrough -->
        <div class="ratio ratio-16x9 rounded-nerap overflow-hidden mb-4">
          <iframe src="https://player.vimeo.com/video/1214596511" title="NERAP Cloud Walkthrough" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
        </div>

        <!-- Demo Credentials -->
        <div class="alert alert-warning border-warning">
          <h6 class="fw-brand mb-2"><i class="fa-solid fa-key me-2"></i>Demo Credentials</h6>
          <table class="table table-sm table-borderless mb-0">
            <tr><td class="fw-bold text-nowrap" style="width:140px;">Admin Email</td><td><code>admin@nerap.cloud</code></td></tr>
            <tr><td class="fw-bold text-nowrap">Admin Password</td><td><code>nerap@2026</code></td></tr>
            <tr><td class="fw-bold text-nowrap">WhatsApp Number</td><td><code>+254738162126</code></td></tr>
          </table>
        </div>

        <!-- Admin Testing -->
        <h6 class="fw-brand text-primary-nerap mt-4"><i class="fa-solid fa-user-shield me-2"></i>Testing as Admin</h6>
        <ol>
          <li><strong>Login</strong>: Click <em>"Moderator Login"</em> in the top navbar (or visit <code>auth_login.php</code>). Enter the admin credentials above.</li>
          <li><strong>Dashboard</strong>: After login you'll see the admin dashboard with live counters and charts mirroring the public homepage.</li>
          <li><strong>Facilities CRUD</strong>: Go to <em>Facilities</em> in the sidebar. Add a new facility (e.g. "Kenyatta National Hospital" with coordinates latitude <code>-1.301</code>, longitude <code>36.807</code>). Edit or delete existing facilities. Changes appear instantly on the public map.</li>
          <li><strong>Resource Types CRUD</strong>: Go to <em>Resource Types</em>. Add a new resource (e.g. category "Oxygen", subtype "Cylinders", synonyms "oxygen,cylinders,oksijeni"). These resources appear in the public filter dropdown and WhatsApp menu.</li>
          <li><strong>Link Resources to Facilities</strong>: In <em>Facilities</em>, click a facility and assign resources with a stock status (Confirmed / Low / Out / Unverified). This populates the map markers and WhatsApp search results.</li>
          <li><strong>Moderation Queue</strong>: Go to <em>Moderation Queue</em>. Any stock reports submitted via WhatsApp or the web form appear here as <em>pending</em>. Approve or reject them. Approving a report updates the facility's stock status and triggers instant alert notifications to subscribers.</li>
          <li><strong>Subscribers</strong>: Go to <em>Subscribers</em> to view all users who subscribed to alerts (via WhatsApp or the web form). You can filter by channel, region, or resource.</li>
          <li><strong>Broadcast Alert</strong>: Go to <em>Broadcast Alert</em>. Compose a message, select a region and/or resource scope, and send it to all matching subscribers via WhatsApp and/or email.</li>
          <li><strong>Admin Users</strong>: Go to <em>Admin Users</em> (super_admin only). Add new moderators or admins. Assign roles and regions.</li>
        </ol>

        <!-- Public/User Testing -->
        <h6 class="fw-brand text-primary-nerap mt-4"><i class="fa-solid fa-globe me-2"></i>Testing as a Public User (Web)</h6>
        <ol>
          <li><strong>Live Map</strong>: On the homepage, use the filter bar to filter by resource type, region, or stock status. Click map markers for facility details, phone numbers, and directions.</li>
          <li><strong>Facility List</strong>: The right sidebar shows facility cards. Click a card to pan/zoom the map to that facility.</li>
          <li><strong>Real-Time Analytics</strong>: Scroll down to see Chart.js dashboards — stock trends, status breakdown doughnut, regional comparison, and alert activity. These update every 30 seconds via AJAX polling.</li>
          <li><strong>Report Stock</strong>: Click <em>"Report Stock"</em> in the navbar. Select a facility (or type a new name), choose a resource, and set the stock status. Your report goes to the moderation queue for admin approval.</li>
          <li><strong>Subscribe to Alerts</strong>: Click <em>"Get Alerts"</em> in the navbar. Choose WhatsApp, email, or both. Optionally filter by region and resource. You'll be notified when stock status changes.</li>
        </ol>

        <!-- WhatsApp Testing -->
        <h6 class="fw-brand text-primary-nerap mt-4"><i class="fa-brands fa-whatsapp me-2"></i>Testing via WhatsApp</h6>
        <p>Send a WhatsApp message to <code>+254738162126</code> and follow the numbered menu:</p>
        <ol>
          <li><strong>Main Menu</strong>: Send <code>MENU</code> or <code>HI</code>. You'll receive:
            <pre class="bg-light p-2 rounded mt-1 mb-2" style="font-size:.85rem;">👋 Welcome to NERAP Cloud!

0️⃣ Search Resources
1️⃣ Report Stock Update
2️⃣ Subscribe to Alerts

Reply with a number:</pre>
          </li>
          <li><strong>Search Resources</strong>: Reply <code>0</code>. You'll get a numbered list of all resource types. Reply with a number (e.g. <code>1</code>) to see which facilities stock that resource, with status, phone, and Google Maps links.</li>
          <li><strong>Report Stock</strong>: Reply <code>1</code>. Select a facility from the numbered list (or press <code>0</code> to type a custom name). Then select a resource from the numbered list. Then choose stock status: <code>1</code> = Confirmed, <code>2</code> = Low, <code>3</code> = Out. Your report enters the moderation queue.</li>
          <li><strong>Subscribe to Alerts</strong>: Reply <code>2</code>. Select a resource from the numbered list. You're now subscribed — you'll receive a WhatsApp message when that resource's stock status changes at any facility.</li>
          <li><strong>Reset Anytime</strong>: Send <code>MENU</code> or <code>RESET</code> at any point to return to the main menu.</li>
        </ol>

        <!-- Tech Stack -->
        <h6 class="fw-brand text-primary-nerap mt-4"><i class="fa-solid fa-layer-group me-2"></i>Tech Stack</h6>
        <ul class="mb-0">
          <li><strong>Backend</strong>: Vanilla PHP (no framework), MySQL, flat file structure</li>
          <li><strong>Frontend</strong>: Bootstrap 5, jQuery, Font Awesome, Chart.js, Google Maps API</li>
          <li><strong>Messaging</strong>: WhatsApp Cloud API-compatible provider, Brevo transactional email (REST API)</li>
          <li><strong>Geospatial</strong>: Bounding-box pre-filter + Haversine distance formula for facility search</li>
          <li><strong>State Machine</strong>: MySQL row-locked sessions (<code>wa_sessions</code>) for multi-step WhatsApp conversations</li>
          <li><strong>PWA</strong>: Manifest + theme color for mobile-native app feel</li>
        </ul>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-nerap-primary" data-bs-dismiss="modal">Got it — start testing</button>
      </div>
    </div>
  </div>
</div>

<script>
$(function () {
    var guideModal = new bootstrap.Modal(document.getElementById('guideModal'));
    guideModal.show();
    document.getElementById('guideModal').addEventListener('hidden.bs.modal', function () {
        window.open('guide.php', '_blank');
    });
});
</script>

<?php require_once __DIR__ . '/includes_footer.php'; ?>