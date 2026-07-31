<?php
/**
 * =========================================================================
 * auth_forgot.php — Password reset request. Generates a one-time token
 * stored on admin_users.reset_token and emails a reset link via Brevo API.
 * =========================================================================
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_brevo.php';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $stmt = db()->prepare("SELECT id, name FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($admin) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $upd = db()->prepare("UPDATE admin_users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $upd->bind_param('ssi', $token, $expires, $admin['id']);
        $upd->execute();

        $resetLink = SITE_URL . '/auth_reset.php?token=' . $token;
        brevo_send_email($email, $admin['name'], 'Reset your ' . SITE_NAME . ' password',
            '<p>Hi ' . e($admin['name']) . ',</p><p>Click the button below to reset your password. This link expires in 1 hour.</p>' .
            '<p style="text-align:center;margin:20px 0;"><a href="' . e($resetLink) . '" style="background:' . COLOR_SECONDARY . ';color:#fff;padding:10px 22px;border-radius:8px;text-decoration:none;">Reset Password</a></p>' .
            '<p style="font-size:12px;color:#6c757d;">If you did not request this, you can safely ignore this email.</p>'
        );
    }
    // Always show generic success (avoid leaking which emails are registered)
    $sent = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot Password — <?= e(SITE_NAME) ?></title>
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
      <h4 class="fw-brand mt-3">Forgot Password</h4>
    </div>
    <?php if ($sent): ?>
      <div class="alert alert-success small">If that email exists in our system, a reset link has been sent.</div>
    <?php else: ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label fw-semibold">Email</label>
          <input type="email" name="email" class="form-control" required autofocus>
        </div>
        <button type="submit" class="btn btn-nerap-primary w-100 py-2">Send Reset Link</button>
      </form>
    <?php endif; ?>
    <div class="text-center mt-3"><a href="auth_login.php" class="small text-muted">Back to Login</a></div>
  </div>
</div>
</body>
</html>
