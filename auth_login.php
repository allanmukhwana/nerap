<?php
/**
 * =========================================================================
 * auth_login.php — Admin / Moderator login screen.
 * On success, stores admin_id / admin_name / admin_role in $_SESSION and
 * redirects to admin_dashboard.php. Protected pages call require_admin()
 * from config.php which checks $_SESSION['admin_id'].
 * =========================================================================
 */
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare("SELECT id, name, email, password_hash, role, status FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        $error = 'Invalid email or password.';
    } elseif ($admin['status'] !== 'active') {
        $error = 'Your account has been disabled. Contact a super admin.';
    } else {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_role'] = $admin['role'];
        header('Location: admin_dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Moderator Login — <?= e(SITE_NAME) ?></title>
<link rel="icon" href="<?= SITE_LOGO ?>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="assets/style.css?v=1" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="text-center mb-4">
      <img src="<?= SITE_LOGO ?>" alt="logo" style="height:44px" onerror="this.style.display='none'">
      <h4 class="fw-brand mt-3 mb-0">Moderator Login</h4>
      <p class="text-muted small">Sign in to manage facilities, moderate reports, and send alerts.</p>
    </div>
    <?php if ($error): ?><div class="alert alert-danger small"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label fw-semibold">Email</label>
        <input type="email" name="email" class="form-control" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-nerap-primary w-100 py-2">Log In</button>
    </form>
    <div class="text-center mt-3">
      <a href="auth_forgot.php" class="small text-muted">Forgot password?</a> &middot;
      <a href="index.php" class="small text-muted">Back to Home</a>
    </div>
  </div>
</div>
</body>
</html>
