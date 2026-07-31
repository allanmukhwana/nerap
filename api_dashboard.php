<?php
/**
 * =========================================================================
 * api_dashboard.php — JSON endpoint powering the Chart.js analytics layer
 * and live summary counters on index.php / admin_dashboard.php.
 * Polled every 30s via jQuery $.ajax() so map + charts always match.
 * =========================================================================
 */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
$conn = db();

// ---- Live summary counters -------------------------------------------------
$totalFacilities = (int)$conn->query("SELECT COUNT(*) c FROM facilities WHERE status='active'")->fetch_assoc()['c'];
$totalResources  = (int)$conn->query("SELECT COUNT(*) c FROM resources")->fetch_assoc()['c'];
$activeAlerts24h = (int)$conn->query("SELECT COUNT(*) c FROM alert_log WHERE created_at >= NOW() - INTERVAL 24 HOUR")->fetch_assoc()['c'];
$pendingSubmissions = (int)$conn->query("SELECT COUNT(*) c FROM submissions WHERE review_status='pending'")->fetch_assoc()['c'];

// ---- Regional coverage doughnut: % of facility_resource rows per status ----
$statusBreakdown = ['confirmed' => 0, 'low' => 0, 'out' => 0, 'unverified' => 0];
$res = $conn->query("SELECT status, COUNT(*) c FROM facility_resources GROUP BY status");
while ($row = $res->fetch_assoc()) { $statusBreakdown[$row['status']] = (int)$row['c']; }

// ---- Facility comparison bar chart: stock count by region ------------------
$regionRes = $conn->query("SELECT f.region, 
        SUM(fr.status='confirmed') AS confirmed,
        SUM(fr.status='low') AS low,
        SUM(fr.status='out') AS out_of_stock
        FROM facilities f LEFT JOIN facility_resources fr ON fr.facility_id = f.id
        WHERE f.status='active' GROUP BY f.region ORDER BY f.region ASC");
$regionLabels = []; $regionConfirmed = []; $regionLow = []; $regionOut = [];
while ($row = $regionRes->fetch_assoc()) {
    $regionLabels[] = $row['region'];
    $regionConfirmed[] = (int)$row['confirmed'];
    $regionLow[] = (int)$row['low'];
    $regionOut[] = (int)$row['out_of_stock'];
}

// ---- Stock trend line chart: submissions approved per day (last 14 days) ---
$trendRes = $conn->query("SELECT DATE(reviewed_at) d, COUNT(*) c FROM submissions
        WHERE review_status='approved' AND reviewed_at >= NOW() - INTERVAL 14 DAY
        GROUP BY DATE(reviewed_at) ORDER BY d ASC");
$trendLabels = []; $trendData = [];
while ($row = $trendRes->fetch_assoc()) { $trendLabels[] = $row['d']; $trendData[] = (int)$row['c']; }

// ---- Alert activity histogram: alerts sent per day (last 14 days) ----------
$alertRes = $conn->query("SELECT DATE(created_at) d, COUNT(*) c FROM alert_log
        WHERE created_at >= NOW() - INTERVAL 14 DAY GROUP BY DATE(created_at) ORDER BY d ASC");
$alertLabels = []; $alertData = [];
while ($row = $alertRes->fetch_assoc()) { $alertLabels[] = $row['d']; $alertData[] = (int)$row['c']; }

echo json_encode([
    'status' => 200,
    'counters' => [
        'total_facilities'    => $totalFacilities,
        'total_resources'     => $totalResources,
        'active_alerts_24h'   => $activeAlerts24h,
        'pending_submissions' => $pendingSubmissions,
        'last_update'         => date('c'),
    ],
    'status_breakdown' => $statusBreakdown,
    'region_chart' => [
        'labels' => $regionLabels,
        'confirmed' => $regionConfirmed,
        'low' => $regionLow,
        'out' => $regionOut,
    ],
    'trend_chart' => [
        'labels' => $trendLabels,
        'data' => $trendData,
    ],
    'alert_chart' => [
        'labels' => $alertLabels,
        'data' => $alertData,
    ],
]);
