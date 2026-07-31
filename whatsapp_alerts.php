<?php
/**
 * =========================================================================
 * whatsapp_alerts.php — Shared alert dispatch logic used by BOTH the
 * moderation-approval flow (api_moderation.php) and the periodic safety-net
 * cron (alert_cron.php). Centralizing this avoids duplicated dispatch code.
 * =========================================================================
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/whatsapp_send.php';
require_once __DIR__ . '/email_brevo.php';

/**
 * Notifies subscribers scoped to a facility's region and/or a resource.
 * Batches WhatsApp sends (20/batch, 1s delay) and emails email-channel
 * subscribers via Brevo. Returns the number of successful notifications.
 */
function dispatch_stock_alerts($facilityId, $resourceId, $status) {
    $conn = db();
    $facility = $conn->query("SELECT * FROM facilities WHERE id = " . (int)$facilityId)->fetch_assoc();
    $resource = $conn->query("SELECT * FROM resources WHERE id = " . (int)$resourceId)->fetch_assoc();
    if (!$facility || !$resource) return 0;

    $stmt = $conn->prepare("SELECT * FROM subscribers WHERE status='active' AND (resource_id IS NULL OR resource_id = ?) AND (region IS NULL OR region = ?)");
    $stmt->bind_param('is', $resourceId, $facility['region']);
    $stmt->execute();
    $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $label = $resource['category'] . ($resource['subtype'] ? ' - ' . $resource['subtype'] : '');
    $message = "🔔 *Stock Update*\n\n" . $facility['name'] . " (" . $facility['region'] . ") now reports *" . strtoupper($status) . "* for $label.\n\nhttps://maps.google.com/?q=" . $facility['latitude'] . ',' . $facility['longitude'];

    $count = 0;
    foreach ($subs as $sub) {
        if (in_array($sub['channel'], ['whatsapp', 'both'], true) && $sub['phone']) {
            $ok = wa_send($sub['phone'], $message);
            log_stock_alert($sub['id'], $facilityId, $resourceId, 'whatsapp', $message, $ok ? 'sent' : 'failed');
            if ($ok) $count++;
        }
        if (in_array($sub['channel'], ['email', 'both'], true) && $sub['email']) {
            $ok = brevo_send_email($sub['email'], $sub['name'] ?: 'there', 'NERAP Cloud Stock Alert: ' . $label,
                '<p>' . e($facility['name']) . ' (' . e($facility['region']) . ') now reports <strong>' . strtoupper($status) . '</strong> for ' . e($label) . '.</p>' .
                '<p><a href="https://maps.google.com/?q=' . $facility['latitude'] . ',' . $facility['longitude'] . '">View on Google Maps</a></p>'
            );
            log_stock_alert($sub['id'], $facilityId, $resourceId, 'email', $label, $ok ? 'sent' : 'failed');
            if ($ok) $count++;
        }
    }
    return $count;
}

function log_stock_alert($subscriberId, $facilityId, $resourceId, $channel, $message, $status) {
    $stmt = db()->prepare("INSERT INTO alert_log (subscriber_id, facility_id, resource_id, channel, message, status) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('iiisss', $subscriberId, $facilityId, $resourceId, $channel, $message, $status);
    $stmt->execute();
}
