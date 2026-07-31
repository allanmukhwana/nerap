<?php
/**
 * =========================================================================
 * admin_broadcast.php — Manually compose & send a targeted WhatsApp/email
 * broadcast to subscribers (e.g. declaring a humanitarian event, announcing
 * a new shelter). Uses the same wa_broadcast() batching + Brevo wrapper as
 * the automatic moderation-triggered alerts in api_moderation.php.
 * =========================================================================
 */
$page_title = 'Broadcast Alert';
require_once __DIR__ . '/admin_header.php';
require_once __DIR__ . '/whatsapp_send.php';
require_once __DIR__ . '/email_brevo.php';
$conn = db();

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $region = trim($_POST['region'] ?? '') ?: null;
    $resourceId = !empty($_POST['resource_id']) ? (int)$_POST['resource_id'] : null;
    $message = trim($_POST['message'] ?? '');

    $sql = "SELECT * FROM subscribers WHERE status='active'";
    $params = []; $types = '';
    if ($region) { $sql .= " AND (region IS NULL OR region = ?)"; $params[] = $region; $types .= 's'; }
    if ($resourceId) { $sql .= " AND (resource_id IS NULL OR resource_id = ?)"; $params[] = $resourceId; $types .= 'i'; }

    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $waPhones = array_values(array_filter(array_map(fn($s) => in_array($s['channel'], ['whatsapp','both'], true) ? $s['phone'] : null, $subs)));
    $waResult = wa_broadcast($waPhones, "📢 *NERAP Alert*\n\n$message");

    $emailCount = 0;
    foreach ($subs as $s) {
        if (in_array($s['channel'], ['email','both'], true) && $s['email']) {
            if (brevo_send_email($s['email'], $s['name'] ?: 'there', 'NERAP Cloud Alert', '<p>' . nl2br(e($message)) . '</p>')) $emailCount++;
        }
    }

    $result = ['recipients' => count($subs), 'wa_sent' => $waResult['sent'], 'wa_failed' => $waResult['failed'], 'email_sent' => $emailCount];
}

$regions = $conn->query("SELECT DISTINCT region FROM facilities ORDER BY region")->fetch_all(MYSQLI_ASSOC);
$resources = $conn->query("SELECT id, category, subtype FROM resources ORDER BY category, subtype")->fetch_all(MYSQLI_ASSOC);
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <?php if ($result): ?>
      <div class="alert alert-success rounded-nerap">
        Broadcast sent to <?= $result['recipients'] ?> matching subscribers —
        WhatsApp: <?= $result['wa_sent'] ?> sent / <?= $result['wa_failed'] ?> failed,
        Email: <?= $result['email_sent'] ?> sent.
      </div>
    <?php endif; ?>

    <div class="nerap-card p-4">
      <h6 class="fw-brand mb-3"><i class="fa-solid fa-tower-broadcast text-secondary-nerap me-2"></i>Compose Broadcast</h6>
      <form method="post">
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Target Region</label>
            <select name="region" class="form-select">
              <option value="">All Regions</option>
              <?php foreach ($regions as $r): ?><option value="<?= e($r['region']) ?>"><?= e($r['region']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Target Resource</label>
            <select name="resource_id" class="form-select">
              <option value="">All Resources</option>
              <?php foreach ($resources as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['category']) ?><?= $r['subtype']?' - '.e($r['subtype']):'' ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Message *</label>
          <textarea name="message" class="form-control" rows="4" required placeholder="e.g. A new emergency shelter has opened at..."></textarea>
        </div>
        <button class="btn btn-nerap-secondary w-100" onclick="return confirm('Send this broadcast now?');"><i class="fa-solid fa-paper-plane me-2"></i>Send Broadcast</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
