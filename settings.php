<?php
require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['email_to', 'email_from', 'scan_time', 'log_retention_days'];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) setSetting($f, trim($_POST[$f]));
    }
    setSetting('cron_enabled', isset($_POST['cron_enabled']) ? '1' : '0');
    flash('Settings saved.');
    header('Location: settings.php');
    exit;
}

$flash = getFlash();
?>

------------------------------------------------------------------------$_COOKIE



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SiteWatcher — Settings</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main>
<div class="page-header"><h1>Settings</h1></div>

<?php if ($flash): ?>
<div class="alert <?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>



<div class="card">
  <h2>Email Report</h2>


  <form method="post">
    <div class="form-group">
      <label>Send reports to (email)</label>
      <input type="email" name="email_to" value="<?= htmlspecialchars(getSetting('email_to')) ?>" class="input" required>
    </div>

    <div class="form-group">
      <label>Send reports from (email)</label>
      <input type="email" name="email_from" value="<?= htmlspecialchars(getSetting('email_from')) ?>" class="input">
    </div>

    <h2 style="margin-top:24px;">Cron / Auto Scan</h2>


    <div class="form-group">
      <label>
        <input type="checkbox" name="cron_enabled" <?= getSetting('cron_enabled','1')==='1' ? 'checked' : '' ?>>
        Enable automatic daily scanning
      </label>
    </div>


    <div class="form-group">
      <label>Scan time (24h)</label>
      <input type="time" name="scan_time" value="<?= htmlspecialchars(getSetting('scan_time','06:00')) ?>" class="input" style="width:140px;">
    </div>
    <div class="form-group">
      <label>keep logs for (days)</label>
      <input type="number" name="log_retention_days" value="<?= htmlspecialchars(getSetting('log_retention_days','30')) ?>" class="input" style="width:100px;" min="1" max="365">
    </div>
    <button type="submit" class="btn btn-primary">Save Settings</button>
  </form>
</div>


</main>
</body>
</html>
