<?php
/**
 * =========================================================================
 * whatsapp_webhook.php — Webhook Receiver + conversation router.
 *
 * Configure this exact URL as the webhook in your WhatsApp provider
 * dashboard (see whatsapp-api-docs.md): https://yourdomain.com/whatsapp_webhook.php
 *
 * Incoming payload shape (POST, multipart or JSON):
 *   { "type": "whatsapp", "data": { "id", "wid", "phone", "message",
 *     "attachment", "timestamp" } }
 *
 * Routes the message through the menu-driven state machine (wa_sessions)
 * to power: (1) instant resource search, (2) crowdsourced stock reporting,
 * (3) alert subscription — all with zero login/training required.
 * =========================================================================
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/whatsapp_send.php';
require_once __DIR__ . '/whatsapp_session.php';
require_once __DIR__ . '/whatsapp_query.php';

// Only POST requests carry inbound message notifications.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 405, 'message' => 'Method Not Allowed']);
    exit;
}

$request = $_REQUEST;

// Verify the shared webhook secret configured in config.php / provider dashboard.
if (!isset($request['secret']) || $request['secret'] !== WA_WEBHOOK_SECRET) {
    http_response_code(403);
    echo json_encode(['status' => 403, 'message' => 'Invalid webhook secret']);
    exit;
}

$payloadType = $request['type'] ?? '';
$payloadData = $request['data'] ?? [];
if (is_string($payloadData)) {
    $decoded = json_decode($payloadData, true);
    if (json_last_error() === JSON_ERROR_NONE) $payloadData = $decoded;
}

if ($payloadType !== 'whatsapp' || !is_array($payloadData)) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'Invalid payload']);
    exit;
}

$phone = wa_format_phone($payloadData['phone'] ?? '');
$messageRaw = trim((string)($payloadData['message'] ?? ''));
$attachment = !empty($payloadData['attachment']) ? $payloadData['attachment'] : null;

if ($phone === '' ) { http_response_code(200); echo json_encode(['status' => 200]); exit; }

wa_log("RECEIVED <- $phone: $messageRaw" . ($attachment ? " [attachment: $attachment]" : ''));

$messageUpper = strtoupper($messageRaw);
$session = wa_get_session($phone); // row-locked (transaction open)

// ---- Global commands available from ANY state -----------------------------
if (in_array($messageUpper, ['MENU', 'HI', 'HELLO', 'START', 'RESET'], true)) {
    wa_set_session($phone, 'idle', $session['data']);
    wa_send($phone, wa_main_menu());
    exit;
}

// ---- State machine ----------------------------------------------------------
switch ($session['state']) {

    case 'search_resource':
        handle_search_resource_choice($phone, $messageRaw, $session);
        break;

    case 'report_facility':
        handle_report_facility_choice($phone, $messageRaw, $session);
        break;

    case 'report_facility_name':
        send_resource_menu($phone, 'report_resource', ['facility_name_raw' => $messageRaw, 'facility_id' => null]);
        break;

    case 'report_resource':
        handle_report_resource_choice($phone, $messageRaw, $session);
        break;

    case 'report_status':
        handle_report_status($phone, $messageRaw, $session, $attachment);
        break;

    case 'alert_resource':
        handle_alert_resource_choice($phone, $messageRaw, $session);
        break;

    case 'idle':
    default:
        handle_idle($phone, $messageRaw, $session);
        break;
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

/** Idle state: user sent a number from the main menu. */
function handle_idle($phone, $message, $session) {
    $choice = trim($message);

    if ($choice === '0') {
        send_resource_menu($phone, 'search_resource', []);
        return;
    }

    if ($choice === '1') {
        send_facility_menu($phone);
        return;
    }

    if ($choice === '2') {
        send_resource_menu($phone, 'alert_resource', []);
        return;
    }

    db()->commit();
    wa_send($phone, "🤔 I didn't recognize that. " . wa_main_menu());
}

/** Sends a numbered menu of all resources and sets the next state. */
function send_resource_menu($phone, $next_state, array $session_data = []) {
    $resources = db()->query("SELECT id, category, subtype FROM resources ORDER BY category, subtype")->fetch_all(MYSQLI_ASSOC);
    $candidates = [];
    $menu = "Select a resource:\n\n";
    foreach ($resources as $i => $r) {
        $label = $r['category'] . ($r['subtype'] ? ' - ' . $r['subtype'] : '');
        $candidates[] = ['id' => (int)$r['id'], 'label' => $label];
        $menu .= ($i + 1) . ". " . $label . "\n";
    }
    $menu .= "\nReply with a number:";
    $session_data['candidates'] = $candidates;
    wa_set_session($phone, $next_state, $session_data);
    wa_send($phone, $menu);
}

/** Sends a numbered menu of active facilities for reporting. */
function send_facility_menu($phone) {
    $facilities = db()->query("SELECT id, name, region FROM facilities WHERE status = 'active' ORDER BY name LIMIT 10")->fetch_all(MYSQLI_ASSOC);
    $candidates = [];
    $menu = "Select a facility:\n\n";
    foreach ($facilities as $i => $f) {
        $candidates[] = ['id' => (int)$f['id'], 'label' => $f['name']];
        $menu .= ($i + 1) . ". " . $f['name'] . " (" . $f['region'] . ")\n";
    }
    $menu .= "0. Other (type facility name)\n";
    $menu .= "\nReply with a number:";
    wa_set_session($phone, 'report_facility', ['candidates' => $candidates]);
    wa_send($phone, $menu);
}

