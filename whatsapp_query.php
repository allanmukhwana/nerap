<?php
/**
 * =========================================================================
 * whatsapp_query.php — Resource Query Engine.
 * Matches incoming keywords (English/Swahili/Somali/Amharic synonyms) to
 * resource records, then finds nearby facilities stocking that resource
 * using a bounding-box pre-filter + precise Haversine distance formula
 * (README challenge #3: geospatial performance at scale).
 * =========================================================================
 */
require_once __DIR__ . '/config.php';

/**
 * Fuzzy-matches a free-text keyword against resource categories/synonyms.
 * Returns an array of matching resource rows grouped by category, so the
 * webhook can present a numbered sub-type menu when >1 match is found.
 */
function wa_match_resources($keyword) {
    $keyword = strtolower(trim($keyword));
    if ($keyword === '') return [];

    $conn = db();
    $result = $conn->query("SELECT id, category, subtype, synonyms FROM resources");
    $exact = [];
    $fuzzy = [];

    while ($row = $result->fetch_assoc()) {
        $haystack = strtolower($row['category'] . ' ' . $row['subtype'] . ' ' . $row['synonyms']);
        $terms = array_filter(array_map('trim', explode(',', $haystack)));

        foreach ($terms as $term) {
            if ($term === $keyword || strpos($term, $keyword) !== false || strpos($keyword, $term) !== false) {
                $exact[] = $row;
                continue 2;
            }
        }
        // Fuzzy fallback using similar_text() percentage (handles typos / partial matches)
        foreach ($terms as $term) {
            similar_text($keyword, $term, $percent);
            if ($percent >= 65) { $fuzzy[] = $row; continue 2; }
        }
    }

    return !empty($exact) ? $exact : $fuzzy;
}

/**
 * Finds facilities stocking a given resource, ranked by distance (if a
 * location is provided) or by stock status priority otherwise.
 *
 * @param int        $resourceId
 * @param float|null $lat
 * @param float|null $lng
 * @param float      $radiusKm    Search radius (default 50km)
 * @param int        $limit
 * @return array List of facility rows with distance_km + status
 */
function wa_find_facilities_for_resource($resourceId, $lat = null, $lng = null, $radiusKm = 50, $limit = 5) {
    $conn = db();

    if ($lat !== null && $lng !== null) {
        // --- Bounding box pre-filter (cheap) before the expensive Haversine calc ---
        // 1 degree latitude ~= 111km. Build a rough square around the point first,
        // reducing the candidate set by >95% before precise distance calculation.
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * max(cos(deg2rad($lat)), 0.01));

        $sql = "SELECT f.id, f.name, f.region, f.phone, f.latitude, f.longitude,
                       fr.status, fr.last_verified_at,
                       (6371 * ACOS(
                            COS(RADIANS(?)) * COS(RADIANS(f.latitude)) * COS(RADIANS(f.longitude) - RADIANS(?))
                            + SIN(RADIANS(?)) * SIN(RADIANS(f.latitude))
                       )) AS distance_km
                FROM facilities f
                JOIN facility_resources fr ON fr.facility_id = f.id
                WHERE fr.resource_id = ? AND f.status = 'active'
                  AND f.latitude BETWEEN ? AND ?
                  AND f.longitude BETWEEN ? AND ?
                HAVING distance_km <= ?
                ORDER BY (fr.status = 'confirmed') DESC, distance_km ASC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $latMin = $lat - $latDelta; $latMax = $lat + $latDelta;
        $lngMin = $lng - $lngDelta; $lngMax = $lng + $lngDelta;
        $stmt->bind_param('dddiddddi', $lat, $lng, $lat, $resourceId, $latMin, $latMax, $lngMin, $lngMax, $radiusKm, $limit);
    } else {
        // No location given: rank by stock status priority only (confirmed first).
        $sql = "SELECT f.id, f.name, f.region, f.phone, f.latitude, f.longitude,
                       fr.status, fr.last_verified_at, NULL AS distance_km
                FROM facilities f
                JOIN facility_resources fr ON fr.facility_id = f.id
                WHERE fr.resource_id = ? AND f.status = 'active'
                ORDER BY FIELD(fr.status, 'confirmed', 'low', 'unverified', 'out') ASC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $resourceId, $limit);
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Formats facility search results into the numbered WhatsApp reply shown
 * in the README example ("3 Facilities found near Nairobi: 1️⃣ KNH...").
 */
function wa_format_results_message($resourceLabel, array $facilities) {
    if (empty($facilities)) {
        return "😔 No facilities currently report stock for *$resourceLabel*.\n\nReply *ALERT* to be notified the moment it becomes available, or *MENU* to search again.";
    }

    $emojis = ['1️⃣','2️⃣','3️⃣','4️⃣','5️⃣'];
    $msg = "🔎 " . count($facilities) . " Facilities found for *$resourceLabel*:\n\n";

    foreach ($facilities as $i => $f) {
        $statusLabel = strtoupper(status_label($f['status']));
        $ago = $f['last_verified_at'] ? wa_time_ago($f['last_verified_at']) : 'never verified';
        $msg .= ($emojis[$i] ?? ($i+1) . '.') . " *" . $f['name'] . "* — $statusLabel (Updated $ago)\n";
        if (!empty($f['phone'])) $msg .= "   📞 " . $f['phone'] . "\n";
        $msg .= "   📍 https://maps.google.com/?q=" . $f['latitude'] . "," . $f['longitude'] . "\n";
        if (isset($f['distance_km']) && $f['distance_km'] !== null) $msg .= "   📏 " . round($f['distance_km'], 1) . " km away\n";
        $msg .= "\n";
    }
    $msg .= "🔔 Reply *ALERT* to get notified of stock changes for this resource.\nReply *MENU* to search again.";
    return $msg;
}

/** Human-friendly "X hours ago" formatter for verification timestamps. */
function wa_time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 3600) return max(1, floor($diff / 60)) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    return floor($diff / 86400) . ' days ago';
}
