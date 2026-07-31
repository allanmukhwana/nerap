<?php
/**
 * =========================================================================
 * admin_users.php — Manage admin/moderator accounts. Restricted to
 * super_admin role (county health department onboarding, README Phase 1).
 * =========================================================================
 */
$page_title = 'Admin Users';
require_once __DIR__ . '/admin_header.php';
$conn = db();

if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    echo '<div class="alert alert-danger">Only super admins can manage users.</div>';
    require_once __DIR__ . '/admin_footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_user') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name']); $email = trim($_POST['email']); $role = $_POST['role']; $region = trim($_POST['region']) ?: null;
        if ($id > 0) {
            if (!empty($_POST['password'])) {
                $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admin_users SET name=?, email=?, role=?, region=?, password_hash=? WHERE id=?");
                $stmt->bind_param('sssssi', $name, $email, $role, $region, $hash, $id);
            } else {
                $stmt = $conn->prepare("UPDATE admin_users SET name=?, email=?, role=?, region=? WHERE id=?");
                $stmt->bind_param('ssssi', $name, $email, $role, $region, $id);
            }
        } else {
            $hash = password_hash($_POST['password'] ?: bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin_users (name, email, password_hash, role, region) VALUES (?,?,?,?,?)");
            $stmt->bind_param('sssss', $name, $email, $hash, $role, $region);
        }
        $stmt->execute();
        header('Location: admin_users.php?saved=1'); exit;
    }

    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE admin_users SET status = IF(status='active','disabled','active') WHERE id = $id");
        header('Location: admin_users.php'); exit;
    }
}

$users = $conn->query("SELECT * FROM admin_users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex justify-content-between mb-3"><div></div>
  <button class="btn btn-nerap-secondary" onclick="openUserModal()"><i class="fa-solid fa-user-plus me-2"></i>Add User</button>
</div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">User saved.</div><?php endif; ?>

<div class="nerap-card p-3">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Region</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td>
          <td class="text-capitalize"><?= e(str_replace('_',' ',$u['role'])) ?></td>
          <td><?= e($u['region'] ?: '—') ?></td>
          <td><?= $u['status']==='active' ? '<span class="badge-status status-confirmed">Active</span>' : '<span class="badge-status status-unverified">Disabled</span>' ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-nerap" onclick='openUserModal(<?= json_encode($u) ?>)'><i class="fa-solid fa-pen"></i></button>
            <form method="post" class="d-inline"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><?= $u['status']==='active'?'Disable':'Enable' ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="userModalTitle">Add User</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="action" value="save_user"><input type="hidden" name="id" id="u_id">
        <div class="mb-2"><label class="form-label">Name *</label><input class="form-control" name="name" id="u_name" required></div>
        <div class="mb-2"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" id="u_email" required></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Role</label>
            <select class="form-select" name="role" id="u_role"><option value="moderator">Moderator</option><option value="super_admin">Super Admin</option></select>
          </div>
          <div class="col-6 mb-2"><label class="form-label">Region</label><input class="form-control" name="region" id="u_region" placeholder="optional"></div>
        </div>
        <div class="mb-2"><label class="form-label">Password <span class="text-muted small" id="u_pw_hint">(leave blank to keep unchanged)</span></label><input class="form-control" type="password" name="password" id="u_password"></div>
      </div>
      <div class="modal-footer"><button class="btn btn-nerap-secondary w-100">Save User</button></div>
    </form>
  </div>
</div>

<script>
function openUserModal(u) {
    u = u || {};
    $('#userModalTitle').text(u.id ? 'Edit User' : 'Add User');
    $('#u_id').val(u.id || ''); $('#u_name').val(u.name || ''); $('#u_email').val(u.email || '');
    $('#u_role').val(u.role || 'moderator'); $('#u_region').val(u.region || ''); $('#u_password').val('');
    $('#u_pw_hint').toggle(!!u.id);
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