/** User picked a resource from the search menu. */
function handle_search_resource_choice($phone, $message, $session) {
    $choice = (int)$message;
    $candidates = $session['data']['candidates'] ?? [];
    if ($choice < 1 || $choice > count($candidates)) {
        db()->commit();
        wa_send($phone, "Please reply with a valid number from the list, or type *MENU* to start over.");
        return;
    }
    $resourceId = $candidates[$choice - 1]['id'];
    $row = db()->query("SELECT id, category, subtype FROM resources WHERE id = " . (int)$resourceId)->fetch_assoc();
    run_resource_search($phone, $row);
}

/** Runs the facility search for a resolved resource and replies with results. */
function run_resource_search($phone, array $resource) {
    $label = $resource['category'] . ($resource['subtype'] ? ' - ' . $resource['subtype'] : '');
    $facilities = wa_find_facilities_for_resource((int)$resource['id']);
    wa_set_session($phone, 'idle', ['last_resource_id' => (int)$resource['id']]);
    wa_send($phone, wa_format_results_message($label, $facilities));
}

/** User picked a facility from the report menu (or chose 0 for custom name). */
function handle_report_facility_choice($phone, $message, $session) {
    $choice = trim($message);
    $candidates = $session['data']['candidates'] ?? [];

    if ($choice === '0') {
        wa_set_session($phone, 'report_facility_name', []);
        wa_send($phone, "📝 Type the facility, shelter, or distribution point name:");
        return;
    }

    $num = (int)$choice;
    if ($num < 1 || $num > count($candidates)) {
        db()->commit();
        wa_send($phone, "Please reply with a valid number from the list, or 0 to type a name. Type *MENU* to start over.");
        return;
    }

    $facilityId = (int)$candidates[$num - 1]['id'];
    $facilityName = $candidates[$num - 1]['label'];
    $data = ['facility_id' => $facilityId, 'facility_name_raw' => $facilityName];
    send_resource_menu($phone, 'report_resource', $data);
}

/** User picked a resource from the report menu. */
function handle_report_resource_choice($phone, $message, $session) {
    $choice = (int)$message;
    $candidates = $session['data']['candidates'] ?? [];
    if ($choice < 1 || $choice > count($candidates)) {
        db()->commit();
        wa_send($phone, "Please reply with a valid number from the list, or type *MENU* to start over.");
        return;
    }
    $data = $session['data'];
    $data['resource_id'] = (int)$candidates[$choice - 1]['id'];
    unset($data['candidates']);
    wa_set_session($phone, 'report_status', $data);
    wa_send($phone, "What is the current stock status?\n1. 🟢 Confirmed / In Stock\n2. 🟡 Low Stock\n3. 🔴 Out of Stock\n\nReply with a number:");
}

/** Final step: stock status number → insert into submissions/moderation queue. */
function handle_report_status($phone, $message, $session, $attachment) {
    $map = [1 => 'confirmed', 2 => 'low', 3 => 'out'];
    $status = $map[(int)$message] ?? null;
    if (!$status) {
        db()->commit();
        wa_send($phone, "Please reply with 1, 2, or 3.");
        return;
    }

    $data = $session['data'];
    $facilityNameRaw = $data['facility_name_raw'] ?? '';
    $facilityId = $data['facility_id'] ?? null;
    $resourceId = (int)($data['resource_id'] ?? 0);

    // If no facility_id yet, try to match an existing facility by name.
    if (!$facilityId && $facilityNameRaw !== '') {
        $match = db()->prepare("SELECT id FROM facilities WHERE name LIKE ? LIMIT 1");
        $like = '%' . $facilityNameRaw . '%';
        $match->bind_param('s', $like);
        $match->execute();
        if ($row = $match->get_result()->fetch_assoc()) $facilityId = (int)$row['id'];
    }

    $stmt = db()->prepare("INSERT INTO submissions (source, phone, facility_id, facility_name_raw, resource_id, reported_status, attachment_url, review_status)
        VALUES ('whatsapp', ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param('sissss', $phone, $facilityId, $facilityNameRaw, $resourceId, $status, $attachment);
    $stmt->execute();

    wa_set_session($phone, 'idle', []);
    wa_send($phone, "✅ Thank you! Your report has been submitted for moderator review and will appear on the map once verified.\n\nReply *MENU* to start again.");
}

/** User picked a resource to subscribe to alerts for. */
function handle_alert_resource_choice($phone, $message, $session) {
    $choice = (int)$message;
    $candidates = $session['data']['candidates'] ?? [];
    if ($choice < 1 || $choice > count($candidates)) {
        db()->commit();
        wa_send($phone, "Please reply with a valid number from the list, or type *MENU* to start over.");
        return;
    }
    $resourceId = (int)$candidates[$choice - 1]['id'];
    $stmt = db()->prepare("INSERT INTO subscribers (phone, resource_id, channel) VALUES (?, ?, 'whatsapp')");
    $stmt->bind_param('si', $phone, $resourceId);
    $stmt->execute();
    wa_set_session($phone, 'idle', []);
    wa_send($phone, "🔔 You're subscribed! We'll message you the moment stock status changes.\n\nReply *MENU* to start again.");
}

/** The always-available help/main menu text shown on MENU/HI/unrecognized input. */
function wa_main_menu() {
    return "👋 Welcome to *" . SITE_NAME . "!*\n\n" .
        "0️⃣ Search Resources\n" .
        "1️⃣ Report Stock Update\n" .
        "2️⃣ Subscribe to Alerts\n\n" .
        "Reply with a number:";
}
