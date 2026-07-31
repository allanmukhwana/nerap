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

if ($messageUpper === 'REPORT') {
    wa_set_session($phone, 'report_facility', []);
    wa_send($phone, "📝 *Report a Stock Update*\n\nWhat is the facility, shelter, or distribution point name?");
    exit;
}

if ($messageUpper === 'ALERT') {
    $resourceId = $session['data']['last_resource_id'] ?? null;
    if (!$resourceId) {
        db()->commit();
        wa_send($phone, "Please search for a resource first (e.g. type *ANTIVENOM*), then reply *ALERT* to subscribe to updates for it.");
        exit;
    }
    $stmt = db()->prepare("INSERT INTO subscribers (phone, resource_id, channel) VALUES (?, ?, 'whatsapp')");
    $stmt->bind_param('si', $phone, $resourceId);
    $stmt->execute();
    db()->commit();
    wa_send($phone, "🔔 You're subscribed! We'll message you the moment stock status changes.\n\nReply *MENU* to search again.");
    exit;
}

// ---- State machine ----------------------------------------------------------
switch ($session['state']) {

    case 'awaiting_resource_choice':
        handle_resource_choice($phone, $messageRaw, $session);
        break;

    case 'report_facility':
        wa_set_session($phone, 'report_resource', ['facility_name_raw' => $messageRaw]);
        wa_send($phone, "Which resource are you reporting on? (e.g. *Antivenom*, *Blood*, *ICU Bed*)");
        break;

    case 'report_resource':
        handle_report_resource($phone, $messageRaw, $session);
        break;

    case 'report_resource_choice':
        handle_report_resource_choice($phone, $messageRaw, $session);
        break;

    case 'report_status':
        handle_report_status($phone, $messageRaw, $session, $attachment);
        break;

    case 'idle':
    default:
        handle_idle_search($phone, $messageRaw, $session);
        break;
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

/** Main entry point: user typed a free-text keyword while idle. */
function handle_idle_search($phone, $message, $session) {
    $matches = wa_match_resources($message);

    if (empty($matches)) {
        db()->commit();
        wa_send($phone, "🤔 I didn't recognize that. " . wa_main_menu());
        return;
    }

    if (count($matches) === 1) {
        run_resource_search($phone, $matches[0]);
        return;
    }

    // Multiple sub-types matched (e.g. "antivenom" -> Polyvalent/Scorpion/Spider) — show menu.
    $data = ['candidates' => array_map(fn($m) => ['id' => $m['id'], 'label' => $m['category'] . ($m['subtype'] ? ' - ' . $m['subtype'] : '')], $matches)];
    wa_set_session($phone, 'awaiting_resource_choice', $data);

    $menu = "🔍 *" . $message . "* Search\n";
    foreach ($data['candidates'] as $i => $c) { $menu .= ($i + 1) . ". " . $c['label'] . "\n"; }
    $menu .= "\nReply with a number:";
    wa_send($phone, $menu);
}

/** User replied with a number to a resource sub-type menu. */
function handle_resource_choice($phone, $message, $session) {
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

/** Step 2 of reporting: resolve the resource keyword typed by the reporter. */
function handle_report_resource($phone, $message, $session) {
    $matches = wa_match_resources($message);
    if (empty($matches)) {
        db()->commit();
        wa_send($phone, "I couldn't match that to a known resource. Try again (e.g. *Blood*, *Antivenom*, *ICU Bed*), or type *RESET* to cancel.");
        return;
    }
    if (count($matches) === 1) {
        $data = $session['data'];
        $data['resource_id'] = (int)$matches[0]['id'];
        wa_set_session($phone, 'report_status', $data);
        wa_send($phone, "What is the current stock status?\n1. 🟢 Confirmed / In Stock\n2. 🟡 Low Stock\n3. 🔴 Out of Stock\n\nReply with a number:");
        return;
    }
    $data = $session['data'];
    $data['candidates'] = array_map(fn($m) => ['id' => $m['id'], 'label' => $m['category'] . ($m['subtype'] ? ' - ' . $m['subtype'] : '')], $matches);
    wa_set_session($phone, 'report_resource_choice', $data);
    $menu = "Which specific type?\n";
    foreach ($data['candidates'] as $i => $c) { $menu .= ($i + 1) . ". " . $c['label'] . "\n"; }
    wa_send($phone, $menu . "\nReply with a number:");
}

function handle_report_resource_choice($phone, $message, $session) {
    $choice = (int)$message;
    $candidates = $session['data']['candidates'] ?? [];
    if ($choice < 1 || $choice > count($candidates)) {
        db()->commit();
        wa_send($phone, "Please reply with a valid number, or type *RESET* to cancel.");
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
    $resourceId = (int)($data['resource_id'] ?? 0);

    // Try to match an existing facility by exact/partial name for a smoother moderation experience.
    $facilityId = null;
    $match = db()->prepare("SELECT id FROM facilities WHERE name LIKE ? LIMIT 1");
    $like = '%' . $facilityNameRaw . '%';
    $match->bind_param('s', $like);
    $match->execute();
    if ($row = $match->get_result()->fetch_assoc()) $facilityId = (int)$row['id'];

    $stmt = db()->prepare("INSERT INTO submissions (source, phone, facility_id, facility_name_raw, resource_id, reported_status, attachment_url, review_status)
        VALUES ('whatsapp', ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param('sissss', $phone, $facilityId, $facilityNameRaw, $resourceId, $status, $attachment);
    $stmt->execute();

    wa_set_session($phone, 'idle', []);
    wa_send($phone, "✅ Thank you! Your report has been submitted for moderator review and will appear on the map once verified.\n\nReply *MENU* to search for a resource.");
}

/** The always-available help/main menu text shown on MENU/HI/unrecognized input. */
function wa_main_menu() {
    return "👋 Welcome to *" . SITE_NAME . "*!\n\n" .
        "🔎 Type a resource name to search (e.g. *Antivenom*, *Blood*, *ICU Bed*, *Shelter*)\n" .
        "📝 Type *REPORT* to submit a stock update\n" .
        "🔔 Type *ALERT* (after a search) to get notified of changes\n" .
        "🔁 Type *MENU* anytime to see this again";
}
