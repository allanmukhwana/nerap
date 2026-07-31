<?php
/**
 * =========================================================================
 * admin_facilities.php — CRUD for `facilities` + per-facility resource
 * stock management (`facility_resources`). One flat page with Bootstrap
 * modals for Add/Edit/Manage Stock, jQuery handles the AJAX-free POST
 * submissions (simple form posts back to this same file).
 * =========================================================================
 */
$page_title = 'Facilities';
require_once __DIR__ . '/admin_header.php';
$conn = db();

// ---- POST actions (create / update / delete / update stock) ---------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_facility') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name']); $type = $_POST['type']; $region = trim($_POST['region']);
        $address = trim($_POST['address']); $lat = (float)$_POST['latitude']; $lng = (float)$_POST['longitude'];
        $phone = trim($_POST['phone']); $verified = isset($_POST['is_verified']) ? 1 : 0;

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE facilities SET name=?, type=?, region=?, address=?, latitude=?, longitude=?, phone=?, is_verified=? WHERE id=?");
            $stmt->bind_param('ssssddsii', $name, $type, $region, $address, $lat, $lng, $phone, $verified, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO facilities (name, type, region, address, latitude, longitude, phone, is_verified) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param('ssssddsi', $name, $type, $region, $address, $lat, $lng, $phone, $verified);
        }
        $stmt->execute();
        header('Location: admin_facilities.php?saved=1'); exit;
    }

    if ($action === 'delete_facility') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM facilities WHERE id = ?");
        $stmt->bind_param('i', $id); $stmt->execute();
        header('Location: admin_facilities.php?deleted=1'); exit;
    }

    if ($action === 'update_stock') {
        $facilityId = (int)$_POST['facility_id'];
        $statuses = $_POST['status'] ?? []; // [resource_id => status]
        foreach ($statuses as $resourceId => $status) {
            $resourceId = (int)$resourceId;
            if ($status === '') continue;
            $stmt = $conn->prepare("INSERT INTO facility_resources (facility_id, resource_id, status, last_verified_at) VALUES (?,?,?,NOW())
                ON DUPLICATE KEY UPDATE status = VALUES(status), last_verified_at = NOW()");
            $stmt->bind_param('iis', $facilityId, $resourceId, $status);
            $stmt->execute();
        }
        header('Location: admin_facilities.php?stock_saved=1'); exit;
    }
}

$facilities = $conn->query("SELECT * FROM facilities ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$resources = $conn->query("SELECT * FROM resources ORDER BY category, subtype")->fetch_all(MYSQLI_ASSOC);

// Pre-load current stock per facility for the "Manage Stock" modal (keyed by facility_id).
$stockByFacility = [];
$stockRes = $conn->query("SELECT facility_id, resource_id, status FROM facility_resources");
while ($row = $stockRes->fetch_assoc()) { $stockByFacility[$row['facility_id']][$row['resource_id']] = $row['status']; }
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div></div>
  <button class="btn btn-nerap-secondary" data-bs-toggle="modal" data-bs-target="#facilityModal" onclick="openFacilityModal()"><i class="fa-solid fa-plus me-2"></i>Add Facility</button>
</div>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Facility saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Facility deleted.</div><?php endif; ?>
<?php if (isset($_GET['stock_saved'])): ?><div class="alert alert-success">Stock levels updated.</div><?php endif; ?>

<div class="nerap-card p-3">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Name</th><th>Type</th><th>Region</th><th>Phone</th><th>Verified</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($facilities as $f): ?>
        <tr>
          <td class="fw-semibold"><?= e($f['name']) ?></td>
          <td class="text-capitalize"><?= e(str_replace('_',' ',$f['type'])) ?></td>
          <td><?= e($f['region']) ?></td>
          <td><?= e($f['phone']) ?></td>
          <td><?= $f['is_verified'] ? '<span class="badge-status status-confirmed">Verified</span>' : '<span class="badge-status status-unverified">No</span>' ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-nerap" onclick='openStockModal(<?= json_encode(["id"=>$f['id'],"name"=>$f['name'],"stock"=>$stockByFacility[$f['id']] ?? new stdClass()]) ?>)'><i class="fa-solid fa-boxes-stacked"></i></button>
            <button class="btn btn-sm btn-outline-nerap" onclick='openFacilityModal(<?= json_encode($f) ?>)'><i class="fa-solid fa-pen"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this facility?');">
              <input type="hidden" name="action" value="delete_facility"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($facilities)): ?><tr><td colspan="6" class="text-center text-muted py-4">No facilities yet. Click "Add Facility" to create one.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- =====================  ADD/EDIT FACILITY MODAL  ===================== -->
