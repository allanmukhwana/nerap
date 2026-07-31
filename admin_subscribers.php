<?php
/**
 * =========================================================================
 * admin_subscribers.php — View & manage alert subscribers (from both the
 * WhatsApp ALERT command and the web subscribe.php form).
 * =========================================================================
 */
$page_title = 'Subscribers';
require_once __DIR__ . '/admin_header.php';
$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    $id = (int)$_POST['id'];
    $conn->query("UPDATE subscribers SET status = IF(status='active','unsubscribed','active') WHERE id = $id");
    header('Location: admin_subscribers.php'); exit;
}

$subscribers = $conn->query("SELECT s.*, r.category, r.subtype FROM subscribers s LEFT JOIN resources r ON r.id = s.resource_id ORDER BY s.created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="nerap-card p-3">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Name</th><th>Contact</th><th>Channel</th><th>Scope</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($subscribers as $s): ?>
        <tr>
          <td><?= e($s['name'] ?: '—') ?></td>
          <td class="small"><?= e($s['phone']) ?><?= $s['phone'] && $s['email'] ? '<br>' : '' ?><?= e($s['email']) ?></td>
          <td class="text-capitalize"><?= e($s['channel']) ?></td>
          <td class="small"><?= e($s['region'] ?: 'All regions') ?> &middot; <?= $s['category'] ? e($s['category']).($s['subtype']?' - '.e($s['subtype']):'') : 'All resources' ?></td>
          <td><?= $s['status'] === 'active' ? '<span class="badge-status status-confirmed">Active</span>' : '<span class="badge-status status-unverified">Unsubscribed</span>' ?></td>
          <td class="text-end">
            <form method="post" class="d-inline"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-sm btn-outline-nerap"><?= $s['status']==='active' ? 'Unsubscribe' : 'Reactivate' ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($subscribers)): ?><tr><td colspan="6" class="text-center text-muted py-4">No subscribers yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
