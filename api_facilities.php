<?php
/**
 * =========================================================================
 * api_facilities.php — JSON endpoint powering the live map + facility list.
 * Consumed by assets/app.js (index.php) via $.ajax() GET requests.
 *
 * Query params (all optional):
 *   resource_id  — filter to facilities stocking a specific resource
 *   region       — filter by county/region (exact match)
 *   status       — filter by stock status: confirmed|low|out|unverified
 *   q            — free text search on facility name
 *   lat, lng, radius_km — geospatial proximity search (Haversine formula)
 * =========================================================================
 */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$conn = db();

$resource_id = isset($_GET['resource_id']) ? (int)$_GET['resource_id'] : 0;
$region      = trim($_GET['region'] ?? '');
$status      = trim($_GET['status'] ?? '');
$q           = trim($_GET['q'] ?? '');
$lat         = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng         = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
$radius_km   = isset($_GET['radius_km']) ? (float)$_GET['radius_km'] : 50;

// Base query: one row per facility, resources aggregated as JSON via GROUP_CONCAT
$sql = "SELECT f.id, f.name, f.type, f.region, f.address, f.latitude, f.longitude, f.phone, f.is_verified,
        GROUP_CONCAT(DISTINCT CONCAT(r.id, '::', r.category, IFNULL(CONCAT(' - ', r.subtype), ''), '::', fr.status, '::', IFNULL(fr.last_verified_at, '')) SEPARATOR '||') AS resources
        FROM facilities f
        LEFT JOIN facility_resources fr ON fr.facility_id = f.id
        LEFT JOIN resources r ON r.id = fr.resource_id
        WHERE f.status = 'active'";
$params = [];
$types = '';

if ($resource_id > 0) {
    $sql .= " AND f.id IN (SELECT facility_id FROM facility_resources WHERE resource_id = ?)";
    $params[] = $resource_id; $types .= 'i';
}
if ($region !== '') {
    $sql .= " AND f.region = ?";
    $params[] = $region; $types .= 's';
}
if ($status !== '') {
    $sql .= " AND f.id IN (SELECT facility_id FROM facility_resources WHERE status = ?)";
    $params[] = $status; $types .= 's';
}
if ($q !== '') {
    $sql .= " AND f.name LIKE ?";
    $params[] = '%' . $q . '%'; $types .= 's';
}

$sql .= " GROUP BY f.id";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$facilities = [];
while ($row = $result->fetch_assoc()) {
    // Optional Haversine distance filter/sort (bounding box pre-filter for performance).
    if ($lat !== null && $lng !== null) {
        $distance = haversine_km($lat, $lng, (float)$row['latitude'], (float)$row['longitude']);
        if ($distance > $radius_km) continue;
        $row['distance_km'] = round($distance, 2);
    }

    $resources = [];
    if (!empty($row['resources'])) {
        foreach (explode('||', $row['resources']) as $chunk) {
            [$rid, $rname, $rstatus, $rverified] = array_pad(explode('::', $chunk), 4, '');
            $resources[] = [
                'resource_id' => (int)$rid,
                'name'        => $rname,
                'status'      => $rstatus ?: 'unverified',
                'last_verified_at' => $rverified,
            ];
        }
    }

    // Facility-level overall status = worst status among its resources (for the map pin color)
    $overall = 'unverified';
    $rank = ['out' => 3, 'low' => 2, 'confirmed' => 1, 'unverified' => 0];
    foreach ($resources as $r) {
        if (($rank[$r['status']] ?? 0) > ($rank[$overall] ?? 0)) $overall = $r['status'];
    }
    // Prefer confirmed if it's the only status present and no worse status exists.
    if (!empty($resources) && $overall === 'unverified') {
        foreach ($resources as $r) { if ($r['status'] === 'confirmed') { $overall = 'confirmed'; break; } }
    }

    $facilities[] = [
        'id'          => (int)$row['id'],
        'name'        => $row['name'],
        'type'        => $row['type'],
        'region'      => $row['region'],
        'address'     => $row['address'],
        'lat'         => (float)$row['latitude'],
        'lng'         => (float)$row['longitude'],
        'phone'       => $row['phone'],
        'is_verified' => (bool)$row['is_verified'],
        'overall_status' => $overall,
        'color'       => status_color($overall),
        'resources'   => $resources,
        'distance_km' => $row['distance_km'] ?? null,
        'maps_link'   => 'https://maps.google.com/?q=' . $row['latitude'] . ',' . $row['longitude'],
    ];
}

// Sort by distance when a geo search was performed
if ($lat !== null && $lng !== null) {
    usort($facilities, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);
}

echo json_encode(['status' => 200, 'data' => $facilities]);

/**
 * Haversine formula — great-circle distance in kilometers between two
 * lat/lng points. Used both here and in whatsapp_query.php.
 */
function haversine_km($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}
