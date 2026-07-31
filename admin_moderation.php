<?php
/**
 * =========================================================================
 * admin_moderation.php — Moderation queue UI. jQuery AJAX calls
 * api_moderation.php for approve/reject actions with an instant UI update
 * (no full page reload), matching the README's moderation dashboard spec.
 * =========================================================================
 */
$page_title = 'Moderation Queue';
require_once __DIR__ . '/admin_header.php';
$conn = db();

$filter = $_GET['status'] ?? 'pending';
$allowed = ['pending', 'approved', 'rejected'];
if (!in_array($filter, $allowed, true)) $filter = 'pending';

$stmt = $conn->prepare("SELECT s.*, f.name AS facility_name, r.category, r.subtype
    FROM submissions s
    LEFT JOIN facilities f ON f.id = s.facility_id
    LEFT JOIN resources r ON r.id = s.resource_id
    WHERE s.review_status = ?
    ORDER BY s.created_at DESC LIMIT 100");
$stmt->bind_param('s', $filter);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$facilities = $conn->query("SELECT id, name FROM facilities WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<ul class="nav nav-pills mb-3">
  <?php foreach ($allowed as $s): ?>
    <li class="nav-item"><a class="nav-link <?= $filter===$s?'active':'' ?> <?= $filter===$s?'btn-nerap-primary':'btn-outline-nerap' ?>" href="?status=<?= $s ?>"><?= ucfirst($s) ?></a></li>
  <?php endforeach; ?>
</ul>

<div id="queueContainer">
<?php if (empty($submissions)): ?>
  <div class="nerap-card p-4 text-center text-muted">No <?= e($filter) ?> submissions.</div>
<?php endif; ?>
<?php foreach ($submissions as $s): ?>
  <div class="nerap-card p-3 mb-2" id="row-<?= (int)$s['id'] ?>">
    <div class="row align-items-center g-2">
      <div class="col-md-7">
        <div class="fw-semibold"><?= e($s['facility_name'] ?: ($s['facility_name_raw'] ?: 'Unnamed facility')) ?>
          <?php if (!$s['facility_id']): ?><span class="badge-status status-unverified ms-1">New / Unlinked</span><?php endif; ?>
        </div>
        <div class="small text-muted">
          <?= e($s['category']) ?><?= $s['subtype'] ? ' - '.e($s['subtype']) : '' ?> &middot;
          Reported: <span class="badge-status status-<?= e($s['reported_status']) ?>"><?= e($s['reported_status']) ?></span> &middot;
          via <?= e(ucfirst($s['source'])) ?> <?= $s['phone'] ? '('.e($s['phone']).')' : '' ?> &middot;
          <?= e($s['created_at']) ?>
        </div>
        <?php if ($s['notes']): ?><div class="small mt-1"><?= e($s['notes']) ?></div><?php endif; ?>
        <?php if ($s['attachment_url']): ?><div class="mt-1"><a href="<?= e($s['attachment_url']) ?>" target="_blank" class="small">📎 View attachment</a></div><?php endif; ?>
      </div>
      <div class="col-md-5 text-md-end">
        <?php if ($filter === 'pending'): ?>
          <?php if (!$s['facility_id']): ?>
            <button class="btn btn-sm btn-nerap-secondary" onclick="openLinkModal(<?= (int)$s['id'] ?>, '<?= e(addslashes($s['facility_name_raw'])) ?>')"><i class="fa-solid fa-link me-1"></i>Link &amp; Approve</button>
          <?php else: ?>
            <button class="btn btn-sm btn-nerap-secondary" onclick="approveSubmission(<?= (int)$s['id'] ?>)"><i class="fa-solid fa-check me-1"></i>Approve</button>
          <?php endif; ?>
          <button class="btn btn-sm btn-outline-danger" onclick="rejectSubmission(<?= (int)$s['id'] ?>)"><i class="fa-solid fa-xmark me-1"></i>Reject</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<!-- Link-to-facility modal (for reports of brand new / unmatched facilities) -->
<div class="modal fade" id="linkModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Link Facility &amp; Approve</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Match to an existing facility</label>
          <select id="link_existing" class="form-select">
            <option value="">-- Create as new facility --</option>
            <?php foreach ($facilities as $f): ?><option value="<?= (int)$f['id'] ?>"><?= e($f['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div id="link_new_fields">
          <div class="mb-2"><label class="form-label">New Facility Name</label><input id="link_new_name" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Region</label><input id="link_new_region" class="form-control"></div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Latitude</label><input id="link_new_lat" type="number" step="any" class="form-control"></div>
            <div class="col-6"><label class="form-label">Longitude</label><input id="link_new_lng" type="number" step="any" class="form-control"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-nerap-secondary w-100" onclick="submitLinkAndApprove()">Approve</button></div>
    </div>
  </div>
</div>

<script>
let linkingSubmissionId = null;

function approveSubmission(id, extra) {
    extra = extra || {};
    $.post('api_moderation.php', Object.assign({ action: 'approve', submission_id: id }, extra), function (res) {
        nerapToast(res.message, res.status === 200 ? 'success' : 'error');
        if (res.status === 200) $('#row-' + id).fadeOut(300, function () { $(this).remove(); });
    }, 'json');
}

function rejectSubmission(id) {
    if (!confirm('Reject this submission?')) return;
    $.post('api_moderation.php', { action: 'reject', submission_id: id }, function (res) {
        nerapToast(res.message, res.status === 200 ? 'success' : 'error');
        if (res.status === 200) $('#row-' + id).fadeOut(300, function () { $(this).remove(); });
    }, 'json');
}

function openLinkModal(id, rawName) {
    linkingSubmissionId = id;
    $('#link_new_name').val(rawName);
    $('#link_existing').val('');
    new bootstrap.Modal(document.getElementById('linkModal')).show();
}

$('#link_existing').on('change', function () {
    $('#link_new_fields').toggle($(this).val() === '');
});

function submitLinkAndApprove() {
    const existing = $('#link_existing').val();
    const extra = existing ? { facility_id: existing } : {
        new_facility_name: $('#link_new_name').val(),
        new_region: $('#link_new_region').val(),
        new_lat: $('#link_new_lat').val(),
        new_lng: $('#link_new_lng').val(),
    };
    approveSubmission(linkingSubmissionId, extra);
    bootstrap.Modal.getInstance(document.getElementById('linkModal')).hide();
}
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
