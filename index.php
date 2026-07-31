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

<?php require_once __DIR__ . '/includes_footer.php'; ?>