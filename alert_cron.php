<?php
/**
 * =========================================================================
 * alert_cron.php — Alert Scheduler (run every 15 minutes via cron).
 *
 * Example crontab entry:
 *   /15 * * * * php /path/to/nerap-1/alert_cron.php >> /path/to/nerap-1/cron_log.txt 2>&1
 *
 * This is a SAFETY NET on top of the instant alert dispatched by
 * api_moderation.php on approval. It catches any facility_resources stock
 * changes made directly by admins (admin_facilities.php "Manage Stock")
 * that bypass the moderation queue, and de-duplicates against alert_log so
 * the same change is never announced twice.
 * =========================================================================
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/whatsapp_alerts.php';

// Only allow CLI execution (cron) — block accidental public HTTP access.
if (php_sapi_name() !== 'cli' && empty($_GET['manual_run'])) {
    http_response_code(403);
    die('This script may only be run from the command line (cron).');
}

$conn = db();
echo "[" . date('Y-m-d H:i:s') . "] Running alert_cron.php...\n";

// Find stock changes in the last 15 minutes that have NOT already triggered
// an alert_log entry created after their last_verified_at timestamp.
$sql = "SELECT fr.facility_id, fr.resource_id, fr.status, fr.last_verified_at
        FROM facility_resources fr
        WHERE fr.last_verified_at >= NOW() - INTERVAL 15 MINUTE
          AND NOT EXISTS (
              SELECT 1 FROM alert_log al
              WHERE al.facility_id = fr.facility_id
                AND al.resource_id = fr.resource_id
                AND al.created_at >= fr.last_verified_at
          )";
$changes = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$totalDispatched = 0;
foreach ($changes as $change) {
    $count = dispatch_stock_alerts((int)$change['facility_id'], (int)$change['resource_id'], $change['status']);
    $totalDispatched += $count;
    echo " - Facility #{$change['facility_id']} / Resource #{$change['resource_id']} ({$change['status']}): $count notifications sent\n";
}

echo "Done. " . count($changes) . " stock change(s) processed, $totalDispatched notification(s) dispatched.\n";
