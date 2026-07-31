<?php
/**
 * =========================================================================
 * admin_resources.php — CRUD for `resources` (resource categories/subtypes
 * + multilingual synonym keywords used by the WhatsApp fuzzy matcher in
 * whatsapp_query.php).
 * =========================================================================
 */
$page_title = 'Resource Types';
require_once __DIR__ . '/admin_header.php';
$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_resource') {
        $id = (int)($_POST['id'] ?? 0);
        $category = trim($_POST['category']); $subtype = trim($_POST['subtype']); $synonyms = trim($_POST['synonyms']);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE resources SET category=?, subtype=?, synonyms=? WHERE id=?");
            $stmt->bind_param('sssi', $category, $subtype, $synonyms, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO resources (category, subtype, synonyms) VALUES (?,?,?)");
            $stmt->bind_param('sss', $category, $subtype, $synonyms);
        }
        $stmt->execute();
        header('Location: admin_resources.php?saved=1'); exit;
    }
    if ($action === 'delete_resource') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM resources WHERE id = ?");
        $stmt->bind_param('i', $id); $stmt->execute();
        header('Location: admin_resources.php?deleted=1'); exit;
    }
}

$resources = $conn->query("SELECT * FROM resources ORDER BY category, subtype")->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex justify-content-between mb-3">
  <div></div>
  <button class="btn btn-nerap-secondary" onclick="openResourceModal()"><i class="fa-solid fa-plus me-2"></i>Add Resource Type</button>
</div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Resource saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Resource deleted.</div><?php endif; ?>

<div class="nerap-card p-3">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Category</th><th>Sub-type</th><th>Synonyms</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($resources as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['category']) ?></td>
          <td><?= e($r['subtype']) ?></td>
          <td class="text-muted small"><?= e($r['synonyms']) ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-nerap" onclick='openResourceModal(<?= json_encode($r) ?>)'><i class="fa-solid fa-pen"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this resource type? This also removes it from all facility stock records.');">
              <input type="hidden" name="action" value="delete_resource"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="resourceModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="resourceModalTitle">Add Resource Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="action" value="save_resource"><input type="hidden" name="id" id="r_id">
        <div class="mb-2"><label class="form-label">Category *</label><input class="form-control" name="category" id="r_category" placeholder="e.g. Antivenom, Blood, ICU" required></div>
        <div class="mb-2"><label class="form-label">Sub-type</label><input class="form-control" name="subtype" id="r_subtype" placeholder="e.g. Polyvalent (Snake), O-Negative"></div>
        <div class="mb-2"><label class="form-label">Synonyms (comma separated, any language)</label>
          <textarea class="form-control" name="synonyms" id="r_synonyms" rows="2" placeholder="e.g. blood,damu,dam"></textarea>
          <div class="form-text">Used to fuzzy-match WhatsApp keywords (e.g. Swahili "damu" → Blood).</div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-nerap-secondary w-100">Save Resource</button></div>
    </form>
  </div>
</div>

<script>
function openResourceModal(r) {
    r = r || {};
    $('#resourceModalTitle').text(r.id ? 'Edit Resource Type' : 'Add Resource Type');
    $('#r_id').val(r.id || ''); $('#r_category').val(r.category || '');
    $('#r_subtype').val(r.subtype || ''); $('#r_synonyms').val(r.synonyms || '');
    new bootstrap.Modal(document.getElementById('resourceModal')).show();
}
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
