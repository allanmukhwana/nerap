<?php
/**
 * =========================================================================
 * subscribe.php — Web form for "Targeted WhatsApp Broadcast Alerts" opt-in.
 * Users choose a channel (WhatsApp/email/both), optional region + resource
 * scope. Sends a Brevo confirmation email when an email address is given.
 * =========================================================================
 */
$page_title = 'Get Alerts — ' . SITE_NAME;
require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/email_brevo.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $region = trim($_POST['region'] ?? '') ?: null;
    $resource_id = !empty($_POST['resource_id']) ? (int)$_POST['resource_id'] : null;
    $channel = $_POST['channel'] ?? 'whatsapp';

    if (($channel === 'whatsapp' && $phone === '') || ($channel === 'email' && $email === '') || ($channel === 'both' && ($phone === '' || $email === ''))) {
        $error = 'Please provide the contact details required for your selected channel.';
    } else {
        $stmt = db()->prepare("INSERT INTO subscribers (phone, email, name, region, resource_id, channel) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssis', $phone, $email, $name, $region, $resource_id, $channel);
        $stmt->execute();

        if ($email !== '') {
            brevo_send_email(
                $email,
                $name ?: 'there',
                'Welcome to NERAP Cloud Alerts',
                '<p>Hi ' . e($name ?: 'there') . ',</p><p>You are now subscribed to NERAP Cloud resource alerts' .
                ($region ? ' for <strong>' . e($region) . '</strong>' : '') . '. We will notify you the moment a critical resource becomes available or a shortage is confirmed near you.</p>' .
                '<p>Stay safe,<br>' . e(SITE_NAME) . '</p>'
            );
        }
        $success = true;
    }
}

$resources = db()->query("SELECT id, category, subtype FROM resources ORDER BY category, subtype")->fetch_all(MYSQLI_ASSOC);
$regions = db()->query("SELECT DISTINCT region FROM facilities ORDER BY region")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container py-4" style="max-width:640px;">
  <h3 class="fw-brand mb-1"><i class="fa-solid fa-bell text-secondary-nerap me-2"></i>Get Proactive Alerts</h3>
  <p class="text-muted mb-4">Doctors, nurses, ambulance coordinators and disaster managers: subscribe to receive a push notification the moment a resource you need becomes available.</p>

  <?php if ($success): ?>
    <div class="alert alert-success rounded-nerap"><i class="fa-solid fa-circle-check me-2"></i>You're subscribed! Watch your WhatsApp/email for alerts.</div>
  <?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger rounded-nerap"><?= e($error) ?></div><?php endif; ?>

  <form method="post" class="nerap-card p-4">
    <div class="mb-3">
      <label class="form-label fw-semibold">Full Name</label>
      <input type="text" name="name" class="form-control">
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">Notify me via</label>
      <select name="channel" id="channelSelect" class="form-select">
        <option value="whatsapp">WhatsApp only</option>
        <option value="email">Email only</option>
        <option value="both">Both WhatsApp &amp; Email</option>
      </select>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-6">
        <label class="form-label fw-semibold">WhatsApp Number</label>
        <input type="text" name="phone" class="form-control" placeholder="+2547...">
      </div>
      <div class="col-6">
        <label class="form-label fw-semibold">Email</label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com">
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">Only alert me for Region (optional)</label>
      <select name="region" class="form-select">
        <option value="">All Regions</option>
        <?php foreach ($regions as $r): ?><option value="<?= e($r['region']) ?>"><?= e($r['region']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">Only alert me for Resource (optional)</label>
      <select name="resource_id" class="form-select">
        <option value="">All Resources</option>
        <?php foreach ($resources as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['category']) ?><?= $r['subtype']?' - '.e($r['subtype']):'' ?></option><?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-nerap-secondary w-100"><i class="fa-solid fa-bell me-2"></i>Subscribe</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
