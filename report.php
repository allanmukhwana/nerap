<?php
/**
 * =========================================================================
 * report.php — Web-based crowdsourced stock reporting form (alternative to
 * the WhatsApp reporting flow). Submits into the SAME `submissions` table
 * and moderation queue used by whatsapp_webhook.php, so moderators review
 * both channels from one place (admin_moderation.php).
 * =========================================================================
 */
$page_title = 'Report Stock — ' . SITE_NAME;
require_once __DIR__ . '/includes_header.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = db();
    $facility_id = !empty($_POST['facility_id']) ? (int)$_POST['facility_id'] : null;
    $facility_name_raw = trim($_POST['facility_name_raw'] ?? '');
    $resource_id = (int)($_POST['resource_id'] ?? 0);
    $reported_status = $_POST['reported_status'] ?? '';
    $quantity = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : null;
    $phone = trim($_POST['phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($resource_id <= 0 || !in_array($reported_status, ['confirmed', 'low', 'out'], true) || (!$facility_id && $facility_name_raw === '')) {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $conn->prepare("INSERT INTO submissions (source, phone, facility_id, facility_name_raw, resource_id, reported_status, quantity, notes, review_status)
            VALUES ('web', ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param('sisssis', $phone, $facility_id, $facility_name_raw, $resource_id, $reported_status, $quantity, $notes);
        $stmt->execute();
        $success = true;
    }
}

$facilities = db()->query("SELECT id, name, region FROM facilities WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$resources  = db()->query("SELECT id, category, subtype FROM resources ORDER BY category, subtype")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container py-4" style="max-width:640px;">
  <h3 class="fw-brand mb-1"><i class="fa-solid fa-file-circle-plus text-secondary-nerap me-2"></i>Report Resource Stock</h3>
  <p class="text-muted mb-4">Field reporters and facility staff: submit an inventory update. Every submission is reviewed by a moderator before it appears on the public map (tiered verification).</p>

  <?php if ($success): ?>
    <div class="alert alert-success rounded-nerap"><i class="fa-solid fa-circle-check me-2"></i>Thank you! Your report has been submitted for moderation.</div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger rounded-nerap"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="nerap-card p-4">
    <div class="mb-3">
      <label class="form-label fw-semibold">Facility</label>
      <select name="facility_id" id="facilitySelect" class="form-select">
        <option value="">-- New / Not Listed --</option>
        <?php foreach ($facilities as $f): ?>
          <option value="<?= (int)$f['id'] ?>"><?= e($f['name']) ?> (<?= e($f['region']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3" id="newFacilityWrap">
      <label class="form-label fw-semibold">New Facility / Shelter Name</label>
      <input type="text" name="facility_name_raw" class="form-control" placeholder="e.g. Nyeri County Referral Hospital">
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">Resource *</label>
      <select name="resource_id" class="form-select" required>
        <option value="">Select resource</option>
        <?php foreach ($resources as $r): ?>
          <option value="<?= (int)$r['id'] ?>"><?= e($r['category']) ?><?= $r['subtype'] ? ' - '.e($r['subtype']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-6">
        <label class="form-label fw-semibold">Stock Status *</label>
        <select name="reported_status" class="form-select" required>
          <option value="confirmed">🟢 Confirmed / In Stock</option>
          <option value="low">🟡 Low Stock</option>
          <option value="out">🔴 Out of Stock</option>
        </select>
      </div>
      <div class="col-6">
        <label class="form-label fw-semibold">Quantity (optional)</label>
        <input type="number" min="0" name="quantity" class="form-control" placeholder="e.g. 12">
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">Your Phone (optional)</label>
      <input type="text" name="phone" class="form-control" placeholder="+2547...">
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">Notes (optional)</label>
      <textarea name="notes" class="form-control" rows="2"></textarea>
    </div>
    <button type="submit" class="btn btn-nerap-secondary w-100"><i class="fa-solid fa-paper-plane me-2"></i>Submit Report</button>
  </form>
</div>

<script>
$(function () {
    function toggleNewFacility() {
        $('#newFacilityWrap').toggle($('#facilitySelect').val() === '');
    }
    $('#facilitySelect').on('change', toggleNewFacility);
    toggleNewFacility();
});
</script>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
