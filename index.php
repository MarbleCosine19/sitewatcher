<?php
require_once __DIR__ . '/includes/config.php';

$flash = getFlash();


try {
    $lastScan = db()->query('SELECT * FROM scan_logs ORDER BY scan_date DESC, id DESC LIMIT 1')->fetch();
    $urlCount = db()->query('SELECT COUNT(*) as n FROM urls WHERE active=1')->fetch()['n'];


    $history = db()->query('SELECT * FROM scan_logs ORDER BY scan_date DESC, id DESC LIMIT 7')->fetchAll();
} catch (Exception $e) {
    $lastScan = null; $urlCount = 0; $history = [];
}
?>



--------------------------------------------------------------------------


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SiteWatcher — Dashboard</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>

<main>
<div class="page-header">
  <h1>Dashboard</h1>
  <div>
    <a href="run.php" class="btn btn-primary" id="runBtn">▶ Run Scan Now</a>
  </div>
</div>

<?php if ($flash): ?>
<div class="alert <?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<!-- Stats bar -->
<div class="stats-row">
  <div class="stat-card">
    <div class="stat-num"><?= $urlCount ?></div>
    <div class="stat-lbl">Monitored Sites</div>
  </div>
  <?php if ($lastScan): ?>
  <div class="stat-card">
    <div class="stat-num"><?= $lastScan['total_checked'] ?></div>
    <div class="stat-lbl">Last Scan Checked</div>
  </div>
  <div class="stat-card <?= $lastScan['total_errors'] > 0 ? 'stat-error' : '' ?>">
    <div class="stat-num"><?= $lastScan['total_errors'] ?></div>
    <div class="stat-lbl">Errors Found</div>
  </div>
  <div class="stat-card <?= $lastScan['total_warnings'] > 0 ? 'stat-warn' : '' ?>">
    <div class="stat-num"><?= $lastScan['total_warnings'] ?></div>
    <div class="stat-lbl">WARNINGS</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= date('M j', strtotime($lastScan['scan_date'])) ?></div>
    <div class="stat-lbl">Last scan date</div>
  </div>
  <?php else: ?>
  <div class="stat-card"><div class="stat-num">—</div><div class="stat-lbl">No scans yet</div></div>
  <?php endif; ?>
</div>

<!-- Scan History Table -->
<div class="card">
  <h2>Scan History</h2>
  <?php if (empty($history)): ?>
  <p class="muted">No scans have been run yet. Click <strong>run scan now</strong> to start.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Date</th><th>Checked</th><th>Errors</th><th>Warnings</th><th>Duration</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?>
    <?php
      $dur = ($h['scan_end'] && $h['scan_start'])
           ? round((strtotime($h['scan_end']) - strtotime($h['scan_start'])) / 60, 1) . ' min'
           : '—';
    ?>
    <tr>
      <td><?= htmlspecialchars($h['scan_date']) ?></td>
      <td><?= $h['total_checked'] ?></td>
      <td><?= $h['total_errors'] > 0 ? '<span class="badge error">'.$h['total_errors'].'</span>' : '0' ?></td>
      <td><?= $h['total_warnings'] > 0 ? '<span class="badge warn">'.$h['total_warnings'].'</span>' : '0' ?></td>
      <td><?= $dur ?></td>
      <td><a href="log.php?date=<?= urlencode($h['scan_date']) ?>" class="btn btn-sm">View Log</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

</main>
</body>
</html>