<div class="modal fade" id="facilityModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="facilityModalTitle">Add Facility</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="action" value="save_facility">
        <input type="hidden" name="id" id="f_id">
        <div class="mb-2"><label class="form-label">Name *</label><input class="form-control" name="name" id="f_name" required></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Type</label>
            <select class="form-select" name="type" id="f_type">
              <option value="hospital">Hospital</option><option value="clinic">Clinic</option><option value="pharmacy">Pharmacy</option>
              <option value="shelter">Shelter</option><option value="distribution_point">Distribution Point</option><option value="other">Other</option>
            </select>
          </div>
          <div class="col-6 mb-2"><label class="form-label">Region *</label><input class="form-control" name="region" id="f_region" required></div>
        </div>
        <div class="mb-2"><label class="form-label">Address</label><input class="form-control" name="address" id="f_address"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Latitude *</label><input class="form-control" name="latitude" id="f_lat" type="number" step="any" required></div>
          <div class="col-6 mb-2"><label class="form-label">Longitude *</label><input class="form-control" name="longitude" id="f_lng" type="number" step="any" required></div>
        </div>
        <div class="mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone" id="f_phone"></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_verified" id="f_verified"><label class="form-check-label" for="f_verified">Verified / trusted source</label></div>
      </div>
      <div class="modal-footer"><button class="btn btn-nerap-secondary w-100">Save Facility</button></div>
    </form>
  </div>
</div>

<!-- =====================  MANAGE STOCK MODAL  ===================== -->
<div class="modal fade" id="stockModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Manage Stock — <span id="s_name"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" style="max-height:60vh; overflow-y:auto;">
        <input type="hidden" name="action" value="update_stock">
        <input type="hidden" name="facility_id" id="s_facility_id">
        <?php foreach ($resources as $r): ?>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small"><?= e($r['category']) ?><?= $r['subtype'] ? ' - '.e($r['subtype']) : '' ?></span>
            <select class="form-select form-select-sm w-auto stock-select" name="status[<?= (int)$r['id'] ?>]" data-resource="<?= (int)$r['id'] ?>">
              <option value="">--</option>
              <option value="confirmed">🟢 Confirmed</option>
              <option value="low">🟡 Low</option>
              <option value="out">🔴 Out</option>
              <option value="unverified">⚫ Unverified</option>
            </select>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="modal-footer"><button class="btn btn-nerap-secondary w-100">Save Stock Levels</button></div>
    </form>
  </div>
</div>

<script>
function openFacilityModal(f) {
    f = f || {};
    $('#facilityModalTitle').text(f.id ? 'Edit Facility' : 'Add Facility');
    $('#f_id').val(f.id || '');
    $('#f_name').val(f.name || '');
    $('#f_type').val(f.type || 'hospital');
    $('#f_region').val(f.region || '');
    $('#f_address').val(f.address || '');
    $('#f_lat').val(f.latitude || '');
    $('#f_lng').val(f.longitude || '');
    $('#f_phone').val(f.phone || '');
    $('#f_verified').prop('checked', !!parseInt(f.is_verified || 0));
    new bootstrap.Modal(document.getElementById('facilityModal')).show();
}
function openStockModal(f) {
    $('#s_name').text(f.name);
    $('#s_facility_id').val(f.id);
    $('.stock-select').each(function () {
        const rid = $(this).data('resource');
        $(this).val(f.stock && f.stock[rid] ? f.stock[rid] : '');
    });
    new bootstrap.Modal(document.getElementById('stockModal')).show();
}
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
