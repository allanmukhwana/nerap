<?php
/**
 * =========================================================================
 * api_moderation.php — RESTful AJAX endpoint consumed by admin_moderation.php.
 * Exposes approve/reject actions that:
 *   1. Update the submission + write an audit row to moderation_log
 *   2. On approval: upsert facility_resources stock + fire WhatsApp/email
 *      alerts to matching subscribers (the "Moderation Queue → WhatsApp
 *      Alert Loop" described in the README).
 * =========================================================================
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/whatsapp_alerts.php';
header('Content-Type: application/json');
require_admin(); // moderators/admins only

$conn = db();
$action = $_POST['action'] ?? '';
$submissionId = (int)($_POST['submission_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM submissions WHERE id = ?");
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission) {
    echo json_encode(['status' => 404, 'message' => 'Submission not found']); exit;
}

if ($action === 'reject') {
    $upd = $conn->prepare("UPDATE submissions SET review_status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
    $upd->bind_param('ii', $_SESSION['admin_id'], $submissionId);
    $upd->execute();
    log_moderation($submissionId, 'rejected');
    echo json_encode(['status' => 200, 'message' => 'Submission rejected']);
    exit;
}

if ($action === 'approve') {
    $facilityId = !empty($_POST['facility_id']) ? (int)$_POST['facility_id'] : $submission['facility_id'];

    // If reporting a brand new facility with no coordinates yet, moderator must supply them.
    if (!$facilityId && !empty($_POST['new_facility_name'])) {
        $lat = (float)($_POST['new_lat'] ?? 0);
        $lng = (float)($_POST['new_lng'] ?? 0);
        $region = trim($_POST['new_region'] ?? 'Unknown');
        $ins = $conn->prepare("INSERT INTO facilities (name, type, region, latitude, longitude, is_verified) VALUES (?, 'other', ?, ?, ?, 0)");
        $name = trim($_POST['new_facility_name']);
        $ins->bind_param('ssdd', $name, $region, $lat, $lng);
        $ins->execute();
        $facilityId = $conn->insert_id;
    }

    if (!$facilityId) {
        echo json_encode(['status' => 422, 'message' => 'This submission needs a facility to be linked (with coordinates) before it can be approved.']);
        exit;
    }

    // Upsert stock level
    $status = $submission['reported_status'] ?: 'unverified';
    $up = $conn->prepare("INSERT INTO facility_resources (facility_id, resource_id, status, quantity, last_verified_at) VALUES (?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE status=VALUES(status), quantity=VALUES(quantity), last_verified_at=NOW()");
    $up->bind_param('iisi', $facilityId, $submission['resource_id'], $status, $submission['quantity']);
    $up->execute();

    $upd = $conn->prepare("UPDATE submissions SET review_status='approved', facility_id=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
    $upd->bind_param('iii', $facilityId, $_SESSION['admin_id'], $submissionId);
    $upd->execute();

    log_moderation($submissionId, 'approved');
    $dispatched = dispatch_stock_alerts($facilityId, (int)$submission['resource_id'], $status);

    echo json_encode(['status' => 200, 'message' => 'Approved and published to the live map.', 'alerts_dispatched' => $dispatched]);
    exit;
}

echo json_encode(['status' => 400, 'message' => 'Unknown action']);

/** Writes an audit trail row for every moderation decision. */
function log_moderation($submissionId, $action) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO moderation_log (submission_id, admin_id, action) VALUES (?,?,?)");
    $stmt->bind_param('iis', $submissionId, $_SESSION['admin_id'], $action);
    $stmt->execute();
}
