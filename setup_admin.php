<?php
/**
 * =========================================================================
 * setup_admin.php — ONE-TIME first super_admin account creator.
 *
 * Run this once in your browser after importing db_schema.sql:
 *   https://yourdomain.com/setup_admin.php
 *
 * It safely no-ops if an admin_users row already exists, so it cannot be
 * abused to create rogue admins after initial setup. DELETE THIS FILE once
 * you have logged in successfully.
 * =========================================================================
 */
require_once __DIR__ . '/config.php';

$conn = db();
$existing = (int)$conn->query("SELECT COUNT(*) c FROM admin_users")->fetch_assoc()['c'];

$message = '';
$done = false;

if ($existing > 0) {
    $message = 'Setup already complete — an admin account already exists. Delete this file for security.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || strlen($password) < 8) {
        $message = 'Please fill all fields (password must be at least 8 characters).';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin_users (name, email, password_hash, role) VALUES (?, ?, ?, 'super_admin')");
        $stmt->bind_param('sss', $name, $email, $hash);
        $stmt->execute();
        $done = true;
        $message = 'Super admin account created! You can now log in and should delete setup_admin.php.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Initial Setup — <?= e(SITE_NAME) ?></title>
<link rel="icon" href="<?= SITE_LOGO ?>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/style.css?v=1" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="text-center mb-4">
      <img src="<?= SITE_LOGO ?>" style="height:44px" onerror="this.style.display='none'">
      <h4 class="fw-brand mt-3">Initial Setup</h4>
      <p class="text-muted small">Create the first super admin account.</p>
    </div>
    <?php if ($message): ?><div class="alert <?= $done || $existing ? 'alert-success' : 'alert-danger' ?> small"><?= e($message) ?></div><?php endif; ?>
    <?php if ($existing === 0 && !$done): ?>
      <form method="post">
        <div class="mb-3"><label class="form-label fw-semibold">Full Name</label><input class="form-control" name="name" required></div>
        <div class="mb-3"><label class="form-label fw-semibold">Email</label><input class="form-control" type="email" name="email" required></div>
        <div class="mb-3"><label class="form-label fw-semibold">Password</label><input class="form-control" type="password" name="password" required minlength="8"></div>
        <button class="btn btn-nerap-primary w-100 py-2">Create Super Admin</button>
      </form>
    <?php else: ?>
      <div class="text-center"><a href="auth_login.php" class="btn btn-nerap-secondary w-100">Go to Login</a></div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
