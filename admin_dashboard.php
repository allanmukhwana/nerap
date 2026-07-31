<?php
/**
 * =========================================================================
 * admin_dashboard.php — Admin analytics overview. Reuses api_dashboard.php
 * (same JSON endpoint that powers the public homepage) so the moderator's
 * view and the public map always reflect identical numbers.
 * =========================================================================
 */
$page_title = 'Dashboard';
require_once __DIR__ . '/admin_header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="stat-tile"><div class="stat-icon mb-2"><i class="fa-solid fa-hospital"></i></div>
      <div class="stat-value count-up" data-target="0" id="cFacilities">0</div><div class="stat-label">Facilities</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-tile"><div class="stat-icon mb-2"><i class="fa-solid fa-box-open"></i></div>
      <div class="stat-value count-up" data-target="0" id="cResources">0</div><div class="stat-label">Resources Tracked</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-tile"><div class="stat-icon mb-2"><i class="fa-solid fa-tower-broadcast"></i></div>
      <div class="stat-value count-up" data-target="0" id="cAlerts">0</div><div class="stat-label">Alerts (24h)</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-tile"><div class="stat-icon mb-2"><i class="fa-solid fa-clipboard-list"></i></div>
      <div class="stat-value count-up" data-target="0" id="cPending">0</div><div class="stat-label">Pending Reports</div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6"><div class="nerap-card p-3"><h6 class="fw-brand">Stock Trend (14 days)</h6><canvas id="chartTrend" height="180"></canvas></div></div>
  <div class="col-lg-6"><div class="nerap-card p-3"><h6 class="fw-brand">Regional Coverage</h6><canvas id="chartDoughnut" height="180"></canvas></div></div>
  <div class="col-lg-6"><div class="nerap-card p-3"><h6 class="fw-brand">Facility Comparison by Region</h6><canvas id="chartBar" height="180"></canvas></div></div>
  <div class="col-lg-6"><div class="nerap-card p-3"><h6 class="fw-brand">Alert Activity</h6><canvas id="chartAlerts" height="180"></canvas></div></div>
</div>

<script>
let charts = {};
function refreshDashboard() {
    $.ajax({ url: 'api_dashboard.php', dataType: 'json' }).done(function (res) {
        if (!res || res.status !== 200) return;
        $('#cFacilities').attr('data-target', res.counters.total_facilities);
        $('#cResources').attr('data-target', res.counters.total_resources);
        $('#cAlerts').attr('data-target', res.counters.active_alerts_24h);
        $('#cPending').attr('data-target', res.counters.pending_submissions);
        animateCounters(document);

        const green = '#1b693f', navy = '#042238', warn = '#e0a800', danger = '#c0392b', grey = '#6c757d';
        if (charts.trend) charts.trend.destroy();
        charts.trend = new Chart(document.getElementById('chartTrend'), { type: 'line', data: { labels: res.trend_chart.labels, datasets: [{ label: 'Approved Reports', data: res.trend_chart.data, borderColor: green, backgroundColor: 'rgba(27,105,63,0.15)', tension: 0.35, fill: true }] }, options: { plugins: { legend: { display: false } } } });
        if (charts.doughnut) charts.doughnut.destroy();
        charts.doughnut = new Chart(document.getElementById('chartDoughnut'), { type: 'doughnut', data: { labels: ['Confirmed','Low','Out','Unverified'], datasets: [{ data: [res.status_breakdown.confirmed,res.status_breakdown.low,res.status_breakdown.out,res.status_breakdown.unverified], backgroundColor: [green,warn,danger,grey] }] } });
        if (charts.bar) charts.bar.destroy();
        charts.bar = new Chart(document.getElementById('chartBar'), { type: 'bar', data: { labels: res.region_chart.labels, datasets: [ { label:'Confirmed', data: res.region_chart.confirmed, backgroundColor: green }, { label:'Low', data: res.region_chart.low, backgroundColor: warn }, { label:'Out', data: res.region_chart.out, backgroundColor: danger } ] }, options: { scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } } });
        if (charts.alerts) charts.alerts.destroy();
        charts.alerts = new Chart(document.getElementById('chartAlerts'), { type: 'bar', data: { labels: res.alert_chart.labels, datasets: [{ label: 'Alerts Sent', data: res.alert_chart.data, backgroundColor: navy }] }, options: { plugins: { legend: { display: false } } } });
    });
}
$(function () { refreshDashboard(); setInterval(refreshDashboard, 30000); });
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
