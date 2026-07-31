<?php
/**
 * =========================================================================
 * auth_reset.php — Completes a password reset given a valid token (from
 * the link emailed by auth_forgot.php via Brevo).
 * =========================================================================
 */
require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = false;

$stmt = db()->prepare("SELECT id FROM admin_users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd = db()->prepare("UPDATE admin_users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $upd->bind_param('si', $hash, $admin['id']);
        $upd->execute();
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password — <?= e(SITE_NAME) ?></title>
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
      <h4 class="fw-brand mt-3">Reset Password</h4>
    </div>
    <?php if ($success): ?>
      <div class="alert alert-success small">Password updated. You can now <a href="auth_login.php">log in</a>.</div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger small"><?= e($error) ?></div><?php endif; ?>
      <?php if ($admin): ?>
      <form method="post">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="mb-3">
          <label class="form-label fw-semibold">New Password</label>
          <input type="password" name="password" class="form-control" required minlength="8">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Confirm Password</label>
          <input type="password" name="confirm" class="form-control" required minlength="8">
        </div>
        <button type="submit" class="btn btn-nerap-primary w-100 py-2">Update Password</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
